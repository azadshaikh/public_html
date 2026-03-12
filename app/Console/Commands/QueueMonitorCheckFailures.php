<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\MonitorStatus;
use App\Models\Monitor;
use App\Models\User;
use App\Notifications\QueueFailureAlert;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;

/**
 * Checks for queue failure bursts within a rolling window.
 * When failures exceed the configured threshold for a given queue,
 * all super_user accounts are notified via database notification.
 * A cache-based silence window prevents repeated alerts for the same queue.
 */
class QueueMonitorCheckFailures extends Command
{
    protected $signature = 'app:queue-monitor:check-failures';

    protected $description = 'Check for queue failure bursts and notify super admins.';

    public function handle(): int
    {
        if (! config('queue-monitor.alerts.enabled', true)) {
            return self::SUCCESS;
        }

        $threshold = (int) config('queue-monitor.alerts.failure_threshold', 5);
        $windowMinutes = (int) config('queue-monitor.alerts.failure_window_minutes', 10);
        $silenceMinutes = (int) config('queue-monitor.alerts.silence_minutes', 60);

        $cutoff = Date::now()->subMinutes($windowMinutes);

        $failuresByQueue = Monitor::query()
            ->selectRaw('queue, COUNT(*) as failure_count')
            ->where('status', MonitorStatus::FAILED)
            ->where('finished_at', '>=', $cutoff)
            ->groupBy('queue')
            ->havingRaw('COUNT(*) >= ?', [$threshold])
            ->get();

        if ($failuresByQueue->isEmpty()) {
            return self::SUCCESS;
        }

        /** @var Collection<int, User> $admins */
        $admins = User::query()->role('super_user')->get();

        if ($admins->isEmpty()) {
            return self::SUCCESS;
        }

        foreach ($failuresByQueue as $row) {
            $queue = $row->queue ?? 'default';
            $count = (int) $row->failure_count;

            $cacheKey = 'queue_monitor_alert_sent.'.$queue;

            if (Cache::has($cacheKey)) {
                $this->line(sprintf('  [silenced] %s: %d failures (alert already sent)', $queue, $count));

                continue;
            }

            Cache::put($cacheKey, true, now()->addMinutes($silenceMinutes));

            foreach ($admins as $admin) {
                $admin->notify(new QueueFailureAlert($queue, $count, $windowMinutes));
            }

            $this->info(sprintf('  [alerted] %s: %d failures in last %d minutes', $queue, $count, $windowMinutes));
        }

        return self::SUCCESS;
    }
}
