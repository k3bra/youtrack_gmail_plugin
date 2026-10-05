<?php

namespace App\Console\Commands;

use App\Actions\Triage\TriageAiFixTicketsAction;
use Illuminate\Console\Command;

class TriageAiFixTicketsCommand extends Command
{
    protected $signature = 'tickets:triage-ai-fix';

    protected $description = 'Start an AI triage for unresolved YouTrack tickets tagged ai-fix that were never triaged';

    public function handle(TriageAiFixTicketsAction $action): int
    {
        if (!config('tickets.triage_auto_enabled')) {
            $this->line('Auto-triage is disabled (TRIAGE_AUTO_ENABLED=false).');

            return self::SUCCESS;
        }

        try {
            $result = $action->handle();
        } catch (\Throwable $e) {
            $this->error('Could not check ai-fix tickets: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->line('started: ' . ($result['started'] ? implode(', ', $result['started']) : '—'));
        $this->line('already triaged: ' . ($result['skipped'] ? implode(', ', $result['skipped']) : '—'));
        if ($result['queued_for_later']) {
            $this->line('next check: ' . implode(', ', $result['queued_for_later']) . ' (over TRIAGE_AUTO_MAX)');
        }

        return self::SUCCESS;
    }
}
