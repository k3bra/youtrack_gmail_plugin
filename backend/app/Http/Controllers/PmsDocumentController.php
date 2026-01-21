<?php

namespace App\Http\Controllers;

use App\Models\PmsDocument;
use App\Models\PmsDocumentTicket;
use App\Services\PdfTextExtractorService;
use App\Services\PmsDocumentAnalysisService;
use App\Services\YouTrackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PmsDocumentController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'document' => 'required|file|mimes:pdf|max:20480',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 400);
        }

        $file = $request->file('document');
        if ($file === null) {
            return response()->json(['error' => 'Document upload is required.'], 400);
        }

        $path = $file->store('pms-documents');

        $document = PmsDocument::create([
            'original_filename' => $file->getClientOriginalName(),
            'storage_path' => $path,
        ]);

        return response()->json(['id' => $document->id]);
    }

    public function index(): JsonResponse
    {
        $documents = PmsDocument::query()
            ->whereNotNull('analysis_result')
            ->orderByDesc('created_at')
            ->simplePaginate(10, ['id', 'original_filename', 'created_at']);

        $items = $documents->getCollection()->map(static function (PmsDocument $document): array {
            return [
                'id' => $document->id,
                'original_filename' => $document->original_filename,
                'created_at' => $document->created_at?->toISOString(),
            ];
        });

        return response()->json([
            'data' => $items,
            'pagination' => [
                'current_page' => $documents->currentPage(),
                'next_page' => $documents->nextPageUrl() ? $documents->currentPage() + 1 : null,
                'prev_page' => $documents->previousPageUrl() ? $documents->currentPage() - 1 : null,
            ],
        ]);
    }

    public function show(PmsDocument $pmsDocument): JsonResponse
    {
        return response()->json([
            'id' => $pmsDocument->id,
            'original_filename' => $pmsDocument->original_filename,
            'created_at' => $pmsDocument->created_at?->toISOString(),
            'analysis_result' => $pmsDocument->analysis_result,
        ]);
    }

    public function analyze(
        PmsDocument $pmsDocument,
        PdfTextExtractorService $pdfTextExtractor,
        PmsDocumentAnalysisService $analysisService
    ): JsonResponse {
        try {
            $path = Storage::path($pmsDocument->storage_path);
            $text = $pdfTextExtractor->extractText($path);
            $analysis = $analysisService->analyze($text);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }

        $pmsDocument->analysis_result = $analysis;
        $pmsDocument->save();

        return response()->json($analysis);
    }

    public function example(
        PmsDocument $pmsDocument,
        PdfTextExtractorService $pdfTextExtractor,
        PmsDocumentAnalysisService $analysisService
    ): JsonResponse {
        try {
            $path = Storage::path($pmsDocument->storage_path);
            $text = $pdfTextExtractor->extractText($path);
            $example = $analysisService->analyzeExample($text);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }

        return response()->json($example);
    }

    public function ticket(Request $request, PmsDocument $pmsDocument, YouTrackService $youTrackService): JsonResponse
    {
        $type = 'spike';

        $analysis = $pmsDocument->analysis_result;
        if (!is_array($analysis)) {
            return response()->json(['error' => 'Document has not been analyzed yet.'], 400);
        }

        $summary = 'PMS analysis: ' . $pmsDocument->original_filename;
        $description = $this->resolveTicketDescription($request, $pmsDocument, $analysis, $type);
        $labels = ['pms', 'analysis'];

        try {
            $issue = $youTrackService->createIssue($type, $summary, $description, $labels);
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

    public function tickets(Request $request, PmsDocument $pmsDocument, YouTrackService $youTrackService): JsonResponse
    {
        $tickets = $pmsDocument->tickets()->orderByDesc('created_at')->get();
        $refresh = $request->boolean('refresh');

        if ($refresh && $tickets->isNotEmpty()) {
            foreach ($tickets as $ticket) {
                try {
                    $ticket->issue_status = $youTrackService->fetchIssueStatus($ticket->issue_id);
                    $ticket->save();
                } catch (\Throwable $e) {
                    continue;
                }
            }
        }

        return response()->json(
            $tickets->map(static function (PmsDocumentTicket $ticket): array {
                return [
                    'id' => $ticket->id,
                    'issue_id' => $ticket->issue_id,
                    'issue_url' => $ticket->issue_url,
                    'issue_status' => $ticket->issue_status,
                    'issue_type' => $ticket->issue_type,
                    'created_at' => $ticket->created_at?->toISOString(),
                ];
            })
        );
    }

    public function download(PmsDocument $pmsDocument): StreamedResponse
    {
        if (!Storage::exists($pmsDocument->storage_path)) {
            abort(404, 'PDF not found.');
        }

        return Storage::download($pmsDocument->storage_path, $pmsDocument->original_filename);
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
            'Source file: ' . $pmsDocument->original_filename,
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
            'Integrate with the following PMS: ' . $pmsDocument->original_filename . '.',
            '',
            'Goal:',
            'Fetch campaigns and be able to send campaigns.',
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
                : 'PDF: ' . $pmsDocument->original_filename,
        ];

        return implode("\n", $lines);
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
