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

        $metrics = config('queue-monitor.ui.show_metrics') ? $this->collectMetrics() : null;
        $data = $this->service()->getData($request);
        $queueStats = $this->service()->getQueueStats();
        $chartData = config('queue-monitor.snapshots.enabled', true)
            ? $this->service()->getChartData((int) (config('queue-monitor.snapshots.chart_hours', 24)))
            : null;
        $workerStats = config('queue-monitor.workers.enabled', true)
            ? $this->workerMonitorService->getWorkerStats()
            : null;

        return Inertia::render($this->inertiaPage().'/index', [
            ...$data,
            'metrics' => $metrics,
            'queueStats' => $queueStats,
            'chartData' => $chartData,
            'workerStats' => $workerStats,
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

    public function retry(Request $request, int $id): JsonResponse
    {
        $monitor = Monitor::query()->findOrFail($id);

        if (! $monitor->canBeRetried()) {
            return response()->json(['status' => 'error', 'message' => 'Job cannot be retried.'], 400);
        }

        try {
            $monitor->retry();

            return response()->json(['status' => 'success', 'message' => 'Job queued for retry.']);
        } catch (Exception $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()], 500);
        }
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
