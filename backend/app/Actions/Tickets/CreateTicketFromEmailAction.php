<?php

namespace App\Actions\Tickets;

use App\Models\TicketRequest;
use App\Services\TicketGeneratorService;
use App\Services\YouTrackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

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
                $aiOutput['labels']
            );
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            $this->persistRecord($baseRecord, $aiOutput, null, 'failed', $message);

            return response()->json(['error' => $message], 502);
        }

        $this->persistRecord($baseRecord, $aiOutput, $issue, 'success', null);

        return response()->json($issue);
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
