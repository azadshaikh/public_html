<?php

namespace App\Http\Controllers\Masters;

use App\Http\Controllers\Controller;
use App\Services\LaravelTools\ArtisanService;
use App\Services\LaravelTools\ConfigService;
use App\Services\LaravelTools\EnvService;
use App\Services\LaravelTools\PhpService;
use App\Services\LaravelTools\RouteService;
use App\Support\Auth\SuperUserAccess;
use App\Traits\ActivityTrait;
use App\Traits\HasAlerts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LaravelToolsController extends Controller
{
    use ActivityTrait;
    use HasAlerts;

    public function __construct(
        protected EnvService $envService,
        protected ArtisanService $artisanService,
        protected ConfigService $configService,
        protected RouteService $routeService,
        protected PhpService $phpService,
    ) {}

    // =========================================================================
    // Dashboard
    // =========================================================================

    /**
     * Dashboard index with overview stats
     */
    public function index(): Response
    {
        $this->authorizeAccess();

        return Inertia::render('masters/laravel-tools/index', [
            'stats' => $this->getSystemStats(),
            'status' => session('status'),
            'error' => session('error'),
        ]);
    }

    // =========================================================================
    // ENV Editor
    // =========================================================================

    /**
     * ENV Editor view
     */
    public function envEditor(): Response
    {
        $this->authorizeAccess();

        return Inertia::render('masters/laravel-tools/env', [
            'envContent' => $this->envService->getContent(),
            'protectedKeys' => $this->envService->getProtectedKeys(),
            'backups' => $this->envService->getBackups(),
            'status' => session('status'),
            'error' => session('error'),
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
    public function getEnvBackupsList(): JsonResponse
    {
        $this->authorizeAccess();

        return response()->json([
            'success' => true,
            'backups' => $this->envService->getBackups(),
        ]);
    }

    // =========================================================================
    // Artisan Runner
    // =========================================================================

    /**
     * Artisan Runner view
     */
    public function artisanRunner(): Response
    {
        $this->authorizeAccess();

        return Inertia::render('masters/laravel-tools/artisan', [
            'commands' => collect($this->artisanService->getSafeCommands())
                ->map(fn (string $description, string $command): array => [
                    'name' => $command,
                    'description' => $description,
                ])
                ->values()
                ->all(),
            'status' => session('status'),
            'error' => session('error'),
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
    public function configBrowser(Request $request): Response
    {
        $this->authorizeAccess();

        $configFiles = collect($this->configService->getFiles())
            ->map(fn (array $file): array => ['name' => $file['name']])
            ->values()
            ->all();

        $selectedFile = $this->resolveSelectedConfigFile($configFiles, $request->string('file')->toString());
        $selectedConfig = null;

        if ($selectedFile !== null) {
            $result = $this->configService->getValues($selectedFile);
            if ($result['success']) {
                $selectedConfig = $result['config'];
            }
        }

        return Inertia::render('masters/laravel-tools/config', [
            'configFiles' => $configFiles,
            'selectedFile' => $selectedFile,
            'selectedConfig' => $selectedConfig,
            'status' => session('status'),
            'error' => session('error'),
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
    // Route List
    // =========================================================================

    /**
     * Route List view
     */
    public function routeList(Request $request): Response
    {
        $this->authorizeAccess();

        $search = $request->string('search')->toString();
        $method = $request->string('method')->toString() ?: 'all';
        $sort = $request->string('sort')->toString() ?: 'uri';
        $direction = $request->string('direction')->toString() === 'desc' ? 'desc' : 'asc';
        $perPage = max(10, min((int) $request->input('per_page', 25), 100));
        $result = $this->routeService->getRoutes($search, $method, $sort, $direction, $perPage);

        return Inertia::render('masters/laravel-tools/routes', [
            'routes' => $result['routes'],
            'total' => $result['total'],
            'filters' => [
                'search' => $search,
                'method' => $method,
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => $perPage,
            ],
            'status' => session('status'),
            'error' => session('error'),
        ]);
    }

    // =========================================================================
    // PHP Diagnostics
    // =========================================================================

    /**
     * PHP diagnostics view.
     */
    public function phpDiagnostics(): Response
    {
        $this->authorizeAccess();

        return Inertia::render('masters/laravel-tools/php', [
            'summary' => $this->phpService->getSummary(),
            'settingGroups' => $this->phpService->getSettingGroups(),
            'extensions' => $this->phpService->getExtensions(),
            'pdoDrivers' => $this->phpService->getPdoDrivers(),
            'status' => session('status'),
            'error' => session('error'),
        ]);
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    /**
     * Authorize access to Laravel Tools (Super Users only)
     */
    protected function authorizeAccess(): void
    {
        abort_unless(SuperUserAccess::allows(auth()->user()), 403);
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
            'database_connection' => config('database.default'),
            'timezone' => config('app.timezone'),
            'locale' => config('app.locale'),
        ];
    }

    /**
     * Resolve the selected config file.
     */
    protected function resolveSelectedConfigFile(array $configFiles, string $requestedFile): ?string
    {
        $availableFiles = collect($configFiles)->pluck('name')->all();

        if ($requestedFile !== '' && in_array($requestedFile, $availableFiles, true)) {
            return $requestedFile;
        }

        return $configFiles[0]['name'] ?? null;
    }
}
