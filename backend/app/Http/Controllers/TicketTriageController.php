<?php

namespace App\Http\Controllers;

use App\Actions\Triage\AcceptTicketTriageAction;
use App\Actions\Triage\ShowTicketTriageAction;
use App\Actions\Triage\StartTicketTriageAction;
use Illuminate\Http\JsonResponse;

class TicketTriageController extends Controller
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
