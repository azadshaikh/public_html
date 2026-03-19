<?php

declare(strict_types=1);

namespace App\Http\Controllers\Logs;

use App\Models\LoginAttempt;
use App\Scaffold\ScaffoldController;
use App\Services\LoginAttemptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

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
            new Middleware('permission:view_login_attempts', only: ['index', 'show']),
            new Middleware('permission:delete_login_attempts', only: ['destroy', 'bulkAction', 'restore', 'forceDelete']),
            new Middleware('permission:manage_login_attempts', only: ['cleanup', 'clearRateLimit', 'getBlockedIps']),
        ];
    }

    // ================================================================
    // OVERRIDE: Custom index with statistics
    // ================================================================

    public function index(Request $request): Response|RedirectResponse
    {
        $this->enforcePermission('view');
        $filters = $this->loginAttemptService->collectRequestFilters($request);

        return Inertia::render($this->inertiaPage().'/index', [
            'config' => $this->service()->getInertiaConfig(),
            'loginAttempts' => $this->loginAttemptService->getPaginatedAttempts($request),
            'statistics' => $this->loginAttemptService->getStatistics(),
            'filters' => $filters,
            'status' => session('status'),
            'error' => session('error'),
        ]);
    }

    public function show(int|string $id): Response
    {
        $this->enforcePermission('view');

        /** @var LoginAttempt $loginAttempt */
        $loginAttempt = $this->findModel((int) $id);
        $recentActivity = $this->service()->getRecentActivityStats($loginAttempt);

        return Inertia::render($this->inertiaPage().'/show', [
            'loginAttempt' => $loginAttempt->toArray(),
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

    protected function inertiaPage(): string
    {
        return 'logs/login-attempts';
    }
}
