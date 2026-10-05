<?php

namespace App\Jobs;

use App\Models\TicketTriage;
use App\Services\ClaudeTriageService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class RunTicketTriageJob implements ShouldQueue
{
    use Queueable;

    // One attempt: a retry would re-run (and re-bill) a full Claude session.
    public int $tries = 1;

    public int $timeout;

    public function __construct(public int $triageId)
    {
        // Process timeout plus headroom for the YouTrack fetch and parsing.
        $this->timeout = (int) config('tickets.triage_timeout', 600) + 60;
    }

    public function handle(ClaudeTriageService $service): void
    {
        $triage = TicketTriage::find($this->triageId);
        if ($triage === null) {
            return;
        }

        $triage->update(['status' => TicketTriage::STATUS_RUNNING]);

        try {
            $result = $service->triage($triage->issue_id);
        } catch (\Throwable $e) {
            Log::warning('Ticket triage failed', ['issue_id' => $triage->issue_id, 'error' => $e->getMessage()]);
            $triage->update(['status' => TicketTriage::STATUS_FAILED, 'error' => $e->getMessage()]);

            return;
        }

        $triage->update([
            'status' => TicketTriage::STATUS_COMPLETED,
            'verdict' => $result['analysis']['verdict'] ?? null,
            'confidence' => $result['analysis']['confidence'] ?? null,
            'analysis' => $result['analysis'],
            'session_id' => $result['session_id'],
            'model' => $result['model'],
            'cost_usd' => $result['cost_usd'],
            'duration_ms' => $result['duration_ms'],
            'num_turns' => $result['num_turns'],
            'error' => null,
        ]);
    }

    public function failed(?\Throwable $e): void
    {
        // Covers worker timeouts, where handle() never reaches its own catch.
        TicketTriage::whereKey($this->triageId)
            ->whereIn('status', [TicketTriage::STATUS_QUEUED, TicketTriage::STATUS_RUNNING])
            ->update(['status' => TicketTriage::STATUS_FAILED, 'error' => $e?->getMessage() ?? 'Triage job failed.']);
    }
}
