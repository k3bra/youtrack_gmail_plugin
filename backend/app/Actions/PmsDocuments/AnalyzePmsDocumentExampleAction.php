<?php

namespace App\Actions\PmsDocuments;

use App\Models\PmsDocument;
use App\Services\PmsDocumentAnalysisService;
use Illuminate\Http\JsonResponse;

class AnalyzePmsDocumentExampleAction
{
    public function __construct(
        private ExtractPmsDocumentTextAction $extractText,
        private PmsDocumentAnalysisService $analysisService
    ) {
    }

    public function handle(PmsDocument $pmsDocument): JsonResponse
    {
        try {
            $text = $this->extractText->handle($pmsDocument);
            $example = $this->analysisService->analyzeExample($text, (bool) $pmsDocument->is_booking_engine);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }

        return response()->json($example);
    }
}
