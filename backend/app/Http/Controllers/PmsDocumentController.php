<?php

namespace App\Http\Controllers;

use App\Actions\PmsDocuments\ListPmsDocumentsAction;
use App\Actions\PmsDocuments\ShowPmsDocumentAction;
use App\Actions\PmsDocuments\StorePmsDocumentAction;
use App\Actions\PmsDocuments\UpdatePmsDocumentAction;
use App\Models\PmsDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PmsDocumentController extends Controller
{
    public function index(ListPmsDocumentsAction $action): JsonResponse
    {
        return $action->handle();
    }

    public function show(PmsDocument $pmsDocument, ShowPmsDocumentAction $action): JsonResponse
    {
        return $action->handle($pmsDocument);
    }

    public function store(Request $request, StorePmsDocumentAction $action): JsonResponse
    {
        return $action->handle($request);
    }

    public function update(Request $request, PmsDocument $pmsDocument, UpdatePmsDocumentAction $action): JsonResponse
    {
        return $action->handle($request, $pmsDocument);
    }
}
