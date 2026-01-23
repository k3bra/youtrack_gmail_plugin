<?php

namespace App\Http\Controllers;

use App\Actions\PmsDocuments\CreatePmsDocumentTicketAction;
use App\Actions\PmsDocuments\ListPmsDocumentTicketsAction;
use App\Models\PmsDocument;
use App\Services\YouTrackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PmsDocumentTicketController extends Controller
{
    public function index(
        Request $request,
        PmsDocument $pmsDocument,
        ListPmsDocumentTicketsAction $action,
        YouTrackService $youTrackService
    ): JsonResponse {
        return $action->handle($request, $pmsDocument, $youTrackService);
    }

    public function store(
        Request $request,
        PmsDocument $pmsDocument,
        CreatePmsDocumentTicketAction $action,
        YouTrackService $youTrackService
    ): JsonResponse {
        return $action->handle($request, $pmsDocument, $youTrackService);
    }
}
