<?php

namespace Tests\Feature;

use App\Jobs\RunTicketTriageJob;
use App\Models\TicketRequest;
use App\Models\TicketTriage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PluginTicketTriageTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_shows_ai_button_only_for_rows_with_an_issue(): void
    {
        $this->ticketRequest('PRD-6526');
        $this->ticketRequest(null, 'failed');

        $this->get('/plugin-tickets')
            ->assertOk()
            ->assertSee('data-issue="PRD-6526"', false)
            ->assertSee('name="csrf-token"', false)
            ->assertSee('AI triage for PRD-6526');

        $this->assertSame(1, substr_count($this->get('/plugin-tickets')->getContent(), 'data-triage-button data-row='));
    }

    public function test_page_embeds_the_latest_triage_per_issue(): void
    {
        $this->ticketRequest('PRD-6526');
        TicketTriage::create(['issue_id' => 'PRD-6526', 'status' => 'failed', 'error' => 'old run']);
        TicketTriage::create(['issue_id' => 'PRD-6526', 'status' => 'completed', 'verdict' => 'blocked', 'session_id' => 'secret-session']);

        $html = $this->get('/plugin-tickets')->getContent();

        $this->assertStringContainsString('"verdict":"blocked"', $html);
        $this->assertStringNotContainsString('old run', $html);
        $this->assertStringNotContainsString('secret-session', $html);
    }

    public function test_starting_a_triage_from_the_page_queues_the_job(): void
    {
        Queue::fake();
        $this->ticketRequest('PRD-6526');

        $this->postJson('/plugin-tickets/triage/prd-6526')
            ->assertStatus(202)
            ->assertJsonPath('issue_id', 'PRD-6526')
            ->assertJsonPath('trigger', 'manual')
            ->assertJsonPath('status', 'queued');

        Queue::assertPushed(RunTicketTriageJob::class);
    }

    public function test_malformed_issue_id_is_rejected(): void
    {
        Queue::fake();

        $this->postJson('/plugin-tickets/triage/' . rawurlencode('PRD-1 --model opus'))->assertNotFound();
        $this->postJson('/plugin-tickets/triage/not-an-issue')->assertNotFound();

        Queue::assertNothingPushed();
    }

    public function test_page_lists_ai_fix_triages_without_a_gmail_row(): void
    {
        $this->ticketRequest('PRD-6526');
        TicketTriage::create(['issue_id' => 'PRD-6526', 'trigger' => 'ai-fix', 'status' => 'completed']);
        TicketTriage::create([
            'issue_id' => 'PRD-7001',
            'trigger' => 'ai-fix',
            'status' => 'completed',
            'verdict' => 'fixable',
            'analysis' => ['summary' => 'Widget button misaligned on mobile.'],
        ]);
        TicketTriage::create(['issue_id' => 'PRD-7002', 'trigger' => 'manual', 'status' => 'completed']);

        $html = $this->get('/plugin-tickets')->assertOk()->getContent();

        $this->assertStringContainsString('--tag=ai-fix', $html);
        $this->assertStringContainsString('Widget button misaligned on mobile.', $html);
        $this->assertSame(1, substr_count($html, 'data-issue="PRD-6526"'));
        $this->assertStringNotContainsString('data-issue="PRD-7002"', $html);
    }

    public function test_ai_fix_section_is_hidden_when_empty(): void
    {
        $this->ticketRequest('PRD-6526');

        $this->get('/plugin-tickets')->assertOk()->assertDontSee('--tag=ai-fix');
    }

    public function test_show_returns_the_triage_for_the_row(): void
    {
        TicketTriage::create(['issue_id' => 'PRD-6526', 'status' => 'running']);

        $this->getJson('/plugin-tickets/triage/PRD-6526')
            ->assertOk()
            ->assertJsonPath('status', 'running');
    }

    private function ticketRequest(?string $issueId, string $status = 'success'): TicketRequest
    {
        return TicketRequest::create([
            'request_type' => 'bug',
            'email_subject' => 'Heritage Collection - Message was not marked as "Open"',
            'email_from' => 'support@example.com',
            'email_body' => 'body',
            'ai_summary' => 'Investigate WeChat image messages not opening conversations',
            'youtrack_issue_id' => $issueId,
            'status' => $status,
        ]);
    }
}
