<?php

namespace App\Http\Controllers\Masters;

use App\Enums\MonitorStatus;
use App\Http\Controllers\Controller;
use App\Models\Monitor;
use App\Services\LaravelTools\ArtisanService;
use App\Services\LaravelTools\ConfigService;
use App\Services\LaravelTools\EnvService;
use App\Services\LaravelTools\LogService;
use App\Services\LaravelTools\PhpService;
use App\Services\LaravelTools\RouteService;
use App\Traits\ActivityTrait;
use App\Traits\HasAlerts;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

class LaravelToolsController extends Controller
{
    use ActivityTrait;
    use HasAlerts;

    public function __construct(
        protected EnvService $envService,
        protected ArtisanService $artisanService,
        protected ConfigService $configService,
        protected LogService $logService,
        protected RouteService $routeService,
        protected PhpService $phpService,
    ) {}

    // =========================================================================
    // Dashboard
    // =========================================================================

    /**
     * Dashboard index with overview stats
     */
    public function index(): View
    {
        $this->authorizeAccess();

        return view('app.masters.laravel-tools.index', [
            'page_title' => __('Laravel Tools'),
            'stats' => $this->getSystemStats(),
            'tools' => $this->getToolsList(),
        ]);
    }

    // =========================================================================
    // ENV Editor
    // =========================================================================

    /**
     * ENV Editor view
     */
    public function envEditor(): View
    {
        $this->authorizeAccess();

        return view('app.masters.laravel-tools.env', [
            'page_title' => __('ENV Editor'),
            'envContent' => $this->envService->getContent(),
            'protectedKeys' => $this->envService->getProtectedKeys(),
            'backups' => $this->envService->getBackups(),
        ]);
    }

    /**
     * Update ENV file
     */
    public function updateEnv(Request $request): JsonResponse
    {
        $this->authorizeAccess();

        $result = $this->envService->update($request->input('content', ''));

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error'] ?? implode(', ', $result['errors'] ?? []),
            ], 422);
        }

        activity()
            ->causedBy(auth()->user())
            ->log('Updated .env file');

        return response()->json([
            'success' => true,
            'message' => __('ENV file updated successfully. Backup created.'),
        ]);
    }

    /**
     * Restore ENV from backup
     */
    public function restoreEnvBackup(Request $request): JsonResponse
    {
        $this->authorizeAccess();

        $result = $this->envService->restoreBackup($request->input('backup', ''));

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error'],
            ], 404);
        }

        activity()
            ->causedBy(auth()->user())
            ->withProperties(['backup' => $request->input('backup')])
            ->log('Restored .env from backup');

        return response()->json([
            'success' => true,
            'message' => __('ENV file restored from backup successfully.'),
            'content' => $result['content'],
        ]);
    }

    /**
     * Get ENV backups list (AJAX)
     */
    public function getEnvBackupsList(): View|string
    {
        $this->authorizeAccess();

        return view('app.masters.laravel-tools.partials.backup-list', [
            'backups' => $this->envService->getBackups(),
        ])->render();
    }

    // =========================================================================
    // Artisan Runner
    // =========================================================================

    /**
     * Artisan Runner view
     */
    public function artisanRunner(): View
    {
        $this->authorizeAccess();

        return view('app.masters.laravel-tools.artisan', [
            'page_title' => __('Artisan Runner'),
            'commands' => $this->artisanService->getSafeCommands(),
        ]);
    }

    /**
     * Run Artisan command
     */
    public function runArtisan(Request $request): JsonResponse
    {
        $this->authorizeAccess();

        $command = $request->input('command');
        $result = $this->artisanService->run($command);

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error'],
            ], 403);
        }

        activity()
            ->causedBy(auth()->user())
            ->withProperties(['command' => $command])
            ->log('Executed artisan command');

        return response()->json([
            'success' => true,
            'output' => $result['output'],
            'duration' => $result['duration'],
            'message' => $result['message'],
        ]);
    }

    // =========================================================================
    // Config Browser
    // =========================================================================

    /**
     * Config Browser view
     */
    public function configBrowser(): View
    {
        $this->authorizeAccess();

        return view('app.masters.laravel-tools.config', [
            'page_title' => __('Config Browser'),
            'configFiles' => $this->configService->getFiles(),
        ]);
    }

    /**
     * Get config values for a specific file
     */
    public function getConfigValues(Request $request): JsonResponse
    {
        $this->authorizeAccess();

        $result = $this->configService->getValues($request->input('file', ''));

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error'],
            ], 404);
        }

        return response()->json([
            'success' => true,
            'config' => $result['config'],
        ]);
    }

    // =========================================================================
    // Log Viewer
    // =========================================================================

    /**
     * Log Viewer
     */
    public function logViewer(): View
    {
        $this->authorizeAccess();

        return view('app.masters.laravel-tools.logs', [
            'page_title' => __('Log Viewer'),
            'logFiles' => $this->logService->getFiles(),
        ]);
    }

    /**
     * Get log entries
     */
    public function getLogEntries(Request $request): JsonResponse
    {
        $this->authorizeAccess();

        $result = $this->logService->getEntries(
            $request->input('file', 'laravel.log'),
            $request->input('level', 'all'),
            $request->input('lines', 100)
        );

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error'],
            ], 404);
        }

        return response()->json([
            'success' => true,
            'entries' => $result['entries'],
            'levels' => $result['levels'],
        ]);
    }

    /**
     * Delete log file
     */
    public function deleteLog(string $filename): JsonResponse
    {
        $this->authorizeAccess();

        $result = $this->logService->delete($filename);

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error'],
            ], 404);
        }

        activity()
            ->causedBy(auth()->user())
            ->withProperties(['file' => $filename])
            ->log('Deleted log file');

        return response()->json([
            'success' => true,
            'message' => $result['message'],
        ]);
    }

    // =========================================================================
    // Route List
    // =========================================================================

    /**
     * Route List view
     */
    public function routeList(): View
    {
        $this->authorizeAccess();

        return view('app.masters.laravel-tools.routes', [
            'page_title' => __('Route List'),
        ]);
    }

    /**
     * Get routes list
     */
    public function getRoutes(Request $request): JsonResponse
    {
        $this->authorizeAccess();

        $result = $this->routeService->getRoutes(
            $request->input('search') ?? '',
            $request->input('method') ?? 'all'
        );

        return response()->json([
            'success' => true,
            'routes' => $result['routes'],
            'total' => $result['total'],
        ]);
    }

    // =========================================================================
    // PHP Diagnostics
    // =========================================================================

    /**
     * PHP diagnostics view.
     */
    public function phpDiagnostics(): View
    {
        $this->authorizeAccess();

        return view('app.masters.laravel-tools.php', [
            'page_title' => __('PHP Diagnostics'),
            'summary' => $this->phpService->getSummary(),
            'settingGroups' => $this->phpService->getSettingGroups(),
            'extensions' => $this->phpService->getExtensions(),
            'pdoDrivers' => $this->phpService->getPdoDrivers(),
        ]);
    }

    // =========================================================================
    // Laravel Queue
    // =========================================================================

    /**
     * Laravel Queue monitor view.
     */
    public function queueMonitor(): View
    {
        $this->authorizeAccess();

        return view('app.masters.laravel-tools.queue', [
            'page_title' => __('Laravel Queue'),
            'config' => $this->getQueueConfig(),
            'statusTabs' => $this->getQueueStatusTabs(),
        ]);
    }

    /**
     * Get queue monitor data for DataGrid.
     */
    public function getQueueData(Request $request): JsonResponse
    {
        $this->authorizeAccess();

        $query = Monitor::query()->latest();

        // Status filter
        $status = $request->input('status', 'all');
        match ($status) {
            'failed' => $query->failed(),
            'succeeded' => $query->succeeded(),
            'pending' => $query->whereIn('status', [
                MonitorStatus::RUNNING,
                MonitorStatus::STALE,
                MonitorStatus::QUEUED,
            ]),
            default => null,
        };

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'ilike', sprintf('%%%s%%', $search))
                    ->orWhere('job_id', 'ilike', sprintf('%%%s%%', $search))
                    ->orWhere('exception_message', 'ilike', sprintf('%%%s%%', $search));
            });
        }

        // Pagination
        $perPage = $request->input('per_page', 35);
        $monitors = $query->paginate($perPage);

        return response()->json([
            'data' => $monitors->items(),
            'meta' => [
                'current_page' => $monitors->currentPage(),
                'last_page' => $monitors->lastPage(),
                'per_page' => $monitors->perPage(),
                'total' => $monitors->total(),
            ],
        ]);
    }

    /**
     * Retry a failed queue job.
     */
    public function retryQueueJob(Monitor $monitor): JsonResponse
    {
        $this->authorizeAccess();

        if ((int) $monitor->status !== MonitorStatus::FAILED) {
            return response()->json(['success' => false, 'message' => 'Job is not failed'], 400);
        }

        try {
            $monitor->retry();

            return response()->json(['success' => true, 'message' => 'Job queued for retry']);
        } catch (Exception $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    /**
     * Delete a queue monitor entry.
     */
    public function deleteQueueJob(Monitor $monitor): JsonResponse
    {
        $this->authorizeAccess();

        $monitor->delete();

        return response()->json(['success' => true, 'message' => 'Queue entry deleted']);
    }

    /**
     * Purge all queue monitor entries.
     */
    public function purgeQueueJobs(Request $request): JsonResponse
    {
        $this->authorizeAccess();

        $type = $request->input('type', 'all');

        $query = Monitor::query();

        match ($type) {
            'failed' => $query->failed(),
            'succeeded' => $query->succeeded(),
            default => null,
        };

        $count = $query->count();
        $query->delete();

        return response()->json(['success' => true, 'message' => sprintf('Purged %d entries', $count)]);
    }

    /**
     * Get DataGrid config for queue monitor.
     */
    protected function getQueueConfig(): array
    {
        return [
            'columns' => [
                [
                    'key' => 'id',
                    'label' => 'ID',
                    'sortable' => true,
                    'width' => '80px',
                ],
                [
                    'key' => 'name',
                    'label' => 'Job',
                    'sortable' => true,
                    'searchable' => true,
                ],
                [
                    'key' => 'status',
                    'label' => 'Status',
                    'sortable' => true,
                    'template' => 'badge',
                ],
                [
                    'key' => 'queue',
                    'label' => 'Queue',
                    'sortable' => true,
                ],
                [
                    'key' => 'attempt',
                    'label' => 'Attempts',
                    'sortable' => true,
                    'center' => true,
                ],
                [
                    'key' => 'time_elapsed',
                    'label' => 'Duration',
                    'sortable' => true,
                    'callback' => fn ($value): string => $value ? number_format($value, 2).'s' : '-',
                ],
                [
                    'key' => 'created_at',
                    'label' => 'Created',
                    'sortable' => true,
                    'datetime' => true,
                ],
                [
                    'key' => '_actions',
                    'label' => 'Actions',
                    'template' => 'actions',
                    'excludeFromExport' => true,
                ],
            ],
            'actions' => [
                'row' => [
                    [
                        'label' => 'Retry',
                        'icon' => 'ri-refresh-line',
                        'href' => '#',
                        'action' => 'retry',
                        'condition' => 'row.failed',
                    ],
                    [
                        'label' => 'Delete',
                        'icon' => 'ri-delete-bin-line',
                        'action' => 'delete',
                        'class' => 'text-danger',
                    ],
                ],
                'bulk' => [
                    [
                        'label' => 'Delete Selected',
                        'action' => 'delete',
                        'class' => 'btn-outline-danger',
                    ],
                ],
            ],
        ];
    }

    /**
     * Get status tabs for queue monitor.
     */
    protected function getQueueStatusTabs(): array
    {
        return [
            ['key' => 'all', 'label' => 'All', 'icon' => 'ri-list-check', 'color' => 'primary'],
            ['key' => 'succeeded', 'label' => 'Succeeded', 'icon' => 'ri-checkbox-circle-line', 'color' => 'success'],
            ['key' => 'failed', 'label' => 'Failed', 'icon' => 'ri-error-warning-line', 'color' => 'danger'],
            ['key' => 'pending', 'label' => 'Pending', 'icon' => 'ri-time-line', 'color' => 'warning'],
        ];
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    /**
     * Authorize access to Laravel Tools (Super Users only)
     */
    protected function authorizeAccess(): void
    {
        abort_unless(auth()->user()?->isSuperUser(), 403);
    }

    /**
     * Get system statistics
     */
    protected function getSystemStats(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'environment' => app()->environment(),
            'debug_mode' => config('app.debug'),
            'cache_driver' => config('cache.default'),
            'cache_prefix' => config('cache.prefix') ?: '(none)',
            'session_driver' => config('session.driver'),
            'queue_driver' => config('queue.default'),
            'database_connection' => config('database.default'),
            'timezone' => config('app.timezone'),
            'locale' => config('app.locale'),
        ];
    }

    /**
     * Get list of available tools
     */
    protected function getToolsList(): array
    {
        $tools = [
            [
                'name' => 'ENV Editor',
                'description' => 'Edit environment variables with syntax highlighting',
                'icon' => 'ri-file-settings-line',
                'route' => 'app.masters.laravel-tools.env',
                'color' => 'primary',
            ],
            [
                'name' => 'Artisan Runner',
                'description' => 'Execute safe artisan commands',
                'icon' => 'ri-terminal-box-line',
                'route' => 'app.masters.laravel-tools.artisan',
                'color' => 'warning',
            ],
            [
                'name' => 'Config Browser',
                'description' => 'Browse all configuration values',
                'icon' => 'ri-settings-3-line',
                'route' => 'app.masters.laravel-tools.config',
                'color' => 'info',
            ],
            [
                'name' => 'Log Viewer',
                'description' => 'View and search Laravel logs',
                'icon' => 'ri-file-list-3-line',
                'route' => 'app.masters.laravel-tools.logs',
                'color' => 'danger',
            ],
            [
                'name' => 'Laravel Queue',
                'description' => 'Monitor queue jobs and manage failed jobs',
                'icon' => 'ri-stack-line',
                'route' => 'app.masters.laravel-tools.queue',
                'color' => 'info',
            ],
            [
                'name' => 'Route List',
                'description' => 'Browse all registered routes',
                'icon' => 'ri-route-line',
                'route' => 'app.masters.laravel-tools.routes',
                'color' => 'primary',
            ],
            [
                'name' => 'Queue Monitor',
                'description' => 'Monitor queue jobs and manage failed jobs',
                'icon' => 'ri-stack-line',
                'route' => 'app.masters.queue-monitor.index',
                'color' => 'info',
            ],
            [
                'name' => 'PHP Diagnostics',
                'description' => 'Inspect PHP runtime, ini settings, and loaded extensions',
                'icon' => 'ri-bug-line',
                'route' => 'app.masters.laravel-tools.php',
                'color' => 'secondary',
            ],
        ];

        return array_values(array_filter($tools, fn (array $tool): bool => Route::has($tool['route'])));
    }
}
