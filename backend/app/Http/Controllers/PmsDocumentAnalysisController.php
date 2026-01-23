<?php

namespace App\Http\Controllers;

use App\Actions\PmsDocuments\AnalyzePmsDocumentAction;
use App\Models\PmsDocument;
use Illuminate\Http\JsonResponse;

class PmsDocumentAnalysisController extends Controller
{
    public function store(PmsDocument $pmsDocument, AnalyzePmsDocumentAction $action): JsonResponse
    {
        return $action->handle($pmsDocument);
    }
}
