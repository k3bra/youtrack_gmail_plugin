<?php

namespace App\Http\Controllers;

use App\Actions\Triage\AcceptTicketTriageAction;
use App\Actions\Triage\ShowTicketTriageAction;
use App\Actions\Triage\StartTicketTriageAction;
use Illuminate\Http\JsonResponse;

/**
 * AI triage from the /plugin-tickets page (session + CSRF, unlike the X-Client-Key API routes).
 * Keyed by issue id, so tickets tagged ai-fix in YouTrack work even without a Gmail row.
 */
class PluginTicketTriageController extends Controller
{
    public function store(string $issueId, StartTicketTriageAction $action): JsonResponse
    {
        return $action->handle($issueId);
    }

    public function show(string $issueId, ShowTicketTriageAction $action): JsonResponse
    {
        return $action->handle($issueId);
    }

    public function accept(string $issueId, AcceptTicketTriageAction $action): JsonResponse
    {
        return $action->handle($issueId);
    }
}
