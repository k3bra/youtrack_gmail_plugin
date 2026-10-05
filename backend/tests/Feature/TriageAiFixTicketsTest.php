<?php

namespace Tests\Feature;

use App\Jobs\RunTicketTriageJob;
use App\Models\TicketTriage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TriageAiFixTicketsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Queue::fake();

        config([
            'tickets.youtrack_base_url' => 'https://youtrack.test',
            'tickets.youtrack_token' => 'test-youtrack-token',
            'tickets.triage_auto_enabled' => true,
            'tickets.triage_auto_query' => 'project: PRD tag: ai-fix #Unresolved',
            'tickets.triage_auto_max' => 3,
        ]);
    }

    public function test_triages_each_tagged_ticket_that_was_never_triaged(): void
    {
        $this->fakeTagged(['PRD-1', 'PRD-2']);

        $this->artisan('tickets:triage-ai-fix')
            ->expectsOutput('started: PRD-1, PRD-2')
            ->assertSuccessful();

        Queue::assertPushed(RunTicketTriageJob::class, 2);
        $this->assertSame(['ai-fix', 'ai-fix'], TicketTriage::orderBy('id')->pluck('trigger')->all());

        Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://youtrack.test/api/issues?')
            && $request['query'] === 'project: PRD tag: ai-fix #Unresolved');
    }

    public function test_tickets_with_any_previous_triage_are_not_triaged_again(): void
    {
        $this->fakeTagged(['PRD-1', 'PRD-2', 'PRD-3']);
        TicketTriage::create(['issue_id' => 'PRD-1', 'trigger' => 'manual', 'status' => 'completed']);
        TicketTriage::create(['issue_id' => 'PRD-2', 'trigger' => 'ai-fix', 'status' => 'failed']);

        $this->artisan('tickets:triage-ai-fix')
            ->expectsOutput('started: PRD-3')
            ->expectsOutput('already triaged: PRD-1, PRD-2')
            ->assertSuccessful();

        Queue::assertPushed(RunTicketTriageJob::class, 1);
    }

    public function test_starts_at_most_the_configured_number_per_check(): void
    {
        config(['tickets.triage_auto_max' => 2]);
        $this->fakeTagged(['PRD-1', 'PRD-2', 'PRD-3', 'PRD-4']);

        $this->artisan('tickets:triage-ai-fix')
            ->expectsOutput('started: PRD-1, PRD-2')
            ->expectsOutput('next check: PRD-3, PRD-4 (over TRIAGE_AUTO_MAX)')
            ->assertSuccessful();

        Queue::assertPushed(RunTicketTriageJob::class, 2);
    }

    public function test_does_nothing_when_disabled(): void
    {
        config(['tickets.triage_auto_enabled' => false]);

        $this->artisan('tickets:triage-ai-fix')->assertSuccessful();

        Http::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_youtrack_error_fails_the_command_without_starting_anything(): void
    {
        Http::fake(['https://youtrack.test/api/issues*' => Http::response(['error' => 'Unauthorized'], 401)]);

        $this->artisan('tickets:triage-ai-fix')->assertFailed();

        Queue::assertNothingPushed();
    }

    public function test_command_is_scheduled_every_ten_minutes(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('tickets:triage-ai-fix')
            ->assertSuccessful();
    }

    private function fakeTagged(array $ids): void
    {
        Http::fake([
            'https://youtrack.test/api/issues*' => Http::response(array_map(fn ($id) => ['idReadable' => $id], $ids)),
        ]);
    }
}
