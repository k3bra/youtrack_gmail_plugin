<?php

namespace App\Actions\Triage;

use App\Models\TicketTriage;
use Illuminate\Http\JsonResponse;

class ShowTicketTriageAction
{
    public function handle(string $issueId): JsonResponse
    {
        $triage = TicketTriage::where('issue_id', strtoupper($issueId))->latest('id')->first();

        if ($triage === null) {
            return response()->json(['error' => 'No triage for this ticket yet.'], 404);
        }

        return response()->json($triage->toApiArray());
    }
}
