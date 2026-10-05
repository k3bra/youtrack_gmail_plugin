<?php

namespace App\Http\Controllers;

use App\Models\TicketRequest;
use App\Services\YouTrackService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

/**
 * Removes a plugin ticket from the log. The page is public, so a ticket that was
 * created in YouTrack can only be removed once its issue is gone from YouTrack.
 */
class PluginTicketDestroyController extends Controller
{
    public function __invoke(TicketRequest $ticketRequest, YouTrackService $youTrackService): RedirectResponse
    {
        $issueId = $ticketRequest->youtrack_issue_id;
        $label = $issueId ?? "request #{$ticketRequest->id}";

        if ($issueId !== null) {
            try {
                $status = $youTrackService->fetchIssueStatus($issueId);
            } catch (\Throwable $e) {
                Log::warning('Failed to check YouTrack issue before deleting', ['issue' => $issueId, 'error' => $e->getMessage()]);

                return back()->with('error', "Couldn't reach YouTrack to check {$issueId}. Try again later.");
            }

            if ($status !== null) {
                return back()->with('error', "{$issueId} still exists in YouTrack. Delete it there first.");
            }
        }

        $ticketRequest->delete();

        return back()->with('success', "Removed {$label} from the log.");
    }
}
