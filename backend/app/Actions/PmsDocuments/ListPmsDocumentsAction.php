<?php

namespace App\Actions\PmsDocuments;

use App\Models\PmsDocument;
use Illuminate\Http\JsonResponse;

class ListPmsDocumentsAction
{
    public function handle(): JsonResponse
    {
        $documents = PmsDocument::query()
            ->whereNotNull('analysis_result')
            ->orderByDesc('created_at')
            ->simplePaginate(10, ['id', 'original_filename', 'source_url', 'title', 'is_booking_engine', 'created_at']);

        $items = $documents->getCollection()->map(static function (PmsDocument $document): array {
            return [
                'id' => $document->id,
                'original_filename' => $document->original_filename,
                'source_url' => $document->source_url,
                'title' => $document->title,
                'is_booking_engine' => $document->is_booking_engine,
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
}
