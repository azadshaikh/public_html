<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\MonitorStatus;
use App\Models\Monitor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Aggregates queue_monitor rows into hourly snapshots.
 *
 * Run every hour via the scheduler. For each queue, counts succeeded/failed
 * and computes avg duration for all jobs that finished in the previous hour,
 * then upserts a row into queue_monitor_snapshots.
 *
 * Old snapshots are pruned according to `queue-monitor.snapshots.retention_days`.
 */
class QueueMonitorAggregateCommand extends Command
{
    protected $signature = 'app:queue-monitor:aggregate
                            {--period-start= : Override the period start (ISO 8601, for back-filling)}';

    protected $description = 'Aggregate queue monitor data into hourly snapshots for throughput charts.';

    public function handle(): int
    {
        $periodStart = $this->option('period-start')
            ? Date::parse($this->option('period-start'))->startOfHour()
            : Date::now()->subHour()->startOfHour();

        $periodEnd = $periodStart->copy()->addHour();

        $this->components->info(sprintf('Aggregating period %s → %s', $periodStart->toDateTimeString(), $periodEnd->toDateTimeString()));

        // EXTRACT(EPOCH FROM ...) is PostgreSQL-specific — intentional, this app targets PostgreSQL exclusively.
        $rows = Monitor::query()
            ->selectRaw('
                queue,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS succeeded,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS failed,
                AVG(CASE
                    WHEN status = ? AND started_at IS NOT NULL AND finished_at IS NOT NULL
                    THEN EXTRACT(EPOCH FROM (finished_at - started_at))
                    ELSE NULL
                END) AS avg_duration
            ', [MonitorStatus::SUCCEEDED, MonitorStatus::FAILED, MonitorStatus::SUCCEEDED])
            ->where('finished_at', '>=', $periodStart)
            ->where('finished_at', '<', $periodEnd)
            ->whereNotNull('queue')
            ->groupBy('queue')
            ->get();

        foreach ($rows as $row) {
            DB::table('queue_monitor_snapshots')->upsert(
                [
                    'period_start' => $periodStart,
                    'queue' => $row->queue,
                    'succeeded' => (int) $row->succeeded,
                    'failed' => (int) $row->failed,
                    'avg_duration' => $row->avg_duration !== null ? round((float) $row->avg_duration, 2) : null,
                    'created_at' => now(),
                ],
                ['period_start', 'queue'],
                ['succeeded', 'failed', 'avg_duration'],
            );
        }

        $this->components->info(sprintf('Snapshotted %d queue(s).', $rows->count()));

        // Prune old snapshots
        $retentionDays = (int) (config('queue-monitor.snapshots.retention_days', 30));
        $pruned = DB::table('queue_monitor_snapshots')
            ->where('period_start', '<', Date::now()->subDays($retentionDays))
            ->delete();

        if ($pruned > 0) {
            $this->components->info(sprintf('Pruned %d old snapshot row(s) (retention: %d days).', $pruned, $retentionDays));
        }

        return self::SUCCESS;
    }
}
