<?php

namespace Tests\Feature;

use App\Jobs\RunTicketFixJob;
use App\Models\TicketFix;
use App\Models\TicketTriage;
use App\Services\Fix\TicketFixService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TicketFixTest extends TestCase
{
    use RefreshDatabase;

    private const ISSUE = 'PRD-7001';
    private const BRANCH = 'feature/PRD-7001_Send_button_hidden_on_iOS_Safari';

    private string $workspace;

    /** @var list<array{cwd: ?string, command: array}> */
    private array $ran = [];

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        $this->workspace = sys_get_temp_dir() . '/fix-test-' . uniqid();
        File::makeDirectory($this->workspace . '/.claude/skills/fix-sprint-bugs', 0755, true);
        File::makeDirectory($this->workspace . '/ai-runs');
        File::put($this->workspace . '/ai-runs/PRD-7001.json', json_encode(['summary' => '[Widget] Send button hidden on iOS Safari']));
        File::put($this->workspace . '/bitbucket.json', json_encode(['auth' => ['username' => 'bb-user', 'appPassword' => 'bb-pass']]));

        config([
            'tickets.youtrack_base_url' => 'https://youtrack.test',
            'tickets.triage_workspace_path' => $this->workspace,
            'tickets.triage_claude_bin' => 'claude',
            'tickets.triage_claude_token' => 'test-claude-token',
            'tickets.fix_model' => 'opus',
            'tickets.fix_base_branch' => 'develop',
            'tickets.fix_max_changed_lines' => 300,
            'tickets.bitbucket_config' => $this->workspace . '/bitbucket.json',
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->workspace);

        parent::tearDown();
    }

    public function test_accept_queues_a_fix_for_a_fixable_triage(): void
    {
        Queue::fake();
        $this->triage();

        $this->postJson('/plugin-tickets/triage/prd-7001/accept')
            ->assertStatus(202)
            ->assertJsonPath('fix.status', 'queued');

        Queue::assertPushed(RunTicketFixJob::class);
    }

    public function test_accept_is_also_available_on_the_api(): void
    {
        Queue::fake();
        config(['tickets.client_key' => 'key']);
        $this->triage();

        $this->withHeader('X-Client-Key', 'key')->postJson('/api/tickets/PRD-7001/triage/accept')->assertStatus(202);
        $this->flushHeaders()->postJson('/api/tickets/PRD-7001/triage/accept')->assertUnauthorized();
    }

    #[DataProvider('refusals')]
    public function test_accept_is_refused_unless_the_triage_is_a_finished_fixable_one(array $overrides, string $error): void
    {
        Queue::fake();
        $this->triage($overrides);

        $this->postJson('/plugin-tickets/triage/PRD-7001/accept')
            ->assertStatus(422)
            ->assertJsonPath('error', fn ($message) => str_contains($message, $error));

        Queue::assertNothingPushed();
    }

    public static function refusals(): array
    {
        return [
            'still running' => [['status' => 'running'], 'not finished'],
            'blocked' => [['verdict' => 'blocked'], 'Only a fixable triage'],
            'no session' => [['session_id' => null], 'no Claude session'],
            'two repos' => [['analysis' => ['repos' => ['console', 'widget']]], 'exactly one'],
            'unknown repo' => [['analysis' => ['repos' => ['dev-workspace']]], 'exactly one'],
        ];
    }

    public function test_accept_without_triage_is_refused(): void
    {
        $this->postJson('/plugin-tickets/triage/PRD-7001/accept')->assertStatus(422);
    }

    public function test_a_running_or_finished_fix_is_not_started_twice(): void
    {
        Queue::fake();
        $triage = $this->triage();
        $triage->latestFix()->create(['issue_id' => self::ISSUE, 'status' => 'completed', 'pr_url' => 'https://bb/pr/1']);

        $this->postJson('/plugin-tickets/triage/PRD-7001/accept')
            ->assertStatus(202)
            ->assertJsonPath('fix.pr_url', 'https://bb/pr/1');

        Queue::assertNothingPushed();
        $this->assertSame(1, TicketFix::count());
    }

    public function test_a_failed_fix_can_be_retried(): void
    {
        Queue::fake();
        $triage = $this->triage();
        $triage->latestFix()->create(['issue_id' => self::ISSUE, 'status' => 'failed']);

        $this->postJson('/plugin-tickets/triage/PRD-7001/accept')->assertStatus(202)->assertJsonPath('fix.status', 'queued');

        Queue::assertPushed(RunTicketFixJob::class);
    }

    public function test_fix_writes_in_a_worktree_then_commits_pushes_and_opens_a_draft_pr(): void
    {
        $this->fakeProcesses($this->fixAnswer());
        Http::fake(['https://api.bitbucket.org/*' => Http::response(['links' => ['html' => ['href' => 'https://bitbucket.org/wonderoute/widget/pull-requests/42']]], 201)]);

        $this->runFix();

        $fix = TicketFix::first();
        $this->assertSame('completed', $fix->status, (string) $fix->error);
        $this->assertSame('widget', $fix->repo);
        $this->assertSame(self::BRANCH, $fix->branch);
        $this->assertSame('abc1234def', $fix->commit_sha);
        $this->assertSame('https://bitbucket.org/wonderoute/widget/pull-requests/42', $fix->pr_url);
        $this->assertSame(['src/components/Composer.vue'], $fix->result['files_committed']);
        $this->assertSame(3.1, $fix->cost_usd);

        $worktree = $this->workspace . '/.ai-fix-worktrees/widget-PRD-7001';
        $this->assertRan(['git', 'worktree', 'add', $worktree, '-b', self::BRANCH, 'origin/develop'], $this->workspace . '/widget');

        $claude = $this->claudeCall();
        $this->assertSame($this->workspace, $claude['cwd']);
        $this->assertSame('continue with PRD-7001 --worktree ' . $worktree . ' --branch ' . self::BRANCH . ' --json', $claude['command'][2]);
        $this->assertSame('session-abc', $this->option($claude['command'], '--resume'));
        $this->assertSame('opus', $this->option($claude['command'], '--model'));
        $this->assertContains('Edit(/' . $worktree . '/**)', $claude['command']);
        $this->assertNotContains('Edit', $claude['command']);
        $this->assertContains('Bash(git -C * push *)', $claude['command']);

        $commit = $this->findRun(['git', 'commit']);
        $this->assertSame($worktree, $commit['cwd']);
        $this->assertStringStartsWith('PRD-7001 Keep the send button visible above the iOS keyboard', $commit['command'][4]);
        $this->assertRan(['git', 'push', '--quiet', '-u', 'origin', self::BRANCH], $worktree);
        $this->assertRan(['git', 'worktree', 'remove', '--force', $worktree], $this->workspace . '/widget');

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://api.bitbucket.org/2.0/repositories/wonderoute/widget/pullrequests'
                && $request['draft'] === true
                && $request['source']['branch']['name'] === self::BRANCH
                && $request['destination']['branch']['name'] === 'develop'
                && $request['title'] === 'PRD-7001: Send button hidden on iOS Safari'
                && str_contains($request['description'], '[PRD-7001](https://youtrack.test/issue/PRD-7001)')
                && str_contains($request['description'], 'do not merge as-is')
                && $request->hasHeader('Authorization', 'Basic ' . base64_encode('bb-user:bb-pass'));
        });
    }

    public function test_blocked_fix_pushes_nothing_and_removes_the_worktree(): void
    {
        $this->fakeProcesses(['ticket' => self::ISSUE, 'status' => 'blocked', 'blocked_reason' => 'Needs a change in the shared Message component.']);

        $this->runFix();

        $fix = TicketFix::first();
        $this->assertSame('blocked', $fix->status);
        $this->assertSame('Needs a change in the shared Message component.', $fix->error);
        $this->assertNull($this->findRun(['git', 'commit']));
        $this->assertNull($this->findRun(['git', 'push']));
        $this->assertNotNull($this->findRun(['git', 'worktree', 'remove']));
        Http::assertNothingSent();
    }

    public function test_existing_remote_branch_stops_before_claude_runs(): void
    {
        $this->fakeProcesses($this->fixAnswer(), lsRemote: 'abc refs/heads/' . self::BRANCH);

        $this->runFix();

        $fix = TicketFix::first();
        $this->assertSame('failed', $fix->status);
        $this->assertStringContainsString('already exists on origin', $fix->error);
        $this->assertNull($this->claudeCall());
    }

    public function test_oversized_fix_is_not_pushed(): void
    {
        config(['tickets.fix_max_changed_lines' => 10]);
        $this->fakeProcesses($this->fixAnswer(), numstat: "40\t5\tsrc/components/Composer.vue");

        $this->runFix();

        $fix = TicketFix::first();
        $this->assertSame('failed', $fix->status);
        $this->assertStringContainsString('45 lines (limit 10)', $fix->error);
        $this->assertNull($this->findRun(['git', 'push']));
        $this->assertSame(self::BRANCH, $fix->branch);
    }

    public function test_fix_with_no_changes_is_not_pushed(): void
    {
        $this->fakeProcesses($this->fixAnswer(), files: '');

        $this->runFix();

        $this->assertStringContainsString('changed no files', TicketFix::first()->error);
        $this->assertNull($this->findRun(['git', 'commit']));
    }

    public function test_pr_failure_after_push_says_the_branch_is_on_origin(): void
    {
        $this->fakeProcesses($this->fixAnswer());
        Http::fake(['https://api.bitbucket.org/*' => Http::response(['error' => ['message' => 'Your credentials lack one or more required privilege scopes.']], 403)]);

        $this->runFix();

        $fix = TicketFix::first();
        $this->assertSame('failed', $fix->status);
        $this->assertStringContainsString(self::BRANCH . ' was pushed, but the draft PR could not be opened', $fix->error);
        $this->assertStringContainsString('privilege scopes', $fix->error);
        $this->assertSame('abc1234def', $fix->commit_sha);
    }

    public function test_slug_follows_the_skill_branch_rule(): void
    {
        $this->assertSame('Send_button_hidden_on_iOS_Safari', TicketFixService::slug('[Widget] Send button hidden on iOS Safari'));
        $this->assertSame('Investigate_WeChat_image_messages_not_opening_conv', TicketFixService::slug('Investigate WeChat image messages not opening conversations for Heritage Collection (ID 1146)'));
        $this->assertSame('fix', TicketFixService::slug('[Only a prefix]'));
    }

    public function test_page_shows_the_fix_with_its_pr_link(): void
    {
        $triage = $this->triage();
        $triage->latestFix()->create(['issue_id' => self::ISSUE, 'status' => 'completed', 'pr_url' => 'https://bitbucket.org/wonderoute/widget/pull-requests/42']);
        TicketTriage::whereKey($triage->id)->update(['trigger' => 'ai-fix']);

        $this->get('/plugin-tickets')
            ->assertOk()
            ->assertSee('pull-requests\/42', false);
    }

    private function triage(array $overrides = []): TicketTriage
    {
        return TicketTriage::create(array_replace_recursive([
            'issue_id' => self::ISSUE,
            'status' => 'completed',
            'verdict' => 'fixable',
            'confidence' => 'high',
            'session_id' => 'session-abc',
            'analysis' => ['repos' => ['widget'], 'summary' => 'Send button hidden behind the iOS keyboard.'],
        ], $overrides));
    }

    private function runFix(): void
    {
        Queue::fake();
        $this->triage();
        $this->postJson('/plugin-tickets/triage/PRD-7001/accept')->assertStatus(202);

        (new RunTicketFixJob(TicketFix::first()->id))->handle(app(TicketFixService::class));
    }

    private function fixAnswer(): array
    {
        return [
            'ticket' => self::ISSUE,
            'status' => 'implemented',
            'summary' => 'Pin the composer above the keyboard with visualViewport.',
            'files_changed' => ['src/components/Composer.vue'],
            'verification' => 'tests not run (no deps in worktree)',
            'commit_message' => 'Keep the send button visible above the iOS keyboard',
            'pr_title' => 'Send button hidden on iOS Safari',
            'pr_description' => '**Root cause:** … **Fix:** …',
            'blocked_reason' => null,
        ];
    }

    private function fakeProcesses(array $answer, string $lsRemote = '', string $files = 'src/components/Composer.vue', string $numstat = "6\t2\tsrc/components/Composer.vue"): void
    {
        Process::fake(function (PendingProcess $process) use ($answer, $lsRemote, $files, $numstat) {
            $command = $process->command;
            $this->ran[] = ['cwd' => $process->path, 'command' => $command];

            if ($command[0] === 'claude') {
                return Process::result(json_encode([
                    'is_error' => false,
                    'session_id' => 'session-abc',
                    'total_cost_usd' => 3.1,
                    'duration_ms' => 240000,
                    'num_turns' => 31,
                    'modelUsage' => ['claude-opus-5-5' => []],
                    'result' => "```json\n" . json_encode($answer) . "\n```",
                ]));
            }

            return match (implode(' ', array_slice($command, 1, 2))) {
                'ls-remote --heads' => Process::result($lsRemote),
                'diff --cached' => Process::result(in_array('--numstat', $command, true) ? $numstat : $files),
                'rev-parse HEAD' => Process::result("abc1234def\n"),
                'remote get-url' => Process::result("git@bitbucket.org:wonderoute/widget.git\n"),
                default => Process::result(),
            };
        });
    }

    private function claudeCall(): ?array
    {
        return collect($this->ran)->first(fn ($run) => $run['command'][0] === 'claude');
    }

    private function findRun(array $prefix): ?array
    {
        return collect($this->ran)->first(fn ($run) => array_slice($run['command'], 0, count($prefix)) === $prefix);
    }

    private function assertRan(array $command, string $cwd): void
    {
        $this->assertTrue(
            collect($this->ran)->contains(fn ($run) => $run['command'] === $command && $run['cwd'] === $cwd),
            'Expected to run [' . implode(' ', $command) . "] in {$cwd}"
        );
    }

    private function option(array $command, string $name): ?string
    {
        $index = array_search($name, $command, true);

        return $index === false ? null : ($command[$index + 1] ?? null);
    }
}
