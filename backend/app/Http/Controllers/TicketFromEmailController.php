<?php

namespace App\Http\Controllers;

use App\Actions\Tickets\CreateTicketFromEmailAction;
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
}
