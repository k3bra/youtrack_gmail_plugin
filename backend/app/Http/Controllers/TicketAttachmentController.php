<?php

namespace App\Http\Controllers;

use App\Actions\Tickets\FinalizeTicketAttachmentsAction;
use App\Actions\Tickets\StoreTicketAttachmentChunkAction;
use App\Services\YouTrackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketAttachmentController extends Controller
{
    public function storeChunk(
        Request $request,
        string $issueId,
        StoreTicketAttachmentChunkAction $action,
        YouTrackService $youTrackService
    ): JsonResponse {
        return $action->handle($request, $issueId, $youTrackService);
    }

    public function finalize(
        Request $request,
        string $issueId,
        FinalizeTicketAttachmentsAction $action,
        YouTrackService $youTrackService
    ): JsonResponse {
        return $action->handle($request, $issueId, $youTrackService);
    }
}
