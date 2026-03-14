<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure-based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

// Displays an inspiring quote from the Inspiring class.
Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Commands
|--------------------------------------------------------------------------
|
| Define the application's command schedule here.
|
*/

// Queue workers are now managed by Supervisor - see hestia/bin/a-setup-queue-worker
// The schedule:run cron still runs for other scheduled tasks (media cleanup, etc.)

// Delete trashed media files daily.
Schedule::command('media:purge-trashed')
    ->dailyAt('01:40')
    ->runInBackground();

// Clean up temporary media files every hour (files older than 2 hours).
Schedule::command('media:clean-temp --older-than=2')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();

// Check for queue failure bursts and alert super users.
Schedule::command('app:queue-monitor:check-failures')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Build hourly throughput snapshots for the queue monitor charts.
if (config('queue-monitor.snapshots.enabled', true)) {
    Schedule::command('app:queue-monitor:aggregate')
        ->hourly()
        ->withoutOverlapping()
        ->runInBackground();
}
