<?php

namespace Tests\Feature;

use App\Jobs\RunTicketTriageJob;
use App\Models\TicketTriage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TicketTriageTest extends TestCase
{
    use RefreshDatabase;

    private const CLIENT_KEY = 'test-client-key';

    private string $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        $this->workspace = sys_get_temp_dir() . '/triage-test-' . uniqid();
        File::makeDirectory($this->workspace . '/.claude/skills/fix-sprint-bugs', 0755, true);

        config([
            'tickets.client_key' => self::CLIENT_KEY,
            'tickets.youtrack_base_url' => 'https://youtrack.test',
            'tickets.youtrack_token' => 'test-youtrack-token',
            'tickets.triage_workspace_path' => $this->workspace,
            'tickets.triage_claude_bin' => '/usr/local/bin/claude',
            'tickets.triage_claude_token' => 'test-claude-token',
            'tickets.triage_model' => 'sonnet',
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->workspace);

        parent::tearDown();
    }

    public function test_requires_client_key(): void
    {
        $this->postJson('/api/tickets/PRD-6526/triage')->assertUnauthorized();
        $this->getJson('/api/tickets/PRD-6526/triage')->assertUnauthorized();
    }

    public function test_triage_runs_claude_read_only_and_stores_the_analysis(): void
    {
        $this->fakeYouTrack();
        Process::fake(['*' => Process::result($this->claudeOutput($this->analysis('blocked', 'low')))]);

        $this->withHeader('X-Client-Key', self::CLIENT_KEY)
            ->postJson('/api/tickets/prd-6526/triage')
            ->assertStatus(202)
            ->assertJsonPath('issue_id', 'PRD-6526');

        Process::assertRan(function (PendingProcess $process): bool {
            $command = $process->command;

            return $process->path === $this->workspace
                && $command[0] === '/usr/local/bin/claude'
                && $command[2] === '/fix-sprint-bugs PRD-6526 --ticket-file ai-runs/PRD-6526.json --json'
                && $this->optionValue($command, '--model') === 'sonnet'
                && !in_array('--dry-run', $command, true)
                && !in_array('Bash', $command, true)
                && in_array('Write', array_slice($command, array_search('--disallowedTools', $command, true)), true)
                && $process->environment['CLAUDE_CODE_OAUTH_TOKEN'] === 'test-claude-token';
        });

        $ticketFile = json_decode(File::get($this->workspace . '/ai-runs/PRD-6526.json'), true);
        $this->assertSame('PRD-6526', $ticketFile['idReadable']);

        $this->withHeader('X-Client-Key', self::CLIENT_KEY)
            ->getJson('/api/tickets/PRD-6526/triage')
            ->assertOk()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('verdict', 'blocked')
            ->assertJsonPath('confidence', 'low')
            ->assertJsonPath('analysis.summary', 'Image-only messages do not open the conversation.')
            ->assertJsonPath('model', 'claude-sonnet-5-5')
            ->assertJsonPath('cost_usd', 0.2425)
            ->assertJsonPath('num_turns', 9)
            ->assertJsonMissingPath('session_id');

        $this->assertSame('session-123', TicketTriage::first()->session_id);
    }

    public function test_fixable_verdict_with_a_failed_gate_is_stored_as_blocked(): void
    {
        $this->fakeYouTrack();
        $analysis = $this->analysis('fixable', 'medium');
        $analysis['gates']['no_shared_behaviour_change'] = false;
        Process::fake(['*' => Process::result($this->claudeOutput($analysis))]);

        $this->withHeader('X-Client-Key', self::CLIENT_KEY)->postJson('/api/tickets/PRD-6526/triage');

        $triage = TicketTriage::first();
        $this->assertSame('blocked', $triage->verdict);
        $this->assertTrue($triage->analysis['verdict_overridden']);
    }

    public function test_claude_auth_error_marks_the_triage_failed(): void
    {
        $this->fakeYouTrack();
        Process::fake(['*' => Process::result(json_encode([
            'is_error' => true,
            'result' => 'Failed to authenticate: OAuth session expired and could not be refreshed',
        ]), '', 1)]);

        $this->withHeader('X-Client-Key', self::CLIENT_KEY)->postJson('/api/tickets/PRD-6526/triage');

        $this->withHeader('X-Client-Key', self::CLIENT_KEY)
            ->getJson('/api/tickets/PRD-6526/triage')
            ->assertJsonPath('status', 'failed')
            ->assertJsonPath('error', 'Claude run failed: Failed to authenticate: OAuth session expired and could not be refreshed');
    }

    public function test_missing_claude_token_fails_before_fetching_the_ticket(): void
    {
        config(['tickets.triage_claude_token' => null]);
        Process::fake();

        $this->withHeader('X-Client-Key', self::CLIENT_KEY)->postJson('/api/tickets/PRD-6526/triage');

        $this->assertSame('failed', TicketTriage::first()->status);
        $this->assertStringContainsString('CLAUDE_CODE_OAUTH_TOKEN', TicketTriage::first()->error);
        Process::assertNothingRan();
        Http::assertNothingSent();
    }

    public function test_triage_in_progress_is_reused_instead_of_starting_another(): void
    {
        Queue::fake();
        $running = TicketTriage::create(['issue_id' => 'PRD-6526', 'status' => TicketTriage::STATUS_RUNNING]);

        $this->withHeader('X-Client-Key', self::CLIENT_KEY)
            ->postJson('/api/tickets/PRD-6526/triage')
            ->assertStatus(202)
            ->assertJsonPath('id', $running->id);

        Queue::assertNothingPushed();
        $this->assertDatabaseCount('ticket_triages', 1);
    }

    public function test_a_finished_triage_can_be_rerun(): void
    {
        Queue::fake();
        TicketTriage::create(['issue_id' => 'PRD-6526', 'status' => TicketTriage::STATUS_COMPLETED]);

        $this->withHeader('X-Client-Key', self::CLIENT_KEY)->postJson('/api/tickets/PRD-6526/triage');

        Queue::assertPushed(RunTicketTriageJob::class);
        $this->assertDatabaseCount('ticket_triages', 2);
    }

    public function test_show_returns_404_without_a_triage(): void
    {
        $this->withHeader('X-Client-Key', self::CLIENT_KEY)
            ->getJson('/api/tickets/PRD-6526/triage')
            ->assertNotFound();
    }

    private function fakeYouTrack(): void
    {
        Http::fake([
            'https://youtrack.test/api/issues/PRD-6526*' => Http::response([
                'idReadable' => 'PRD-6526',
                'summary' => 'Investigate WeChat image messages not opening conversations',
                'description' => 'Image messages are not marked as Open.',
                'comments' => [],
                'customFields' => [],
                'links' => [],
            ]),
        ]);
    }

    private function claudeOutput(array $analysis): string
    {
        return json_encode([
            'type' => 'result',
            'is_error' => false,
            'session_id' => 'session-123',
            'total_cost_usd' => 0.2425,
            'duration_ms' => 18886,
            'num_turns' => 9,
            'modelUsage' => ['claude-sonnet-5-5' => ['costUSD' => 0.2425]],
            'result' => "Final report.\n\n```json\n" . json_encode([$analysis]) . "\n```",
        ]);
    }

    private function analysis(string $verdict, string $confidence): array
    {
        return [
            'ticket' => 'PRD-6526',
            'summary' => 'Image-only messages do not open the conversation.',
            'gates' => [
                'root_cause_pinned' => true,
                'fix_is_local' => true,
                'no_shared_behaviour_change' => true,
                'callers_checked' => true,
                'no_product_decision_needed' => true,
            ],
            'verdict' => $verdict,
            'confidence' => $confidence,
            'planned_fix' => $verdict === 'fixable' ? 'Change the condition' : null,
            'open_questions' => [],
        ];
    }

    private function optionValue(array $command, string $option): ?string
    {
        $index = array_search($option, $command, true);

        return $index === false ? null : ($command[$index + 1] ?? null);
    }
}
