<?php

namespace App\Http\Controllers;

use App\Actions\PmsDocuments\AnalyzePmsDocumentExampleAction;
use App\Models\PmsDocument;
use Illuminate\Http\JsonResponse;

class PmsDocumentExampleController extends Controller
{
    public function store(PmsDocument $pmsDocument, AnalyzePmsDocumentExampleAction $action): JsonResponse
    {
        return $action->handle($pmsDocument);
    }
}
