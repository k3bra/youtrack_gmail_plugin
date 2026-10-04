<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TicketFromEmailTest extends TestCase
{
    use RefreshDatabase;

    private const CLIENT_KEY = 'test-client-key';

    private const TASK_DESCRIPTION = "## Context\nReported by support for Hesperia (1512).\n\n"
        . "## Problem\nReviewPro links redirect to an error page.\n\n"
        . "## Evidence\n- https://console.hijiffy.com/inbox/all/abc\n\n"
        . "## Expected Behavior\nLinks open the ReviewPro survey.\n\n"
        . "## Acceptance Criteria\n- [ ] ReviewPro links open the survey.";

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        config([
            'tickets.client_key' => self::CLIENT_KEY,
            'tickets.openai_key' => 'test-openai-key',
            'tickets.openai_model' => 'test-model',
            'tickets.youtrack_base_url' => 'https://youtrack.test',
            'tickets.youtrack_token' => 'test-youtrack-token',
            'tickets.youtrack_project_id' => 'PRD',
        ]);
    }

    public function test_preview_returns_ai_draft_without_creating_issue(): void
    {
        $this->fakeOpenAi([
            'summary' => 'Fix ReviewPro links for Hesperia (1512)',
            'description' => self::TASK_DESCRIPTION,
            'labels' => ['ReviewPro', 'campaigns', 'reviewpro', 'links'],
        ]);

        $response = $this->withHeader('X-Client-Key', self::CLIENT_KEY)
            ->postJson('/api/tickets/preview', $this->emailPayload());

        $response->assertOk()
            ->assertJson([
                'summary' => 'Fix ReviewPro links for Hesperia (1512)',
                'description' => self::TASK_DESCRIPTION,
                'labels' => ['reviewpro', 'campaigns', 'links'],
            ]);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://api.openai.com/v1/chat/completions'
                && $request['model'] === 'test-model'
                && $request['response_format']['type'] === 'json_schema'
                && $request['response_format']['json_schema']['strict'] === true;
        });
        Http::assertNotSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://youtrack.test'));
        $this->assertDatabaseCount('ticket_requests', 0);
    }

    public function test_preview_renders_sectioned_description_object_as_markdown(): void
    {
        $this->fakeOpenAi([
            'summary' => 'Fix ReviewPro links for Hesperia (1512)',
            'description' => [
                'Context' => 'Reported by support.',
                'Problem' => 'Links redirect to an error page.',
                'Evidence' => ['- link one', '- link two'],
                'Expected Behavior' => 'Links work.',
                'Acceptance Criteria' => '- [ ] Links work.',
            ],
            'labels' => [],
        ]);

        $response = $this->withHeader('X-Client-Key', self::CLIENT_KEY)
            ->postJson('/api/tickets/preview', $this->emailPayload());

        $response->assertOk();
        $description = $response->json('description');
        $this->assertStringStartsWith("## Context\nReported by support.", $description);
        $this->assertStringContainsString("## Evidence\n- link one\n- link two", $description);
        $this->assertStringNotContainsString('Thank you', $description);
    }

    public function test_preview_fails_instead_of_falling_back_to_raw_email(): void
    {
        $this->fakeOpenAi([
            'summary' => 'Fix ReviewPro links',
            'description' => 'Just some text without the required sections.',
            'labels' => [],
        ]);

        $response = $this->withHeader('X-Client-Key', self::CLIENT_KEY)
            ->postJson('/api/tickets/preview', $this->emailPayload());

        $response->assertStatus(502);
        $this->assertStringContainsString('missing sections', $response->json('error'));
    }

    public function test_preview_requires_client_key(): void
    {
        Http::fake();

        $this->postJson('/api/tickets/preview', $this->emailPayload())->assertUnauthorized();

        Http::assertNothingSent();
    }

    public function test_reviewed_draft_is_created_with_labels_and_source_email(): void
    {
        Http::fake([
            'youtrack.test/*' => Http::response(['idReadable' => 'PRD-1']),
        ]);

        $response = $this->withHeader('X-Client-Key', self::CLIENT_KEY)
            ->postJson('/api/tickets/from-email', [
                'type' => 'task',
                'mode' => 'manual',
                'summary' => 'Edited summary',
                'description' => self::TASK_DESCRIPTION,
                'labels' => ['reviewpro'],
                'email' => $this->emailPayload()['email'],
            ]);

        $response->assertOk()->assertJson([
            'issueId' => 'PRD-1',
            'url' => 'https://youtrack.test/issue/PRD-1',
        ]);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://youtrack.test/api/issues?fields=idReadable'
                && $request['summary'] === 'Edited summary'
                && $request['description'] === self::TASK_DESCRIPTION
                && $request['labels'] === [['name' => 'reviewpro']];
        });

        $this->assertDatabaseHas('ticket_requests', [
            'status' => 'success',
            'youtrack_issue_id' => 'PRD-1',
            'email_subject' => 'Hesperia (1512) - ReviewPro links not working',
            'email_from' => 'Pedro Batista',
            'ai_summary' => 'Edited summary',
        ]);
    }

    private function fakeOpenAi(array $content): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode($content)]],
                ],
            ]),
        ]);
    }

    private function emailPayload(): array
    {
        return [
            'type' => 'task',
            'email' => [
                'subject' => 'Hesperia (1512) - ReviewPro links not working',
                'from' => 'Pedro Batista',
                'body' => "Hi!\n\nThe ReviewPro links redirect to an error page.\n\nThank you,\nPedro",
                'threadUrl' => 'https://mail.google.com/mail/u/0/#inbox/abc',
            ],
        ];
    }
}
