<?php

namespace App\Actions\PmsDocuments;

use App\Models\PmsDocument;
use App\Models\PmsDocumentTicket;
use App\Services\YouTrackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreatePmsDocumentTicketAction
{
    public function handle(Request $request, PmsDocument $pmsDocument, YouTrackService $youTrackService): JsonResponse
    {
        $type = 'spike';

        $analysis = $pmsDocument->analysis_result;
        if (!is_array($analysis)) {
            return response()->json(['error' => 'Document has not been analyzed yet.'], 400);
        }

        $summaryTarget = $pmsDocument->title ?? $pmsDocument->source_url ?? $pmsDocument->original_filename;
        $summary = 'PMS analysis: ' . $summaryTarget;
        $description = $this->resolveTicketDescription($request, $pmsDocument, $analysis, $type);
        $labels = ['pms', 'analysis'];
        $customFields = [
            [
                '$type' => 'SingleEnumIssueCustomField',
                'name' => 'Type',
                'value' => [
                    '$type' => 'EnumBundleElement',
                    'name' => 'Spike',
                ],
            ],
            [
                '$type' => 'MultiEnumIssueCustomField',
                'name' => 'Team(s)',
                'value' => [
                    [
                        '$type' => 'EnumBundleElement',
                        'name' => 'BE',
                    ],
                ],
            ],
        ];

        try {
            $issue = $youTrackService->createIssue($type, $summary, $description, $labels, $customFields);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }

        $status = null;
        try {
            $status = $youTrackService->fetchIssueStatus($issue['issueId']);
        } catch (\Throwable $e) {
            $status = null;
        }

        PmsDocumentTicket::create([
            'pms_document_id' => $pmsDocument->id,
            'issue_id' => $issue['issueId'],
            'issue_url' => $issue['url'],
            'issue_status' => $status,
            'issue_type' => $type,
        ]);

        return response()->json($issue);
    }

    private function resolveTicketDescription(
        Request $request,
        PmsDocument $pmsDocument,
        array $analysis,
        string $type
    ): string {
        $override = $request->input('description');
        if (is_string($override) && trim($override) !== '') {
            return trim($override);
        }

        return $this->formatTicketDescription($request, $pmsDocument, $analysis, $type);
    }

    private function formatTicketDescription(Request $request, PmsDocument $pmsDocument, array $analysis, string $type): string
    {
        if ($type === 'spike') {
            return $this->formatSpikeDescription($request, $pmsDocument, $analysis);
        }

        $lines = [
            'Source file: ' . ($pmsDocument->source_url ?? $pmsDocument->original_filename),
            'Document ID: ' . $pmsDocument->id,
            '',
            'GET reservations endpoint: ' . ($analysis['has_get_reservations_endpoint'] ? 'Yes' : 'No'),
        ];

        if (!empty($analysis['get_reservations_endpoint'])) {
            $lines[] = 'Endpoint: ' . $analysis['get_reservations_endpoint'];
        }

        $lines[] = 'Webhooks: ' . ($analysis['supports_webhooks'] ? 'Yes' : 'No');

        if (!empty($analysis['webhook_details'])) {
            $lines[] = 'Webhook details: ' . $analysis['webhook_details'];
        }

        $lines[] = '';
        $lines[] = 'Fields:';

        $fieldMap = [
            'Check-in date' => 'check_in_date',
            'Check-out date' => 'checkout_date',
            'First name' => 'first_name',
            'Last name' => 'last_name',
            'Reservation ID' => 'reservation_id',
            'Mobile phone' => 'mobile_phone',
            'Email' => 'email',
        ];

        foreach ($fieldMap as $label => $key) {
            $entry = $analysis['fields'][$key] ?? null;
            if (!is_array($entry)) {
                continue;
            }
            $available = ($entry['available'] ?? false) ? 'Yes' : 'No';
            $line = '- ' . $label . ': ' . $available;
            $sourceLabel = $entry['source_label'] ?? null;
            if (is_string($sourceLabel) && $sourceLabel !== '') {
                $line .= ' (source label: ' . $sourceLabel . ')';
            }
            $lines[] = $line;
        }

        $status = $analysis['fields']['reservation_status'] ?? null;
        if (is_array($status)) {
            $available = ($status['available'] ?? false) ? 'Yes' : 'No';
            $line = '- Reservation status: ' . $available;
            $sourceLabel = $status['source_label'] ?? null;
            if (is_string($sourceLabel) && $sourceLabel !== '') {
                $line .= ' (source label: ' . $sourceLabel . ')';
            }
            $lines[] = $line;

            $values = $status['values'] ?? [];
            if (is_array($values) && $values !== []) {
                $lines[] = '  Values: ' . implode(', ', $values);
            }
        }

        $notes = $analysis['notes'] ?? [];
        if (is_array($notes) && $notes !== []) {
            $lines[] = '';
            $lines[] = 'Notes:';
            foreach ($notes as $note) {
                if (is_string($note) && $note !== '') {
                    $lines[] = '- ' . $note;
                }
            }
        }

        return implode("\n", $lines);
    }

    private function formatSpikeDescription(Request $request, PmsDocument $pmsDocument, array $analysis): string
    {
        $documentLink = $this->buildDocumentLink($request, $pmsDocument);

        $lines = [
            'Context:',
            'Integrate with the following PMS: ' . ($pmsDocument->source_url ?? $pmsDocument->original_filename) . '.',
            $pmsDocument->title ? 'Document title: ' . $pmsDocument->title . '.' : null,
            '',
            'Goal:',
            'Fetch reservations from the PMS and create tickets in YouTrack.',
            '',
            'Scope:',
            '- Review documented endpoints and required fields for integration readiness.',
            '- Validate GET reservations endpoint availability and response structure.',
            '- Confirm webhook support and payload expectations.',
            '',
            'Expected Outcome:',
            '- Clear mapping of required fields and any gaps to complete the campaigns integration.',
            '',
            'Conclusion:',
            'Pending spike investigation.',
            '',
            'Next Steps:',
            'After successful integration, create the next ticket.',
            '',
            'Documentation:',
            $documentLink !== null
                ? $documentLink
                : 'Document: ' . ($pmsDocument->source_url ?? $pmsDocument->original_filename),
        ];

        return implode("\n", array_values(array_filter($lines, static fn ($line) => $line !== null)));
    }

    private function buildDocumentLink(Request $request, PmsDocument $pmsDocument): ?string
    {
        $baseUrl = rtrim($request->getSchemeAndHttpHost(), '/');
        if ($baseUrl === '') {
            return null;
        }

        return $baseUrl . '/pms-documents/' . $pmsDocument->id . '/download';
    }
}
