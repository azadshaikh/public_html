<?php

namespace Modules\Platform\Http\Controllers\Concerns;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Platform\Libs\HestiaClient;
use Modules\Platform\Models\Server;

trait InteractsWithServerOptimization
{
    /**
     * Get server optimization data (PostgreSQL settings + recommendations).
     */
    public function optimizationTool(int|string $id): JsonResponse
    {
        /** @var Server $server */
        $server = Server::query()->findOrFail((int) $id);

        if ($server->status !== 'active') {
            return response()->json([
                'status' => 'error',
                'message' => 'Server must be active to retrieve optimization data.',
            ], 400);
        }

        try {
            $pgData = $this->fetchPgConfig($server);

            $ramMb = (int) $pgData['ram_mb'];
            $cpuCores = (int) $pgData['cpu_cores'];
            $currentSettings = $pgData['settings'];

            $recommendations = $this->calculatePgRecommendations($ramMb, $cpuCores);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'categories' => [
                        [
                            'id' => 'postgresql',
                            'label' => 'PostgreSQL',
                            'icon' => 'ri-database-2-line',
                            'pg_version' => $pgData['pg_version'],
                            'hardware' => [
                                'ram_mb' => $ramMb,
                                'cpu_cores' => $cpuCores,
                                'storage_type' => 'ssd',
                            ],
                            'settings' => $this->buildOptimizationSettings($currentSettings, $recommendations),
                        ],
                    ],
                ],
            ]);
        } catch (Exception $exception) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve optimization data: '.$exception->getMessage(),
            ], 500);
        }
    }

    /**
     * Apply PostgreSQL optimization settings.
     */
    public function applyOptimization(Request $request, int|string $id): JsonResponse
    {
        /** @var Server $server */
        $server = Server::query()->findOrFail((int) $id);

        if ($server->status !== 'active') {
            return response()->json([
                'status' => 'error',
                'message' => 'Server must be active to apply optimizations.',
            ], 400);
        }

        $settings = $request->input('settings', []);

        if (empty($settings)) {
            return response()->json([
                'status' => 'error',
                'message' => 'No settings provided.',
            ], 422);
        }

        $allowedSettings = [
            'shared_buffers', 'effective_cache_size', 'work_mem', 'maintenance_work_mem',
            'wal_buffers', 'max_connections', 'random_page_cost', 'effective_io_concurrency',
            'max_worker_processes', 'max_parallel_workers_per_gather', 'max_parallel_workers',
            'max_parallel_maintenance_workers', 'huge_pages', 'min_wal_size', 'max_wal_size',
            'checkpoint_completion_target', 'default_statistics_target',
            'wal_compression', 'wal_log_hints', 'checkpoint_timeout', 'log_checkpoints',
            'log_temp_files', 'log_lock_waits', 'idle_in_transaction_session_timeout',
            'shared_preload_libraries',
        ];

        $settings = array_intersect_key($settings, array_flip($allowedSettings));

        if ($settings === []) {
            return response()->json([
                'status' => 'error',
                'message' => 'No valid settings provided.',
            ], 422);
        }

        try {
            $result = $this->applyPgSettings($server, $settings);

            return response()->json([
                'status' => $result['status'],
                'message' => $result['message'],
                'data' => [
                    'applied' => $result['applied'],
                    'failed' => $result['failed'],
                    'restart_required' => $result['restart_required'],
                    'restarted' => $result['restarted'],
                ],
            ]);
        } catch (Exception $exception) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to apply optimizations: '.$exception->getMessage(),
            ], 500);
        }
    }

    /**
     * @param  array<string, string>  $settings
     * @return array{status: string, message: string, applied: array, failed: array, restart_required: bool, restarted: bool}
     */
    protected function applyPgSettings(Server $server, array $settings): array
    {
        $result = HestiaClient::execute(
            'a-apply-pg-config',
            $server,
            [json_encode($settings)],
            120
        );

        if (! $result['success'] || empty($result['data'])) {
            return [
                'status' => 'error',
                'message' => $result['message'] ?? 'Failed to apply settings via HestiaClient.',
                'applied' => [],
                'failed' => array_keys($settings),
                'restart_required' => false,
                'restarted' => false,
            ];
        }

        $data = $result['data'];
        $applied = array_values(array_filter($data['applied'] ?? [], fn ($value): bool => $value !== ''));
        $failed = array_values(array_filter($data['failed'] ?? [], fn ($value): bool => $value !== ''));

        return [
            'status' => $failed === [] ? 'success' : 'partial',
            'message' => $data['message'] ?? 'Settings applied.',
            'applied' => $applied,
            'failed' => $failed,
            'restart_required' => $data['restart_required'] ?? false,
            'restarted' => $data['restarted'] ?? false,
        ];
    }

    /**
     * @return array{ram_mb: int, cpu_cores: int, pg_version: string, settings: array}
     */
    protected function fetchPgConfig(Server $server): array
    {
        $hestiaData = [];

        try {
            $result = HestiaClient::execute('a-get-pg-config', $server);
            if ($result['success'] && ! empty($result['data'])) {
                $hestiaData = $result['data'];
            }
        } catch (Exception) {
        }

        $settings = $hestiaData['settings'] ?? [];
        if (empty($settings)) {
            $settings = $this->fetchPgSettingsFromDb();
        }

        $pgVersion = $hestiaData['pg_version'] ?? '';
        if (empty($pgVersion)) {
            $db = DB::connection();
            $versionRow = $db->selectOne('SELECT version()');
            $fullVersion = $versionRow->version ?? '';
            if (preg_match('/PostgreSQL\s+([\d.]+)/', $fullVersion, $matches)) {
                $pgVersion = $matches[1];
            }
        }

        $ramMb = (int) ($hestiaData['ram_mb'] ?? $server->server_ram ?? 0);
        $cpuCores = (int) ($hestiaData['cpu_cores'] ?? $server->server_ccore ?? 1);

        return [
            'ram_mb' => $ramMb,
            'cpu_cores' => $cpuCores,
            'pg_version' => $pgVersion,
            'settings' => $settings,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function fetchPgSettingsFromDb(): array
    {
        $db = DB::connection();

        if ($db->getDriverName() !== 'pgsql') {
            return [];
        }

        $settingNames = [
            'shared_buffers', 'effective_cache_size', 'work_mem', 'maintenance_work_mem',
            'wal_buffers', 'max_connections', 'random_page_cost', 'effective_io_concurrency',
            'max_worker_processes', 'max_parallel_workers_per_gather', 'max_parallel_workers',
            'max_parallel_maintenance_workers', 'huge_pages', 'min_wal_size', 'max_wal_size',
            'checkpoint_completion_target', 'default_statistics_target', 'log_min_duration_statement',
            'wal_compression', 'wal_log_hints', 'checkpoint_timeout', 'log_checkpoints',
            'log_temp_files', 'log_lock_waits', 'idle_in_transaction_session_timeout',
            'shared_preload_libraries',
        ];

        $settings = [];
        foreach ($settingNames as $name) {
            try {
                $row = $db->selectOne('SHOW '.$name);
                $settings[$name] = $row->{$name} ?? 'N/A';
            } catch (Exception) {
                $settings[$name] = 'N/A';
            }
        }

        return $settings;
    }

    /**
     * @return array<string, array{value: string, description: string}>
     */
    protected function calculatePgRecommendations(int $ramMb, int $cpuCores): array
    {
        $ramGb = $ramMb / 1024;
        $sharedBuffersMb = min((int) ($ramMb * 0.25), 8192);
        $sharedBuffers = $sharedBuffersMb >= 1024
            ? round($sharedBuffersMb / 1024, 1).'GB'
            : $sharedBuffersMb.'MB';

        $effectiveCacheMb = (int) ($ramMb * 0.75);
        $effectiveCache = $effectiveCacheMb >= 1024
            ? round($effectiveCacheMb / 1024, 1).'GB'
            : $effectiveCacheMb.'MB';

        $maxConn = max(4 * $cpuCores, 100);
        $maxConnections = (string) $maxConn;

        $workMemMb = max(4, (int) (($ramMb - $sharedBuffersMb) / ($maxConn * 3)));
        $workMem = $workMemMb.'MB';

        $maintenanceWorkMemMb = min((int) ($ramMb / 16), 2048);
        $maintenanceWorkMem = $maintenanceWorkMemMb >= 1024
            ? round($maintenanceWorkMemMb / 1024, 1).'GB'
            : $maintenanceWorkMemMb.'MB';

        $walBuffersMb = max(1, min(64, (int) ($sharedBuffersMb * 0.03)));
        $randomPageCost = '1.1';
        $effectiveIoConcurrency = '200';
        $maxWorkerProcesses = (string) $cpuCores;
        $maxParallelWorkers = (string) $cpuCores;
        $maxParallelWorkersPerGather = (string) max(2, (int) ($cpuCores / 2));
        $maxParallelMaintenanceWorkers = (string) max(2, (int) ($cpuCores / 4));
        $hugePages = $ramGb >= 32 ? 'on' : 'off';
        $minWalSize = $ramGb >= 4 ? '1GB' : '512MB';
        $maxWalSize = $ramGb >= 8 ? '4GB' : ($ramGb >= 4 ? '2GB' : '1GB');
        $checkpointTimeout = '15min';
        $idleTimeout = '10min';

        return [
            'max_connections' => [
                'value' => $maxConnections,
                'description' => 'GREATEST(4 × CPU cores, 100). Use a pooler like pgBouncer for extra connections.',
            ],
            'shared_buffers' => [
                'value' => $sharedBuffers,
                'description' => '25% of total RAM. Primary PostgreSQL memory cache for frequently accessed data.',
            ],
            'effective_cache_size' => [
                'value' => $effectiveCache,
                'description' => '75% of total RAM. Helps the query planner estimate available caching (OS + PG).',
            ],
            'maintenance_work_mem' => [
                'value' => $maintenanceWorkMem,
                'description' => 'Memory for maintenance operations like VACUUM and CREATE INDEX.',
            ],
            'checkpoint_completion_target' => [
                'value' => '0.9',
                'description' => 'Spreads checkpoint I/O over 90% of the interval. Reduces I/O spikes.',
            ],
            'wal_buffers' => [
                'value' => $walBuffers,
                'description' => '~3% of shared_buffers. Buffers for write-ahead log entries.',
            ],
            'default_statistics_target' => [
                'value' => '100',
                'description' => 'Statistics sampling for query planner. Default 100 is good for most workloads.',
            ],
            'random_page_cost' => [
                'value' => $randomPageCost,
                'description' => 'Set low (1.1) for SSD storage — random I/O is nearly as fast as sequential.',
            ],
            'effective_io_concurrency' => [
                'value' => $effectiveIoConcurrency,
                'description' => 'High (200) for SSD — supports many concurrent I/O operations.',
            ],
            'work_mem' => [
                'value' => $workMem,
                'description' => '(RAM - shared_buffers) / (connections × 3). Memory per sort/hash operation.',
            ],
            'huge_pages' => [
                'value' => $hugePages,
                'description' => $ramGb >= 32 ? 'Enabled for large RAM systems — reduces page table overhead.' : 'Disabled — not beneficial below 32 GB RAM.',
            ],
            'min_wal_size' => [
                'value' => $minWalSize,
                'description' => 'Minimum WAL disk space retained. Higher values reduce checkpoint frequency.',
            ],
            'max_wal_size' => [
                'value' => $maxWalSize,
                'description' => 'Maximum WAL disk space before automatic checkpoint trigger.',
            ],
            'max_worker_processes' => [
                'value' => $maxWorkerProcesses,
                'description' => 'Maximum background worker processes. Matches CPU core count.',
            ],
            'max_parallel_workers_per_gather' => [
                'value' => $maxParallelWorkersPerGather,
                'description' => 'Parallel workers per query. Half of CPU cores for balanced parallelism.',
            ],
            'max_parallel_workers' => [
                'value' => $maxParallelWorkers,
                'description' => 'Total parallel workers available. Matches CPU core count.',
            ],
            'max_parallel_maintenance_workers' => [
                'value' => $maxParallelMaintenanceWorkers,
                'description' => 'Parallel workers for maintenance (VACUUM, index builds). Quarter of CPU cores.',
            ],
            'wal_compression' => [
                'value' => 'pglz',
                'description' => 'Compresses full-page writes in WAL using pglz — reduces I/O during heavy write operations.',
            ],
            'wal_log_hints' => [
                'value' => 'on',
                'description' => 'Required for pg_rewind and recovery tools. Logs hint bit changes in WAL.',
            ],
            'checkpoint_timeout' => [
                'value' => $checkpointTimeout,
                'description' => '15min reduces checkpoint I/O vs default 5min. Slight increase in crash recovery time.',
            ],
            'log_checkpoints' => [
                'value' => 'on',
                'description' => 'Logs checkpoint activity — essential for monitoring and verifying checkpoint behavior.',
            ],
            'log_temp_files' => [
                'value' => '0',
                'description' => 'Logs all temp file usage — indicates when work_mem may need adjustment.',
            ],
            'log_lock_waits' => [
                'value' => 'on',
                'description' => 'Logs queries waiting on locks — helps identify lock contention issues.',
            ],
            'idle_in_transaction_session_timeout' => [
                'value' => $idleTimeout,
                'description' => 'Terminates idle transactions after 10 minutes to prevent lock blocking.',
            ],
            'shared_preload_libraries' => [
                'value' => 'pg_stat_statements',
                'description' => 'Enables pg_stat_statements for detailed query-level performance monitoring.',
            ],
        ];
    }

    /**
     * @return array<int, array{name: string, current: string, recommended: string, description: string, status: string}>
     */
    protected function buildOptimizationSettings(array $currentSettings, array $recommendations): array
    {
        $settings = [];

        foreach ($recommendations as $name => $recommendation) {
            $current = $currentSettings[$name] ?? 'N/A';
            $recommended = $recommendation['value'];

            $currentBytes = $this->parsePostgresSize($current);
            $recommendedBytes = $this->parsePostgresSize($recommended);

            if ($current === 'N/A') {
                $status = 'unknown';
            } elseif ($name === 'shared_preload_libraries') {
                $currentLibs = array_map(trim(...), explode(',', (string) $current));
                $status = in_array($recommended, $currentLibs, true) ? 'ok' : 'needs_tuning';
            } elseif ($this->pgSizeValuesMatch($currentBytes, $recommendedBytes)) {
                $status = 'ok';
            } else {
                $status = 'needs_tuning';
            }

            $settings[] = [
                'name' => $name,
                'current' => $current,
                'recommended' => $recommended,
                'description' => $recommendation['description'],
                'status' => $status,
            ];
        }

        return $settings;
    }

    protected function pgSizeValuesMatch(int $a, int $b): bool
    {
        if ($a === $b) {
            return true;
        }

        if ($a > 1_000_000_000_000 || $b > 1_000_000_000_000) {
            return $a === $b;
        }

        $max = max($a, $b);
        if ($max === 0) {
            return true;
        }

        return abs($a - $b) / $max < 0.01;
    }

    protected function parsePostgresSize(string $value): int
    {
        $value = trim($value);

        if (is_numeric($value)) {
            return (int) $value;
        }

        if (preg_match('/^([\d.]+)\s*(kB|MB|GB|TB)$/i', $value, $matches)) {
            $number = (float) $matches[1];
            $unit = strtoupper($matches[2]);

            return (int) match ($unit) {
                'KB' => $number * 1024,
                'MB' => $number * 1024 * 1024,
                'GB' => $number * 1024 * 1024 * 1024,
                'TB' => $number * 1024 * 1024 * 1024 * 1024,
                default => $number,
            };
        }

        return crc32($value);
    }
}
