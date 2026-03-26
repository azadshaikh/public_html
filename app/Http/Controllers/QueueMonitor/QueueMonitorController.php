<?php

declare(strict_types=1);

namespace App\Http\Controllers\QueueMonitor;

use App\Definitions\QueueMonitorDefinition;
use App\Enums\MonitorStatus;
use App\Http\Controllers\QueueMonitor\Payloads\Metric;
use App\Http\Controllers\QueueMonitor\Payloads\Metrics;
use App\Models\Monitor;
use App\Scaffold\ScaffoldController;
use App\Services\QueueMonitorService;
use App\Services\WorkerMonitorService;
use Exception;
use Illuminate\Database as DatabaseConnections;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class QueueMonitorController extends ScaffoldController implements HasMiddleware
{
    // =========================================================================
    // WIRING
    // =========================================================================

    public function __construct(
        private readonly QueueMonitorService $queueMonitorService,
        private readonly WorkerMonitorService $workerMonitorService,
    ) {}

    protected function service(): QueueMonitorService
    {
        return $this->queueMonitorService;
    }

    protected function inertiaPage(): string
    {
        return 'masters/queue-monitor';
    }

    public static function middleware(): array
    {
        return (new QueueMonitorDefinition)->getMiddleware();
    }

    // =========================================================================
    // INDEX — Override to inject metrics
    // =========================================================================

    public function index(Request $request): Response|RedirectResponse
    {
        $this->enforcePermission('view');

        $status = (string) ($request->input('status') ?? $request->route('status') ?? 'all');
        $perPage = $this->service()->getScaffoldDefinition()->getPerPage();
        $metrics = config('queue-monitor.ui.show_metrics') ? $this->collectMetrics()->all() : null;
        $queueStats = $this->service()->getQueueStats();
        $chartData = config('queue-monitor.snapshots.enabled', true)
            ? $this->service()->getChartData((int) (config('queue-monitor.snapshots.chart_hours', 24)))
            : null;
        $workerStats = config('queue-monitor.workers.enabled', true)
            ? $this->workerMonitorService->getWorkerStats()
            : null;

        return Inertia::render($this->inertiaPage().'/index', [
            'config' => $this->service()->getScaffoldDefinition()->toInertiaConfig(),
            'monitors' => $this->service()->getPaginatedMonitors($request),
            'statistics' => $this->service()->getStatistics(),
            'filters' => [
                'search' => (string) $request->input('search', ''),
                'queue' => (string) $request->input('queue', ''),
                'metadata_key' => (string) $request->input('metadata_key', ''),
                'metadata_value' => (string) $request->input('metadata_value', ''),
                'status' => $status,
                'sort' => (string) ($request->input('sort') ?? $request->input('sort_column') ?? 'started_at'),
                'direction' => (string) ($request->input('direction') ?? $request->input('sort_direction') ?? 'desc'),
                'per_page' => (int) $request->input('per_page', $perPage),
                'view' => (string) $request->input('view', 'table'),
            ],
            'metrics' => $metrics,
            'queueStats' => $queueStats,
            'chartData' => $chartData,
            'workerStats' => $workerStats,
            'queueOptions' => collect($queueStats)
                ->pluck('queue')
                ->values()
                ->map(fn (string $queue): array => ['value' => $queue, 'label' => $queue])
                ->all(),
            'ui' => [
                'refreshInterval' => config('queue-monitor.ui.refresh_interval'),
                'metricsTimeFrame' => (int) config('queue-monitor.ui.metrics_time_frame', 14),
                'chartHours' => (int) config('queue-monitor.snapshots.chart_hours', 24),
                'workerRefreshInterval' => config('queue-monitor.workers.worker_refresh_interval'),
                'allowRetry' => (bool) config('queue-monitor.ui.allow_retry', true),
                'allowDeletion' => (bool) config('queue-monitor.ui.allow_deletion', true),
                'allowPurge' => (bool) config('queue-monitor.ui.allow_purge', true),
                'allowClearQueue' => (bool) config('queue-monitor.ui.allow_clear_queue', true),
            ],
            'status' => session('status'),
            'error' => session('error'),
        ]);
    }

    // =========================================================================
    // WORKERS — JSON endpoint for Alpine.js polling on the workers panel
    // =========================================================================

    public function workers(): JsonResponse
    {
        abort_unless(config('queue-monitor.workers.enabled', true), 404);

        $stats = $this->workerMonitorService->getWorkerStats();

        return response()->json($stats);
    }

    public function bulkAction(Request $request): RedirectResponse
    {
        $rules = [
            'action' => ['required', 'string'],
            'select_all' => ['nullable', 'boolean'],
        ];

        if ($request->input('action') !== 'purge') {
            $rules['ids'] = ['required_without:select_all', 'array'];
            $rules['ids.*'] = ['required'];
        }

        $request->validate($rules);

        try {
            $result = $this->service()->handleBulkAction($request);
        } catch (RuntimeException $runtimeException) {
            report($runtimeException);

            return back()->with('error', 'Queue monitor bulk action failed.');
        }

        $this->handleBulkActionSideEffects(
            (string) $request->input('action'),
            $request->boolean('select_all') ? [] : $request->input('ids', [])
        );

        return back()->with('status', $result['message']);
    }

    // =========================================================================
    // DESTROY — Override to say "deleted" (hard delete, no trash)
    // =========================================================================

    public function destroy(int|string $id): RedirectResponse
    {
        $model = Monitor::query()->findOrFail($id);
        $model->delete();

        return to_route('app.masters.queue-monitor.index')
            ->with('status', 'Monitor entry deleted.');
    }

    // =========================================================================
    // RETRY — Custom action (not part of standard CRUD)
    // =========================================================================

    public function retry(int $id): RedirectResponse
    {
        $monitor = Monitor::query()->findOrFail($id);

        if (! $monitor->canBeRetried()) {
            return back()->with('error', 'Job cannot be retried.');
        }

        try {
            $monitor->retry();

            return back()->with('status', 'Job queued for retry.');
        } catch (Exception $exception) {
            report($exception);

            return back()->with('error', 'Unable to retry the job right now.');
        }
    }

    public function clearQueue(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'queue' => ['nullable', 'string'],
        ]);

        try {
            $result = $this->service()->clearQueuedJobs($validated['queue'] ?? null);

            return back()->with('status', $result['message']);
        } catch (RuntimeException $runtimeException) {
            report($runtimeException);

            return back()->with('error', 'Unable to clear queued jobs right now.');
        }
    }

    public function cancel(int $id): RedirectResponse
    {
        $monitor = Monitor::query()->findOrFail($id);

        if ($monitor->status !== MonitorStatus::RUNNING) {
            return back()->with('error', 'Only running jobs can be stopped.');
        }

        if (! data_get($monitor->metadata, 'cancellable', false)) {
            return back()->with('error', 'This job does not support manual stop requests.');
        }

        if (filled(data_get($monitor->metadata, 'cancel_requested_at'))) {
            return back()->with('status', 'Stop already requested for this job.');
        }

        $monitor->update([
            'metadata' => array_merge($monitor->metadata ?? [], [
                'cancel_requested_at' => now()->toIso8601String(),
            ]),
        ]);

        return back()->with('status', 'Stop requested. The job will stop after the current step.');
    }

    // =========================================================================
    // METRICS (private — used only by index)
    // =========================================================================

    private function collectMetrics(): Metrics
    {
        $timeFrame = config('queue-monitor.ui.metrics_time_frame', 2);
        $metrics = new Metrics;
        $connection = DB::connection();

        $expressionTotalTime = DB::raw('SUM(TIMESTAMPDIFF(SECOND, `started_at`, `finished_at`)) as `total_time_elapsed`');
        $expressionAverageTime = DB::raw('AVG(TIMESTAMPDIFF(SECOND, `started_at`, `finished_at`)) as `average_time_elapsed`');
        $expressionAvgWait = DB::raw('AVG(CASE WHEN `queued_at` IS NOT NULL AND `started_at` IS NOT NULL THEN TIMESTAMPDIFF(SECOND, `queued_at`, `started_at`) ELSE NULL END) as `average_wait_seconds`');

        if ($connection instanceof DatabaseConnections\SQLiteConnection) {
            $expressionTotalTime = DB::raw('SUM(strftime("%s", `finished_at`) - strftime("%s", `started_at`)) as total_time_elapsed');
            $expressionAverageTime = DB::raw('AVG(strftime("%s", `finished_at`) - strftime("%s", `started_at`)) as average_time_elapsed');
            $expressionAvgWait = DB::raw('AVG(CASE WHEN queued_at IS NOT NULL AND started_at IS NOT NULL THEN strftime("%s", started_at) - strftime("%s", queued_at) ELSE NULL END) as average_wait_seconds');
        }

        if ($connection instanceof DatabaseConnections\SqlServerConnection) {
            $expressionTotalTime = DB::raw('SUM(DATEDIFF(SECOND, "started_at", "finished_at")) as "total_time_elapsed"');
            $expressionAverageTime = DB::raw('AVG(DATEDIFF(SECOND, "started_at", "finished_at")) as "average_time_elapsed"');
            $expressionAvgWait = DB::raw('AVG(CASE WHEN [queued_at] IS NOT NULL AND [started_at] IS NOT NULL THEN DATEDIFF(SECOND, [queued_at], [started_at]) ELSE NULL END) as [average_wait_seconds]');
        }

        if ($connection instanceof DatabaseConnections\PostgresConnection) {
            $expressionTotalTime = DB::raw('SUM(EXTRACT(EPOCH FROM (finished_at - started_at))) as total_time_elapsed');
            $expressionAverageTime = DB::raw('AVG(EXTRACT(EPOCH FROM (finished_at - started_at))) as average_time_elapsed');
            $expressionAvgWait = DB::raw('AVG(CASE WHEN queued_at IS NOT NULL AND started_at IS NOT NULL THEN EXTRACT(EPOCH FROM (started_at - queued_at)) ELSE NULL END) as average_wait_seconds');
        }

        $aggregationColumns = [
            DB::raw('COUNT(*) as count'),
            $expressionTotalTime,
            $expressionAverageTime,
            $expressionAvgWait,
        ];

        $aggregatedInfo = Monitor::query()
            ->select($aggregationColumns)
            ->where('status', '!=', MonitorStatus::RUNNING)
            ->where('started_at', '>=', Date::now()->subDays($timeFrame))
            ->first();

        $aggregatedComparisonInfo = Monitor::query()
            ->select($aggregationColumns)
            ->where('status', '!=', MonitorStatus::RUNNING)
            ->where('started_at', '>=', Date::now()->subDays($timeFrame * 2))
            ->where('started_at', '<=', Date::now()->subDays($timeFrame))
            ->first();

        /**
         * @var object{total_time_elapsed: float|string, average_time_elapsed: float|string, count: int|string, average_wait_seconds: float|string|null}|null $aggregatedInfo
         * @var object{total_time_elapsed: float|string, average_time_elapsed: float|string, count: int|string, average_wait_seconds: float|string|null}|null $aggregatedComparisonInfo
         */
        if ($aggregatedInfo === null || $aggregatedComparisonInfo === null) {
            return $metrics;
        }

        return $metrics
            ->push(new Metric('Total Jobs Executed', (float) ($aggregatedInfo->count ?? 0), (float) ($aggregatedComparisonInfo->count ?? 0), '%d'))
            ->push(new Metric('Total Execution Time', (float) ($aggregatedInfo->total_time_elapsed ?? 0), (float) ($aggregatedComparisonInfo->total_time_elapsed ?? 0), '%ds'))
            ->push(new Metric('Average Execution Time', (float) ($aggregatedInfo->average_time_elapsed ?? 0), (float) ($aggregatedComparisonInfo->average_time_elapsed ?? 0), '%0.2fs'))
            ->push(new Metric('Average Wait Time', (float) ($aggregatedInfo->average_wait_seconds ?? 0), (float) ($aggregatedComparisonInfo->average_wait_seconds ?? 0), '%0.2fs'));
    }
}
