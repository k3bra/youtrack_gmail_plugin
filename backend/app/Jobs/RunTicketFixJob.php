<?php

namespace App\Jobs;

use App\Models\TicketFix;
use App\Services\Fix\TicketFixFailed;
use App\Services\Fix\TicketFixService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class RunTicketFixJob implements ShouldQueue
{
    use Queueable;

    // One attempt: a retry would re-run Opus and could push twice.
    public int $tries = 1;

    public int $timeout;

    public function __construct(public int $fixId)
    {
        // Claude timeout plus headroom for fetch, push and the PR call.
        $this->timeout = (int) config('tickets.fix_timeout', 1500) + 180;
    }

    public function handle(TicketFixService $service): void
    {
        $fix = TicketFix::with('triage')->find($this->fixId);
        if ($fix === null) {
            return;
        }

        $fix->update(['status' => TicketFix::STATUS_RUNNING]);

        try {
            $fix->update(['error' => null, ...$service->run($fix)]);
        } catch (\Throwable $e) {
            Log::warning('Ticket fix failed', ['issue_id' => $fix->issue_id, 'error' => $e->getMessage()]);
            $fix->update([
                ...($e instanceof TicketFixFailed ? $e->attributes : []),
                'status' => TicketFix::STATUS_FAILED,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function failed(?\Throwable $e): void
    {
        // Covers worker timeouts, where handle() never reaches its own catch.
        TicketFix::whereKey($this->fixId)
            ->whereIn('status', [TicketFix::STATUS_QUEUED, TicketFix::STATUS_RUNNING])
            ->update(['status' => TicketFix::STATUS_FAILED, 'error' => $e?->getMessage() ?? 'Fix job failed.']);
    }
}
