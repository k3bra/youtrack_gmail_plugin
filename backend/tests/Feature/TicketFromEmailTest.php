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
            return $request->url() === 'https://youtrack.test/api/issues?fields=id,idReadable'
                && $request['summary'] === 'Edited summary'
                && $request['description'] === self::TASK_DESCRIPTION
                && !isset($request['labels'])
                && !isset($request['tags']);
        });

        $this->assertDatabaseHas('ticket_requests', [
            'status' => 'success',
            'youtrack_issue_id' => 'PRD-1',
            'email_subject' => 'Hesperia (1512) - ReviewPro links not working',
            'email_from' => 'Pedro Batista',
            'ai_summary' => 'Edited summary',
        ]);
    }

    public function test_priorities_endpoint_returns_active_youtrack_values_in_order(): void
    {
        $this->fakeYouTrack();

        $this->withHeader('X-Client-Key', self::CLIENT_KEY)
            ->getJson('/api/tickets/priorities')
            ->assertOk()
            ->assertExactJson(['priorities' => ['Highest', 'High', 'Medium']]);
    }

    public function test_ticket_is_created_with_selected_priority(): void
    {
        $this->fakeYouTrack();

        $this->withHeader('X-Client-Key', self::CLIENT_KEY)
            ->postJson('/api/tickets/from-email', [
                'type' => 'task',
                'mode' => 'manual',
                'summary' => 'Fix ReviewPro links',
                'description' => self::TASK_DESCRIPTION,
                'priority' => 'High',
            ])
            ->assertOk()
            ->assertJson(['issueId' => 'PRD-1']);

        Http::assertSent(function (Request $request): bool {
            return str_starts_with($request->url(), 'https://youtrack.test/api/issues')
                && $request['customFields'] === [[
                    '$type' => 'SingleEnumIssueCustomField',
                    'name' => 'Priority',
                    'value' => ['$type' => 'EnumBundleElement', 'name' => 'High'],
                ]];
        });
    }

    public function test_ticket_without_priority_sends_no_custom_fields(): void
    {
        $this->fakeYouTrack();

        $this->withHeader('X-Client-Key', self::CLIENT_KEY)
            ->postJson('/api/tickets/from-email', [
                'type' => 'task',
                'mode' => 'manual',
                'summary' => 'Fix ReviewPro links',
                'description' => self::TASK_DESCRIPTION,
            ])
            ->assertOk();

        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/api/admin/'));
        Http::assertSent(function (Request $request): bool {
            return str_starts_with($request->url(), 'https://youtrack.test/api/issues')
                && !isset($request['customFields']);
        });
    }

    public function test_unknown_priority_is_rejected_before_creating_issue(): void
    {
        $this->fakeYouTrack();

        $response = $this->withHeader('X-Client-Key', self::CLIENT_KEY)
            ->postJson('/api/tickets/from-email', [
                'type' => 'task',
                'mode' => 'manual',
                'summary' => 'Fix ReviewPro links',
                'description' => self::TASK_DESCRIPTION,
                'priority' => 'Low',
            ]);

        $response->assertStatus(400)
            ->assertJson(['error' => 'Priority must be one of: Highest, High, Medium.']);
        Http::assertNotSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://youtrack.test/api/issues'));
    }

    public function test_sprint_options_return_current_sprint_and_latest_proposal(): void
    {
        $this->fakeYouTrack();

        $this->withHeader('X-Client-Key', self::CLIENT_KEY)
            ->getJson('/api/tickets/sprint-options')
            ->assertOk()
            ->assertExactJson([
                'current' => ['name' => 'Sprint 150', 'number' => 150],
                'proposal' => ['name' => 'proposal151', 'number' => 151],
            ]);
    }

    public function test_proposal_ticket_gets_latest_proposal_tag_and_is_not_added_to_sprint(): void
    {
        $this->fakeYouTrack();

        $this->withHeader('X-Client-Key', self::CLIENT_KEY)
            ->postJson('/api/tickets/from-email', $this->manualPayload(['sprint' => 'proposal', 'sprintNumber' => 151]))
            ->assertOk()
            ->assertJson(['issueId' => 'PRD-1', 'url' => 'https://youtrack.test/issue/PRD-1'])
            ->assertJsonMissingPath('warning');

        Http::assertSent(fn (Request $request): bool => $this->isCreateIssue($request)
            && $request['tags'] === [['id' => '6-151']]);
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/sprints/'));
    }

    public function test_current_sprint_ticket_reuses_existing_added_sprint_tag_and_joins_sprint(): void
    {
        $this->fakeYouTrack(existingAddedSprint150: true);

        $this->withHeader('X-Client-Key', self::CLIENT_KEY)
            ->postJson('/api/tickets/from-email', $this->manualPayload(['sprint' => 'current', 'sprintNumber' => 150]))
            ->assertOk()
            ->assertJsonMissingPath('warning');

        Http::assertSent(fn (Request $request): bool => $this->isCreateIssue($request)
            && $request['tags'] === [['id' => '6-a150']]);
        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST'
            && str_contains($request->url(), '/api/issueTags'));
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && str_starts_with($request->url(), 'https://youtrack.test/api/agiles/193-5/sprints/208-559/issues')
            && $request['id'] === '2-999');
    }

    public function test_current_sprint_ticket_creates_missing_added_sprint_tag_with_previous_visibility(): void
    {
        $this->fakeYouTrack();

        $this->withHeader('X-Client-Key', self::CLIENT_KEY)
            ->postJson('/api/tickets/from-email', $this->manualPayload(['sprint' => 'current', 'sprintNumber' => 150]))
            ->assertOk();

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && str_starts_with($request->url(), 'https://youtrack.test/api/issueTags')
            && $request['name'] === 'added-sprint150'
            && $request['visibleFor'] === ['id' => '3-7', '$type' => 'UserGroup']);
        Http::assertSent(fn (Request $request): bool => $this->isCreateIssue($request)
            && $request['tags'] === [['id' => '6-new']]);
    }

    public function test_ticket_is_still_created_with_warning_when_joining_sprint_fails(): void
    {
        $this->fakeYouTrack(existingAddedSprint150: true, sprintJoinFails: true);

        $this->withHeader('X-Client-Key', self::CLIENT_KEY)
            ->postJson('/api/tickets/from-email', $this->manualPayload(['sprint' => 'current', 'sprintNumber' => 150]))
            ->assertOk()
            ->assertJson([
                'issueId' => 'PRD-1',
                'warning' => 'Ticket created, but it could not be added to Sprint 150. Add it on the board manually.',
            ]);
    }

    public function test_stale_sprint_number_is_rejected_before_creating_issue(): void
    {
        $this->fakeYouTrack();

        $this->withHeader('X-Client-Key', self::CLIENT_KEY)
            ->postJson('/api/tickets/from-email', $this->manualPayload(['sprint' => 'current', 'sprintNumber' => 149]))
            ->assertStatus(400)
            ->assertJson(['error' => 'The current sprint is now 150. Reopen the ticket form and try again.']);

        Http::assertNotSent(fn (Request $request): bool => $this->isCreateIssue($request));
        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST'
            && str_contains($request->url(), '/api/issueTags'));
    }

    public function test_reply_message_mentions_current_sprint_and_greets_sender(): void
    {
        config(['tickets.reply_signature' => 'Eduardo']);
        $this->fakeYouTrack(existingAddedSprint150: true);

        $response = $this->withHeader('X-Client-Key', self::CLIENT_KEY)
            ->postJson('/api/tickets/from-email', $this->manualPayload([
                'sprint' => 'current',
                'sprintNumber' => 150,
                'senderName' => 'Pedro Batista',
            ]));

        $response->assertOk();
        $this->assertSame(
            "Hi Pedro,\n\n"
            . "Thanks for reporting this. We've created PRD-1 to track it, and we'll work on it in the current sprint (Sprint 150).\n\n"
            . "You can follow its progress in YouTrack: https://youtrack.test/issue/PRD-1\n\n"
            . "Best,\nEduardo",
            $response->json('replyMessage')
        );
    }

    public function test_reply_message_for_proposal_and_unknown_sender(): void
    {
        config(['tickets.reply_signature' => null]);
        $this->fakeYouTrack();

        $message = $this->withHeader('X-Client-Key', self::CLIENT_KEY)
            ->postJson('/api/tickets/from-email', $this->manualPayload([
                'sprint' => 'proposal',
                'sprintNumber' => 151,
                'senderName' => 'support@hijiffy.com',
            ]))
            ->assertOk()
            ->json('replyMessage');

        $this->assertStringStartsWith("Hi,\n\n", $message);
        $this->assertStringContainsString('and it will be proposed for Sprint 151.', $message);
        $this->assertStringEndsWith("\n\nBest,", $message);
    }

    public function test_reply_message_without_sprint(): void
    {
        $this->fakeYouTrack();

        $message = $this->withHeader('X-Client-Key', self::CLIENT_KEY)
            ->postJson('/api/tickets/from-email', $this->manualPayload(['senderName' => 'Ana']))
            ->assertOk()
            ->json('replyMessage');

        $this->assertStringStartsWith("Hi Ana,", $message);
        $this->assertStringContainsString('and our team will review and prioritize it.', $message);
    }

    private function manualPayload(array $overrides = []): array
    {
        return array_merge([
            'type' => 'task',
            'mode' => 'manual',
            'summary' => 'Fix ReviewPro links',
            'description' => self::TASK_DESCRIPTION,
        ], $overrides);
    }

    private function isCreateIssue(Request $request): bool
    {
        return $request->method() === 'POST'
            && str_starts_with($request->url(), 'https://youtrack.test/api/issues?');
    }

    private function fakeYouTrack(bool $existingAddedSprint150 = false, bool $sprintJoinFails = false): void
    {
        Http::fake(function (Request $request) use ($existingAddedSprint150, $sprintJoinFails) {
            $url = $request->url();

            if (str_contains($url, '/api/admin/projects/PRD/customFields')) {
                return Http::response([
                    ['field' => ['name' => 'State'], 'bundle' => ['values' => [['name' => 'Open', 'archived' => false, 'ordinal' => 0]]]],
                    ['field' => ['name' => 'Priority'], 'bundle' => ['values' => [
                        ['name' => 'Medium', 'archived' => false, 'ordinal' => 2],
                        ['name' => 'Highest', 'archived' => false, 'ordinal' => 0],
                        ['name' => 'Low', 'archived' => true, 'ordinal' => 3],
                        ['name' => 'High', 'archived' => false, 'ordinal' => 1],
                    ]]],
                ]);
            }

            if (str_contains($url, '/api/agiles?')) {
                return Http::response([
                    ['id' => '193-9', 'name' => 'QA Board', 'currentSprint' => ['id' => '1-1', 'name' => 'First sprint']],
                    ['id' => '193-5', 'name' => 'Product Sprint', 'currentSprint' => ['id' => '208-559', 'name' => 'Sprint 150']],
                ]);
            }

            if (str_contains($url, '/sprints/')) {
                return $sprintJoinFails
                    ? Http::response(['error' => 'Forbidden'], 403)
                    : Http::response(['id' => '2-999']);
            }

            if (str_contains($url, '/api/issueTags') && $request->method() === 'POST') {
                return Http::response(['id' => '6-new', 'name' => $request['name']]);
            }

            if (str_contains($url, '/api/issueTags') && str_contains($url, 'query=added-sprint')) {
                $tags = [
                    ['id' => '6-a137', 'name' => 'added-sprint137', 'visibleFor' => ['id' => '3-1', '$type' => 'UserGroup'], 'usableFor' => null],
                    ['id' => '6-a146', 'name' => 'added-sprint146', 'visibleFor' => ['id' => '3-7', '$type' => 'UserGroup'], 'usableFor' => null],
                ];
                if ($existingAddedSprint150) {
                    $tags[] = ['id' => '6-a150', 'name' => 'added-sprint150', 'visibleFor' => null, 'usableFor' => null];
                }

                return Http::response($tags);
            }

            if (str_contains($url, '/api/issueTags')) {
                return Http::response([
                    ['id' => '6-140', 'name' => 'proposal140'],
                    ['id' => '6-151', 'name' => 'proposal151'],
                    ['id' => '6-99', 'name' => 'proposal_old'],
                    ['id' => '6-147', 'name' => 'proposal147'],
                ]);
            }

            if (str_contains($url, '/api/issues?')) {
                return Http::response(['id' => '2-999', 'idReadable' => 'PRD-1']);
            }

            return Http::response(['error' => 'Unexpected request: ' . $url], 500);
        });
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
