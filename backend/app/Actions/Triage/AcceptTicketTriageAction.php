<?php

namespace App\Actions\Triage;

use App\Jobs\RunTicketFixJob;
use App\Models\TicketFix;
use App\Models\TicketTriage;
use App\Services\Fix\TicketFixService;
use Illuminate\Http\JsonResponse;

/**
 * Accept & fix: only for the latest triage of a ticket, when it is completed and fixable.
 */
class AcceptTicketTriageAction
{
    public function handle(string $issueId): JsonResponse
    {
        $triage = TicketTriage::where('issue_id', strtoupper($issueId))->latest('id')->first();

        $refusal = match (true) {
            $triage === null => 'No triage for this ticket yet.',
            $triage->status !== TicketTriage::STATUS_COMPLETED => 'The triage has not finished.',
            $triage->verdict !== 'fixable' => 'Only a fixable triage can be accepted.',
            !$triage->session_id => 'The triage has no Claude session to continue.',
            TicketFixService::repoFor($triage->analysis ?? []) === null => 'The triage must point at exactly one of: '
                . implode(', ', config('tickets.fix_repos', [])) . '.',
            default => null,
        };
        if ($refusal !== null) {
            return response()->json(['error' => $refusal], 422);
        }

        // One fix per triage: a running or finished one is returned instead of starting another.
        // A failed or blocked fix can be retried.
        $existing = $triage->latestFix;
        if ($existing && ($existing->isInProgress() || $existing->status === TicketFix::STATUS_COMPLETED)) {
            return response()->json($triage->fresh()->toApiArray(), 202);
        }

        $fix = $triage->latestFix()->create(['issue_id' => $triage->issue_id, 'status' => TicketFix::STATUS_QUEUED]);
        RunTicketFixJob::dispatch($fix->id);

        return response()->json($triage->fresh()->toApiArray(), 202);
    }
}
