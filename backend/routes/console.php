<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Picks up YouTrack tickets tagged ai-fix and triages each one once (see config/tickets.php).
Schedule::command('tickets:triage-ai-fix')->everyTenMinutes()->withoutOverlapping();
