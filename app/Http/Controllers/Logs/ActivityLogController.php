<?php

declare(strict_types=1);

namespace App\Http\Controllers\Logs;

use App\Models\ActivityLog;
use App\Scaffold\ScaffoldController;
use App\Services\ActivityLogService;
use App\Traits\ActivityTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

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
            new Middleware('permission:view_activity_logs', only: ['index', 'show']),
            new Middleware('permission:delete_activity_logs', only: ['destroy', 'bulkAction', 'restore', 'forceDelete']),
            new Middleware('permission:manage_activity_logs', only: ['cleanup', 'export']),
        ];
    }

    // ================================================================
    // OVERRIDE: Custom index with statistics
    // ================================================================

    public function index(Request $request): Response|RedirectResponse
    {
        $this->enforcePermission('view');

        $status = $request->input('status') ?? $request->route('status') ?? 'all';
        $perPage = $this->service()->getScaffoldDefinition()->getPerPage();

        return Inertia::render($this->inertiaPage().'/index', [
            'logs' => $this->activityLogService->getPaginatedLogs($request),
            'statistics' => $this->activityLogService->getStatistics(),
            'filterOptions' => [
                'event' => $this->activityLogService->getEventOptions(),
                'causer_id' => $this->activityLogService->getUserOptions(),
            ],
            'filters' => [
                'search' => $request->input('search', ''),
                'status' => $status,
                'event' => $request->input('event', ''),
                'causer_id' => $request->input('causer_id', ''),
                'sort' => $request->input('sort', 'created_at'),
                'direction' => $request->input('direction', 'desc'),
                'per_page' => (int) $request->input('per_page', $perPage),
                'view' => $request->input('view', 'table'),
            ],
            'status' => session('status'),
            'error' => session('error'),
        ]);
    }

    // ================================================================
    // OVERRIDE: Custom show with changes summary
    // ================================================================

    public function show(int|string $id): Response
    {
        $this->enforcePermission('view');

        /** @var ActivityLog $activityLog */
        $activityLog = $this->findModel((int) $id);

        $data = [
            'activityLog' => $activityLog->toArray(),
        ];

        if ($activityLog->hasTrackedChanges()) {
            $data['changes_summary'] = $activityLog->getChangesSummary();
        }

        return Inertia::render($this->inertiaPage().'/show', $data);
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

    protected function inertiaPage(): string
    {
        return 'logs/activity-logs';
    }
}
