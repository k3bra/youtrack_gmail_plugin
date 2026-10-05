<?php

namespace App\Actions\Triage;

use App\Jobs\RunTicketTriageJob;
use App\Models\TicketTriage;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class StartTicketTriageAction
{
    public function handle(string $issueId, string $trigger = TicketTriage::TRIGGER_MANUAL): JsonResponse
    {
        try {
            $triage = $this->start($issueId, $trigger);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json($triage->toApiArray(), 202);
    }

    public function start(string $issueId, string $trigger = TicketTriage::TRIGGER_MANUAL): TicketTriage
    {
        $issueId = strtoupper($issueId);

        // The id ends up in a file path and in the Claude prompt, so only accept a plain issue id.
        if (!preg_match('/^[A-Z][A-Z0-9_]*-[0-9]+$/', $issueId)) {
            throw new InvalidArgumentException('Invalid issue id.');
        }

        // A triage already queued or running for this ticket is reused, so a double click
        // doesn't start (and pay for) a second Claude session.
        $existing = TicketTriage::where('issue_id', $issueId)->latest('id')->first();
        if ($existing?->isInProgress()) {
            return $existing;
        }

        $triage = TicketTriage::create([
            'issue_id' => $issueId,
            'trigger' => $trigger,
            'status' => TicketTriage::STATUS_QUEUED,
        ]);
        RunTicketTriageJob::dispatch($triage->id);

        return $triage->fresh();
    }
}
