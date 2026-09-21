<?php

use Illuminate\Support\Facades\Schedule;

// Run with `php artisan schedule:work` (development) or a cron entry calling
// `php artisan schedule:run` every minute (production).

Schedule::command('audits:prune')->hourly()->withoutOverlapping();

// Housekeeping of Laravel's own queue tables.
Schedule::command('queue:prune-batches --hours=48 --unfinished=72')->daily();
Schedule::command('queue:prune-failed --hours=168')->daily();
