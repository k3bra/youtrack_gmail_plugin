<?php

namespace App\Actions\PmsDocuments;

use App\Models\PmsDocument;
use App\Models\PmsDocumentTicket;
use App\Services\YouTrackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ListPmsDocumentTicketsAction
{
    public function handle(Request $request, PmsDocument $pmsDocument, YouTrackService $youTrackService): JsonResponse
    {
        $tickets = $pmsDocument->tickets()->orderByDesc('created_at')->get();
        $refresh = $request->boolean('refresh');

        if ($refresh && $tickets->isNotEmpty()) {
            foreach ($tickets as $ticket) {
                try {
                    $status = $youTrackService->fetchIssueStatus($ticket->issue_id);
                    if ($status === null) {
                        $ticket->delete();
                        continue;
                    }
                    $ticket->issue_status = $status;
                    $ticket->save();
                } catch (\Throwable $e) {
                    continue;
                }
            }
        }

        $freshTickets = $pmsDocument->tickets()->orderByDesc('created_at')->get();

        return response()->json(
            $freshTickets->map(static function (PmsDocumentTicket $ticket): array {
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
}
