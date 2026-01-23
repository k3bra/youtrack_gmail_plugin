<?php

namespace App\Actions\PmsDocuments;

use App\Models\PmsDocument;
use Illuminate\Http\JsonResponse;

class ShowPmsDocumentAction
{
    public function handle(PmsDocument $pmsDocument): JsonResponse
    {
        return response()->json([
            'id' => $pmsDocument->id,
            'original_filename' => $pmsDocument->original_filename,
            'source_url' => $pmsDocument->source_url,
            'title' => $pmsDocument->title,
            'is_booking_engine' => $pmsDocument->is_booking_engine,
            'created_at' => $pmsDocument->created_at?->toISOString(),
            'analysis_result' => $pmsDocument->analysis_result,
        ]);
    }
}
