<?php

declare(strict_types=1);

namespace App\Http\Controllers\Logs;

use App\Models\LoginAttempt;
use App\Scaffold\ScaffoldController;
use App\Services\LoginAttemptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class LoginAttemptController extends ScaffoldController implements HasMiddleware
{
    public function __construct(
        private readonly LoginAttemptService $loginAttemptService
    ) {}

    // ================================================================
    // MIDDLEWARE (Permission control)
    // ================================================================

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view_login_attempts', only: ['index', 'show', 'data']),
            new Middleware('permission:delete_login_attempts', only: ['destroy', 'bulkAction', 'restore', 'forceDelete']),
            new Middleware('permission:manage_login_attempts', only: ['cleanup', 'clearRateLimit', 'getBlockedIps']),
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

        return view($this->scaffold()->getIndexView(), [
            'config' => $config,
            'statistics' => $statistics,
            'initialData' => $initialData,
        ]);
    }

    public function show(int|string $id): View|JsonResponse
    {
        /** @var LoginAttempt $loginAttempt */
        $loginAttempt = $this->findModel((int) $id);

        if (request()->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'data' => $loginAttempt,
            ]);
        }

        $recentActivity = $this->service()->getRecentActivityStats($loginAttempt);

        return view($this->scaffold()->getShowView(), [
            'loginAttempt' => $loginAttempt,
            'recentEmailStats' => $recentActivity['email'],
            'recentIpStats' => $recentActivity['ip'],
        ]);
    }

    // ================================================================
    // CUSTOM: Cleanup old attempts
    // ================================================================

    public function cleanup(Request $request): JsonResponse
    {
        $daysToKeep = $request->input('days_to_keep', 30);
        $deletedCount = $this->service()->cleanupOldAttempts((int) $daysToKeep);

        return response()->json([
            'status' => 'success',
            'message' => sprintf('%d login attempt(s) older than %s days have been deleted.', $deletedCount, $daysToKeep),
            'deleted_count' => $deletedCount,
        ]);
    }

    // ================================================================
    // CUSTOM: Clear rate limit
    // ================================================================

    public function clearRateLimit(Request $request): JsonResponse
    {
        $ipAddress = $request->input('ip_address');
        $clearAll = $request->boolean('clear_all');

        $count = $this->service()->clearRateLimit($ipAddress, $clearAll);

        if (! $clearAll && $count === 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unable to clear rate limit for this IP.',
            ], 403);
        }

        $message = $clearAll
            ? sprintf('Rate limits cleared for %d IP address(es).', $count)
            : 'Rate limit cleared for IP: '.$ipAddress;

        return response()->json([
            'status' => 'success',
            'message' => $message,
            'count' => $count,
        ]);
    }

    // ================================================================
    // CUSTOM: Get blocked IPs
    // ================================================================

    public function getBlockedIps(): JsonResponse
    {
        $blockedIps = $this->service()->getBlockedIps();

        return response()->json([
            'status' => 'success',
            'data' => $blockedIps,
        ]);
    }

    // ================================================================
    // REQUIRED: Return the service
    // ================================================================

    protected function service(): LoginAttemptService
    {
        return $this->loginAttemptService;
    }
}
