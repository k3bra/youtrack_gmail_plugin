<?php

namespace App\Http\Controllers;

use App\Models\TicketRequest;
use App\Services\TicketGeneratorService;
use App\Services\YouTrackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TicketFromEmailController extends Controller
{
    public function __invoke(
        Request $request,
        TicketGeneratorService $ticketGenerator,
        YouTrackService $youTrackService
    ): JsonResponse {
        $payload = $request->all();
        $baseRecord = $this->buildBaseRecord($payload);

        $validator = Validator::make($payload, [
            'type' => 'required|in:task,spike',
            'email.subject' => 'required|string',
            'email.from' => 'required|string',
            'email.body' => 'required|string',
            'email.threadUrl' => 'required|string',
        ]);

        if ($validator->fails()) {
            $message = $validator->errors()->first();
            $this->persistRecord($baseRecord, null, null, 'failed', $message);

            return response()->json(['error' => $message], 400);
        }

        try {
            $aiOutput = $ticketGenerator->fromEmail($payload);
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

    private function buildBaseRecord(array $payload): array
    {
        return [
            'request_type' => $payload['type'] ?? null,
            'email_subject' => data_get($payload, 'email.subject'),
            'email_from' => data_get($payload, 'email.from'),
            'email_body' => data_get($payload, 'email.body'),
            'email_thread_url' => data_get($payload, 'email.threadUrl'),
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
