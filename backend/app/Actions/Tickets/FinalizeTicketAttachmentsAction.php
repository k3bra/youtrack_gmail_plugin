<?php

namespace App\Actions\Tickets;

use App\Models\TicketRequest;
use App\Services\YouTrackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Embeds the uploaded images in the issue description so they show inline, not only as attachments.
 */
class FinalizeTicketAttachmentsAction
{
    public function handle(Request $request, string $issueId, YouTrackService $youTrackService): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'names' => 'required|array|min:1|max:50',
            'names.*' => 'required|string|max:200|regex:/^[A-Za-z0-9._-]+$/',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 400);
        }

        $createdRecently = TicketRequest::query()
            ->where('youtrack_issue_id', $issueId)
            ->where('status', 'success')
            ->where('created_at', '>=', now()->subMinutes(StoreTicketAttachmentChunkAction::UPLOAD_WINDOW_MINUTES))
            ->exists();

        if (!$createdRecently) {
            return response()->json(['error' => 'Attachments can only be added to tickets just created by this app.'], 403);
        }

        $section = "## Attachments\n" . implode("\n", array_map(
            static fn (string $name): string => "![]({$name})",
            $request->input('names')
        ));

        try {
            $youTrackService->appendToDescription($issueId, $section);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }

        return response()->json(['ok' => true]);
    }
}
