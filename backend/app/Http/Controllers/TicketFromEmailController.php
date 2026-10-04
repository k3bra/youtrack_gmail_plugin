<?php

namespace App\Http\Controllers;

use App\Actions\Tickets\CreateTicketFromEmailAction;
use App\Actions\Tickets\PreviewTicketFromEmailAction;
use App\Services\TicketGeneratorService;
use App\Services\YouTrackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketFromEmailController extends Controller
{
    public function store(
        Request $request,
        CreateTicketFromEmailAction $action,
        TicketGeneratorService $ticketGenerator,
        YouTrackService $youTrackService
    ): JsonResponse {
        return $action->handle($request, $ticketGenerator, $youTrackService);
    }

    public function preview(
        Request $request,
        PreviewTicketFromEmailAction $action,
        TicketGeneratorService $ticketGenerator
    ): JsonResponse {
        return $action->handle($request, $ticketGenerator);
    }

    public function sprintOptions(YouTrackService $youTrackService): JsonResponse
    {
        try {
            $current = $youTrackService->fetchCurrentSprint();
            $proposal = $youTrackService->fetchLatestProposalTag();
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }

        return response()->json([
            'current' => $current === null ? null : ['name' => $current['name'], 'number' => $current['number']],
            'proposal' => $proposal === null ? null : ['name' => $proposal['name'], 'number' => $proposal['sprint']],
        ]);
    }

    public function priorities(YouTrackService $youTrackService): JsonResponse
    {
        try {
            return response()->json(['priorities' => $youTrackService->fetchPriorityValues()]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }
    }
}
