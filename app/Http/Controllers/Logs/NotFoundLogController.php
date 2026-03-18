<?php

declare(strict_types=1);

namespace App\Http\Controllers\Logs;

use App\Models\NotFoundLog;
use App\Scaffold\ScaffoldController;
use App\Services\NotFoundLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

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
            new Middleware('permission:view_not_found_logs', only: ['index', 'show']),
            new Middleware('permission:delete_not_found_logs', only: ['destroy', 'bulkAction', 'restore', 'forceDelete']),
            new Middleware('permission:manage_not_found_logs', only: ['cleanup', 'statistics']),
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
            'config' => $this->service()->getScaffoldDefinition()->toInertiaConfig(),
            'notFoundLogs' => $this->notFoundLogService->getPaginatedLogs($request),
            'statistics' => $this->notFoundLogService->getStatistics(),
            'filters' => [
                'search' => $request->input('search', ''),
                'status' => $status,
                'sort' => $request->input('sort', 'created_at'),
                'direction' => $request->input('direction', 'desc'),
                'per_page' => (int) $request->input('per_page', $perPage),
                'view' => $request->input('view', 'table'),
            ],
            'status' => session('status'),
            'error' => session('error'),
        ]);
    }

    public function show(int|string $id): Response
    {
        $this->enforcePermission('view');

        /** @var NotFoundLog $notFoundLog */
        $notFoundLog = $this->findModel((int) $id);
        $recentActivity = $this->service()->getRecentActivityStats($notFoundLog);

        return Inertia::render($this->inertiaPage().'/show', [
            'notFoundLog' => $notFoundLog->toArray(),
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

    protected function inertiaPage(): string
    {
        return 'logs/not-found-logs';
    }
}
