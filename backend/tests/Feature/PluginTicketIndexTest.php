<?php

namespace Tests\Feature;

use App\Models\TicketRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PluginTicketIndexTest extends TestCase
{
    use RefreshDatabase;

    private function createTicketRequest(array $attributes = []): TicketRequest
    {
        return TicketRequest::create(array_merge([
            'request_type' => 'task',
            'email_subject' => 'Booking widget broken',
            'email_from' => 'Jane Doe <jane@example.com>',
            'email_body' => 'Secret body text',
            'ai_summary' => 'Fix booking widget',
            'ai_description' => 'Secret description',
            'youtrack_issue_id' => 'PRD-101',
            'status' => 'success',
        ], $attributes));
    }

    public function test_guest_can_see_plugin_tickets(): void
    {
        config(['tickets.youtrack_base_url' => 'https://youtrack.example.com/']);
        $this->createTicketRequest();

        $this->get('/plugin-tickets')
            ->assertOk()
            ->assertSee('Fix booking widget')
            ->assertSee('https://youtrack.example.com/issue/PRD-101', false)
            ->assertSee('jane@example.com')
            ->assertDontSee('Secret body text')
            ->assertDontSee('Secret description');
    }

    public function test_failed_requests_show_their_error(): void
    {
        $this->createTicketRequest([
            'youtrack_issue_id' => null,
            'ai_summary' => null,
            'status' => 'failed',
            'error_message' => 'Priority must be one of: Normal.',
        ]);

        $this->get('/plugin-tickets')
            ->assertOk()
            ->assertSee('Booking widget broken')
            ->assertSee('Priority must be one of: Normal.');
    }

    public function test_tickets_can_be_filtered_by_status(): void
    {
        $this->createTicketRequest(['ai_summary' => 'Created ticket']);
        $this->createTicketRequest([
            'ai_summary' => 'Broken ticket',
            'youtrack_issue_id' => null,
            'status' => 'failed',
            'error_message' => 'YouTrack is down',
        ]);

        $this->get('/plugin-tickets?status=failed')
            ->assertOk()
            ->assertSee('Broken ticket')
            ->assertDontSee('Created ticket');

        $this->get('/plugin-tickets?status=bogus')
            ->assertOk()
            ->assertSee('Broken ticket')
            ->assertSee('Created ticket');
    }

    public function test_thread_links_must_use_https(): void
    {
        $this->createTicketRequest(['email_thread_url' => 'javascript:alert(1)']);

        $this->get('/plugin-tickets')
            ->assertOk()
            ->assertDontSee('javascript:alert(1)', false);
    }

    private function fakeYouTrack(int $status): void
    {
        config([
            'tickets.youtrack_base_url' => 'https://youtrack.test',
            'tickets.youtrack_token' => 'test-youtrack-token',
        ]);

        Http::fake([
            'youtrack.test/api/issues/*' => Http::response(['idReadable' => 'PRD-101', 'customFields' => []], $status),
        ]);
    }

    public function test_ticket_deleted_in_youtrack_can_be_removed(): void
    {
        $this->fakeYouTrack(404);
        $ticket = $this->createTicketRequest();

        $this->from('/plugin-tickets?status=success')
            ->delete("/plugin-tickets/{$ticket->id}")
            ->assertRedirect('/plugin-tickets?status=success')
            ->assertSessionHas('success', 'Removed PRD-101 from the log.');

        $this->assertModelMissing($ticket);
    }

    public function test_ticket_still_in_youtrack_is_kept(): void
    {
        $this->fakeYouTrack(200);
        $ticket = $this->createTicketRequest();

        $this->from('/plugin-tickets')
            ->delete("/plugin-tickets/{$ticket->id}")
            ->assertRedirect('/plugin-tickets')
            ->assertSessionHas('error', 'PRD-101 still exists in YouTrack. Delete it there first.');

        $this->assertModelExists($ticket);
    }

    public function test_ticket_is_kept_when_youtrack_is_unreachable(): void
    {
        $this->fakeYouTrack(500);
        $ticket = $this->createTicketRequest();

        $this->from('/plugin-tickets')
            ->delete("/plugin-tickets/{$ticket->id}")
            ->assertSessionHas('error');

        $this->assertModelExists($ticket);
    }

    public function test_failed_request_is_removed_without_checking_youtrack(): void
    {
        Http::fake();
        $ticket = $this->createTicketRequest([
            'youtrack_issue_id' => null,
            'status' => 'failed',
            'error_message' => 'YouTrack is down',
        ]);

        $this->from('/plugin-tickets')
            ->delete("/plugin-tickets/{$ticket->id}")
            ->assertSessionHas('success');

        $this->assertModelMissing($ticket);
        Http::assertNothingSent();
    }

    public function test_flash_message_is_shown_on_the_page(): void
    {
        $this->withSession(['error' => 'PRD-9 still exists in YouTrack. Delete it there first.'])
            ->get('/plugin-tickets')
            ->assertSee('PRD-9 still exists in YouTrack. Delete it there first.');
    }
}
