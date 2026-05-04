<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;

// Revert jobs stuck in "processing" for > 5 minutes — runs every minute
Schedule::command('print-hub:revert-stale-jobs')->everyMinute()->withoutOverlapping();

// Process scheduled/recurring jobs — runs every minute
Schedule::command('print-hub:process-scheduled')->everyMinute()->withoutOverlapping();

// Retry failed webhook deliveries — runs every 5 minutes
Schedule::command('print-hub:retry-webhooks')->everyFiveMinutes()->withoutOverlapping();

// Clean up old job files — runs weekly at 02:00 on Sunday
Schedule::command('print-hub:cleanup-jobs')->weeklyOn(0, '02:00');

