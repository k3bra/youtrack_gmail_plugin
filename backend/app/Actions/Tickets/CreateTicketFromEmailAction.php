<?php

namespace App\Actions\Tickets;

use App\Models\TicketRequest;
use App\Services\TicketGeneratorService;
use App\Services\YouTrackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

class CreateTicketFromEmailAction
{
    public function handle(
        Request $request,
        TicketGeneratorService $ticketGenerator,
        YouTrackService $youTrackService
    ): JsonResponse {
        $payload = $request->all();
        $mode = $this->normalizeMode($payload['mode'] ?? null);
        $baseRecord = $this->buildBaseRecord($payload, $mode);

        $rules = [
            'type' => 'required|in:task,spike',
            'mode' => 'required|in:email,manual,ai',
            'priority' => 'nullable|string',
            'sprint' => 'nullable|in:current,proposal',
            'sprintNumber' => 'required_with:sprint|integer',
            'senderName' => 'nullable|string',
        ];

        if ($mode === 'manual') {
            $rules['summary'] = 'required|string';
            $rules['description'] = 'required|string';
            $rules['labels'] = 'nullable|array';
            $rules['labels.*'] = 'string';
            $rules['email'] = 'nullable|array';
        } elseif ($mode === 'email') {
            $rules['email.subject'] = 'required|string';
            $rules['email.body'] = 'required|string';
            $rules['email.from'] = 'nullable|string';
            $rules['email.threadUrl'] = 'nullable|string';
        }

        $validator = Validator::make($payload, $rules);

        if ($validator->fails()) {
            $message = $validator->errors()->first();
            $this->persistRecord($baseRecord, null, null, 'failed', $message);

            return response()->json(['error' => $message], 400);
        }

        $customFields = [];
        $priority = $payload['priority'] ?? null;
        if (is_string($priority) && $priority !== '') {
            try {
                $allowedPriorities = $youTrackService->fetchPriorityValues();
            } catch (\Throwable $e) {
                $message = $e->getMessage();
                $this->persistRecord($baseRecord, null, null, 'failed', $message);

                return response()->json(['error' => $message], 502);
            }

            if (!in_array($priority, $allowedPriorities, true)) {
                $message = 'Priority must be one of: ' . implode(', ', $allowedPriorities) . '.';
                $this->persistRecord($baseRecord, null, null, 'failed', $message);

                return response()->json(['error' => $message], 400);
            }

            $customFields[] = $youTrackService->priorityCustomField($priority);
        }

        try {
            [$tags, $sprintToJoin] = $this->resolveSprint($payload, $youTrackService);
        } catch (InvalidArgumentException $e) {
            $message = $e->getMessage();
            $this->persistRecord($baseRecord, null, null, 'failed', $message);

            return response()->json(['error' => $message], 400);
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            $this->persistRecord($baseRecord, null, null, 'failed', $message);

            return response()->json(['error' => $message], 502);
        }

        $normalizedPayload = $this->normalizePayload($payload, $mode ?? 'email');
        $baseRecord = $this->buildBaseRecord($normalizedPayload, $mode);

        try {
            if ($mode === 'manual') {
                $aiOutput = $ticketGenerator->fromManual($payload);
            } else {
                $aiOutput = $ticketGenerator->fromEmail($normalizedPayload);
            }
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            $this->persistRecord($baseRecord, null, null, 'failed', $message);

            return response()->json(['error' => $message], 502);
        }

        $type = (string) $payload['type'];

        try {
            $issue = $youTrackService->createIssue(
                $type,
                $aiOutput['summary'],
                $aiOutput['description'],
                $customFields,
                $tags
            );
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            $this->persistRecord($baseRecord, $aiOutput, null, 'failed', $message);

            return response()->json(['error' => $message], 502);
        }

        $warning = null;
        if ($sprintToJoin !== null) {
            try {
                $youTrackService->addIssueToSprint($sprintToJoin['agileId'], $sprintToJoin['id'], $issue['databaseId']);
            } catch (\Throwable $e) {
                Log::warning('Failed to add issue to sprint', ['issue' => $issue['issueId'], 'error' => $e->getMessage()]);
                $warning = "Ticket created, but it could not be added to {$sprintToJoin['name']}. Add it on the board manually.";
            }
        }

        $this->persistRecord($baseRecord, $aiOutput, $issue, 'success', null);

        $response = [
            'issueId' => $issue['issueId'],
            'url' => $issue['url'],
            'replyMessage' => $this->buildReplyMessage(
                $payload['senderName'] ?? null,
                $issue,
                $payload['sprint'] ?? null,
                isset($payload['sprintNumber']) ? (int) $payload['sprintNumber'] : null
            ),
        ];
        if ($warning !== null) {
            $response['warning'] = $warning;
        }

        return response()->json($response);
    }

    /**
     * Reply to paste into the email thread so the reporter knows the ticket exists and where it is headed.
     */
    private function buildReplyMessage(?string $senderName, array $issue, ?string $sprint, ?int $sprintNumber): string
    {
        $firstName = $this->firstName($senderName);
        $greeting = $firstName === null ? 'Hi,' : "Hi {$firstName},";

        $plan = match ($sprint) {
            'current' => "we'll work on it in the current sprint (Sprint {$sprintNumber})",
            'proposal' => "it will be proposed for Sprint {$sprintNumber}",
            default => 'our team will review and prioritize it',
        };

        $signature = trim((string) config('tickets.reply_signature'));
        $signOff = $signature === '' ? 'Best,' : "Best,\n{$signature}";

        return "{$greeting}\n\n"
            . "Thanks for reporting this. We've created {$issue['issueId']} to track it, and {$plan}.\n\n"
            . "You can follow its progress in YouTrack: {$issue['url']}\n\n"
            . $signOff;
    }

    private function firstName(?string $senderName): ?string
    {
        if (!is_string($senderName)) {
            return null;
        }

        $name = trim($senderName);
        if ($name === '' || str_contains($name, '@')) {
            return null;
        }

        return preg_split('/\s+/', $name)[0];
    }

    /**
     * Returns the tags to apply and, for the current sprint, the sprint to add the issue to.
     * The extension sends the sprint number it showed, so a stale form can't target the wrong sprint.
     *
     * @return array{0: array, 1: ?array}
     */
    private function resolveSprint(array $payload, YouTrackService $youTrackService): array
    {
        $sprint = $payload['sprint'] ?? null;
        if ($sprint === null) {
            return [[], null];
        }

        $expectedNumber = (int) $payload['sprintNumber'];

        if ($sprint === 'proposal') {
            $proposalTag = $youTrackService->fetchLatestProposalTag();
            if ($proposalTag === null) {
                throw new InvalidArgumentException('No proposal tag exists in YouTrack.');
            }
            if ($proposalTag['sprint'] !== $expectedNumber) {
                throw new InvalidArgumentException(
                    "The proposal sprint is now {$proposalTag['sprint']}. Reopen the ticket form and try again."
                );
            }

            return [[['id' => $proposalTag['id']]], null];
        }

        $currentSprint = $youTrackService->fetchCurrentSprint();
        if ($currentSprint === null) {
            throw new InvalidArgumentException('The sprint board has no current sprint.');
        }
        if ($currentSprint['number'] !== $expectedNumber) {
            throw new InvalidArgumentException(
                "The current sprint is now {$currentSprint['number']}. Reopen the ticket form and try again."
            );
        }

        $addedTag = $youTrackService->findOrCreateAddedSprintTag($currentSprint['number']);

        return [[['id' => $addedTag['id']]], $currentSprint];
    }

    private function buildBaseRecord(array $payload, ?string $mode): array
    {
        $email = $this->extractEmailFields($payload, $mode);

        return [
            'request_type' => $payload['type'] ?? null,
            'email_subject' => $email['subject'],
            'email_from' => $email['from'],
            'email_body' => $email['body'],
            'email_thread_url' => $email['threadUrl'],
        ];
    }

    private function normalizeMode(?string $mode): ?string
    {
        if ($mode === 'ai') {
            return 'email';
        }

        if ($mode === 'email' || $mode === 'manual') {
            return $mode;
        }

        return null;
    }

    private function normalizePayload(array $payload, string $mode): array
    {
        if ($mode === 'manual') {
            // Drafts reviewed in the extension carry the source email, so keep it for the record.
            return [
                'type' => $payload['type'] ?? null,
                'email' => [
                    'subject' => data_get($payload, 'email.subject') ?? $payload['summary'] ?? null,
                    'from' => data_get($payload, 'email.from') ?? 'manual',
                    'body' => data_get($payload, 'email.body') ?? $payload['description'] ?? null,
                    'threadUrl' => data_get($payload, 'email.threadUrl'),
                ],
            ];
        }

        $email = is_array($payload['email'] ?? null) ? $payload['email'] : [];

        return [
            'type' => $payload['type'] ?? null,
            'email' => [
                'subject' => $email['subject'] ?? null,
                'from' => $email['from'] ?? null,
                'body' => $email['body'] ?? null,
                'threadUrl' => $email['threadUrl'] ?? null,
            ],
        ];
    }

    private function extractEmailFields(array $payload, ?string $mode): array
    {
        if ($mode === 'manual') {
            return [
                'subject' => $payload['summary'] ?? data_get($payload, 'email.subject'),
                'from' => data_get($payload, 'email.from') ?? 'manual',
                'body' => $payload['description'] ?? data_get($payload, 'email.body'),
                'threadUrl' => data_get($payload, 'email.threadUrl'),
            ];
        }

        return [
            'subject' => data_get($payload, 'email.subject'),
            'from' => data_get($payload, 'email.from'),
            'body' => data_get($payload, 'email.body'),
            'threadUrl' => data_get($payload, 'email.threadUrl'),
        ];
    }

    private function persistRecord(
        array $baseRecord,
        ?array $aiOutput,
        ?array $issue,
        string $status,
        ?string $errorMessage
    ): void {
        $record = $baseRecord;
        $record['status'] = $status;
        $record['error_message'] = $errorMessage;

        if (is_array($aiOutput)) {
            $record['ai_summary'] = $aiOutput['summary'] ?? null;
            $record['ai_description'] = $aiOutput['description'] ?? null;
            $record['ai_labels'] = $aiOutput['labels'] ?? null;
        }

        if (is_array($issue)) {
            $record['youtrack_issue_id'] = $issue['issueId'] ?? null;
        }

        TicketRequest::create($record);
    }
}
