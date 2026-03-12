<?php

declare(strict_types=1);

namespace App\Http\Controllers\Logs;

use App\Scaffold\ScaffoldController;
use App\Services\ActivityLogService;
use App\Traits\ActivityTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class ActivityLogController extends ScaffoldController implements HasMiddleware
{
    use ActivityTrait;

    public function __construct(
        private readonly ActivityLogService $activityLogService
    ) {}

    // ================================================================
    // MIDDLEWARE (Permission control)
    // ================================================================

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view_activity_logs', only: ['index', 'show', 'data']),
            new Middleware('permission:delete_activity_logs', only: ['destroy', 'bulkAction', 'restore', 'forceDelete']),
            new Middleware('permission:manage_activity_logs', only: ['cleanup', 'export']),
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
        $initialData = $this->service()->getData($request);
        $config = $this->service()->getDataGridConfig();
        $statistics = $initialData['statistics'] ?? $this->service()->getStatistics();

        // Get filter options
        $filterOptions = [
            'event' => $this->service()->getEventOptions(),
            'causer_id' => $this->service()->getUserOptions(),
        ];

        return view($this->scaffold()->getIndexView(), [
            'config' => $config,
            'statistics' => $statistics,
            'initialData' => $initialData,
            'filterOptions' => $filterOptions,
        ]);
    }

    // ================================================================
    // CUSTOM: Cleanup old logs
    // ================================================================

    public function cleanup(Request $request): JsonResponse
    {
        $daysToKeep = $request->input('days_to_keep', 365);
        $deletedCount = $this->service()->cleanupOldLogs((int) $daysToKeep);

        return response()->json([
            'status' => 'success',
            'message' => sprintf('%d activity log(s) older than %s days have been deleted.', $deletedCount, $daysToKeep),
            'deleted_count' => $deletedCount,
        ]);
    }

    // ================================================================
    // CUSTOM: Export logs
    // ================================================================

    public function export(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'event', 'causer_id', 'limit']);
        $filters['limit'] = $request->input('limit', 1000);

        $data = $this->service()->exportActivities($filters);

        return response()->json([
            'status' => 'success',
            'data' => $data,
            'count' => count($data),
            'format' => $request->input('format', 'json'),
        ]);
    }

    // ================================================================
    // REQUIRED: Return the service
    // ================================================================

    protected function service(): ActivityLogService
    {
        return $this->activityLogService;
    }
}
