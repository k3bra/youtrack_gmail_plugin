<?php

namespace App\Actions\Triage;

use App\Models\TicketTriage;
use App\Services\YouTrackIssueReaderService;

/**
 * Starts a triage for unresolved tickets tagged ai-fix that have never been triaged.
 * Each ticket is triaged once (manual re-runs stay available on the page), and at most
 * tickets.triage_auto_max new triages are started per call, to bound token use.
 */
class TriageAiFixTicketsAction
{
    public function __construct(
        private YouTrackIssueReaderService $reader,
        private StartTicketTriageAction $startTriage
    ) {
    }

    /**
     * @return array{started: list<string>, skipped: list<string>, queued_for_later: list<string>}
     */
    public function handle(): array
    {
        $tagged = array_map('strtoupper', $this->reader->searchIssueIds((string) config('tickets.triage_auto_query')));

        $alreadyTriaged = TicketTriage::whereIn('issue_id', $tagged)->distinct()->pluck('issue_id')->all();
        $pending = array_values(array_diff($tagged, $alreadyTriaged));
        $max = max(0, (int) config('tickets.triage_auto_max', 3));

        $started = [];
        foreach (array_slice($pending, 0, $max) as $issueId) {
            $this->startTriage->start($issueId, TicketTriage::TRIGGER_AI_FIX);
            $started[] = $issueId;
        }

        return [
            'started' => $started,
            'skipped' => array_values(array_intersect($tagged, $alreadyTriaged)),
            'queued_for_later' => array_slice($pending, $max),
        ];
    }
}
