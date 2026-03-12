<?php

declare(strict_types=1);

namespace App\Http\Controllers\Logs;

use App\Models\NotFoundLog;
use App\Scaffold\ScaffoldController;
use App\Services\NotFoundLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class NotFoundLogController extends ScaffoldController implements HasMiddleware
{
    public function __construct(
        private readonly NotFoundLogService $notFoundLogService
    ) {}

    // ================================================================
    // MIDDLEWARE (Permission control)
    // ================================================================

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view_not_found_logs', only: ['index', 'show', 'data']),
            new Middleware('permission:delete_not_found_logs', only: ['destroy', 'bulkAction', 'restore', 'forceDelete']),
            new Middleware('permission:manage_not_found_logs', only: ['cleanup', 'statistics']),
        ];
    }

    // ================================================================
    // OVERRIDE: Custom index with statistics
    // ================================================================

    public function index(Request $request): View|JsonResponse
    {
        // If this is an AJAX/JSON request, return data
        if ($request->expectsJson() || $request->ajax()) {
            return $this->data($request);
        }

        // Get config from service (Scaffoldable trait provides getDataGridConfig)
        $config = $this->service()->getDataGridConfig();
        $statistics = $this->service()->getStatistics();

        // Get initial data for SSR (avoids extra AJAX request on page load)
        $initialData = $this->service()->getData($request);

        return view($this->scaffold()->getIndexView(), [
            'config' => $config,
            'statistics' => $statistics,
            'initialData' => $initialData,
        ]);
    }

    public function show(int|string $id): View|JsonResponse
    {
        /** @var NotFoundLog $notFoundLog */
        $notFoundLog = $this->findModel((int) $id);

        if (request()->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'data' => $notFoundLog,
            ]);
        }

        $recentActivity = $this->service()->getRecentActivityStats($notFoundLog);

        return view($this->scaffold()->getShowView(), [
            'notFoundLog' => $notFoundLog,
            'recentUrlStats' => $recentActivity['url'],
            'recentIpStats' => $recentActivity['ip'],
        ]);
    }

    // ================================================================
    // CUSTOM: Cleanup old logs
    // ================================================================

    public function cleanup(Request $request): JsonResponse
    {
        $daysToKeep = (int) $request->input('days_to_keep', 30);

        // Validate days_to_keep: minimum 1 day, maximum 365 days
        if ($daysToKeep < 1) {
            return response()->json([
                'status' => 'error',
                'message' => 'Days to keep must be at least 1.',
            ], 422);
        }

        if ($daysToKeep > 365) {
            return response()->json([
                'status' => 'error',
                'message' => 'Days to keep cannot exceed 365.',
            ], 422);
        }

        $deletedCount = $this->service()->cleanupOldLogs($daysToKeep);

        return response()->json([
            'status' => 'success',
            'message' => sprintf('%d 404 log(s) older than %d days have been deleted.', $deletedCount, $daysToKeep),
            'deleted_count' => $deletedCount,
        ]);
    }

    // ================================================================
    // CUSTOM: Get extended statistics
    // ================================================================

    public function statistics(Request $request): JsonResponse
    {
        $days = $request->input('days', 30);
        $statistics = $this->service()->getExtendedStatistics((int) $days);

        return response()->json([
            'status' => 'success',
            'data' => $statistics,
        ]);
    }

    // ================================================================
    // REQUIRED: Return the service
    // ================================================================

    protected function service(): NotFoundLogService
    {
        return $this->notFoundLogService;
    }
}
