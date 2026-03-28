<?php

namespace Modules\Platform\Jobs\Concerns;

use Exception;
use Illuminate\Support\Facades\Log;
use Modules\Platform\Libs\HestiaClient;
use Modules\Platform\Models\Server;
use Modules\Platform\Services\ServerService;
use Modules\Platform\Services\ServerSSHService;

trait InteractsWithServerProvisionReleaseSync
{
    /**
     * Persist release API key on provisioned server for a-sync-releases.
     */
    protected function configureReleaseApiKey(Server $server, ServerSSHService $sshService): array
    {
        $releaseApiKey = trim((string) ($server->getSecretValue('release_api_key') ?? $server->getMetadata('release_api_key') ?? ''));

        if ($releaseApiKey === '') {
            $releaseApiKey = $this->resolveDefaultReleaseApiKey();
        }

        throw_if($releaseApiKey === '', Exception::class, 'Release API key is missing. Set RELEASE_API_KEY in application environment or provide release_api_key in server form.');

        $isInsecureSync = $server->isLocalhostType() ? '1' : '0';

        $writeKeyCmd = sprintf(
            'mkdir -p /usr/local/hestia/data/astero'
            .' && printf %%s %s > /usr/local/hestia/data/astero/release_api_key'
            .' && printf %%s %s > /usr/local/hestia/data/astero/release_api_insecure'
            .' && chmod 600 /usr/local/hestia/data/astero/release_api_key /usr/local/hestia/data/astero/release_api_insecure'
            .' && chown root:root /usr/local/hestia/data/astero/release_api_key /usr/local/hestia/data/astero/release_api_insecure',
            escapeshellarg($releaseApiKey),
            escapeshellarg($isInsecureSync)
        );

        $result = $this->executeSshCommand($server, $sshService, $writeKeyCmd, 30, 'write_release_api_key_files');
        if (! $result['success']) {
            throw new Exception('Failed to configure release API key on server: '.($result['data']['output'] ?? $result['message']));
        }

        if ($server->getMetadata('release_api_key') !== null) {
            $server->setMetadata('release_api_key', null);
            $server->save();
        }

        return [
            'summary' => 'Release API key configured on target server.',
        ];
    }

    protected function resolveDefaultReleaseApiKey(): string
    {
        $fromConfig = trim((string) config('platform.release_api_key', ''));
        if ($fromConfig !== '') {
            return $fromConfig;
        }

        $fromProcessEnv = trim((string) (getenv('RELEASE_API_KEY') ?: ''));
        if ($fromProcessEnv !== '') {
            return $fromProcessEnv;
        }

        return $this->readEnvValueFromDotEnv('RELEASE_API_KEY');
    }

    protected function readEnvValueFromDotEnv(string $key): string
    {
        $envPath = base_path('.env');
        if (! is_readable($envPath)) {
            return '';
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return '';
        }

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }

            if (str_starts_with($trimmed, '#')) {
                continue;
            }

            if (! str_contains($trimmed, '=')) {
                continue;
            }

            [$lineKey, $lineValue] = explode('=', $trimmed, 2);
            if (trim($lineKey) !== $key) {
                continue;
            }

            $value = trim($lineValue);
            if ($value === '') {
                return '';
            }

            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"'))
                || (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                return trim(substr($value, 1, -1));
            }

            return trim((string) preg_replace('/\s+#.*$/', '', $value));
        }

        return '';
    }

    /**
     * Update server releases.
     */
    protected function updateReleases(Server $server, ServerSSHService $sshService, ServerService $serverService): array
    {
        Log::info('ServerProvision: Updating server releases', ['server_id' => $server->id]);

        $releaseZipUrl = trim((string) ($server->getMetadata('release_zip_url') ?? ''));
        if ($releaseZipUrl !== '') {
            return $this->setupReleaseFromZipUrl($server, $sshService, $releaseZipUrl);
        }

        $this->configureReleaseApiKey($server, $sshService);

        $result = $serverService->updateLocalReleases($server);

        if (! ($result['success'] ?? false)) {
            throw new Exception('Failed to update releases: '.($result['message'] ?? 'Unknown error'));
        }

        $version = (string) ($result['data']['version'] ?? '');
        $outputTail = trim((string) ($result['data']['output_tail'] ?? ''));
        $executionPath = (string) ($result['data']['execution_path'] ?? '');

        $payload = [
            'summary' => $result['message'] ?? 'Releases updated successfully.',
            'version' => $version,
        ];

        if ($outputTail !== '') {
            $payload['output_tail'] = $this->sanitizeSensitiveMessage(substr($outputTail, -2000));
        }

        if ($executionPath !== '') {
            $payload['execution_path'] = $executionPath;
        }

        return $payload;
    }

    protected function setupReleaseFromZipUrl(Server $server, ServerSSHService $sshService, string $releaseZipUrl): array
    {
        $path = parse_url($releaseZipUrl, PHP_URL_PATH);
        $fileName = basename((string) $path);

        throw_if(in_array($fileName, ['', '.', '/'], true), Exception::class, 'Failed to update releases: Invalid release zip URL.');

        $version = $this->extractVersionFromReleaseFileName($fileName);
        throw_if($version === '', Exception::class, 'Failed to update releases: Could not determine version from release zip URL.');

        $escapedUrl = escapeshellarg($releaseZipUrl);
        $escapedVersion = escapeshellarg($version);

        $command = <<<'BASH'
set -euo pipefail
ASTERO_DATA_DIR="/usr/local/hestia/data/astero"
PACKAGE_DIR="$ASTERO_DATA_DIR/releases/application/main"
TMP_FILE="/tmp/astero-release-${RANDOM}.zip"

mkdir -p "$PACKAGE_DIR"

if command -v curl >/dev/null 2>&1; then
  curl -fL --connect-timeout 15 --max-time 1800 __URL__ -o "$TMP_FILE"
elif command -v wget >/dev/null 2>&1; then
  wget --timeout=1800 -O "$TMP_FILE" __URL__
else
  echo "Neither curl nor wget is available"
  exit 1
fi

test -s "$TMP_FILE"
mv "$TMP_FILE" "$PACKAGE_DIR/v__VERSION__.zip"
ln -sfn "v__VERSION__.zip" "$PACKAGE_DIR/current"

if [ -x "/usr/local/hestia/bin/a-install-master" ]; then
  /usr/local/hestia/bin/a-install-master "__VERSION__" "application" "main" || true
fi

echo "ASTERO_RELEASE_VERSION=__VERSION__"
BASH;

        $command = str_replace('__URL__', $escapedUrl, $command);
        $command = str_replace('__VERSION__', trim($escapedVersion, "'"), $command);

        $result = $sshService->executeCommand($server, $command, 2100);
        if (! ($result['success'] ?? false)) {
            $message = (string) ($result['message'] ?? 'Unknown SSH error');
            $output = (string) ($result['data']['output'] ?? '' ?: '');
            throw new Exception('Failed to update releases: '.$message.($output !== '' ? ' | '.$output : ''));
        }

        $output = (string) ($result['data']['output'] ?? '');

        return [
            'summary' => 'Release setup completed from release zip URL.',
            'version' => $version,
            'execution_path' => 'ssh-zip-url',
            'output_tail' => $this->sanitizeSensitiveMessage(substr(trim($output), -2000)),
        ];
    }

    protected function extractVersionFromReleaseFileName(string $fileName): string
    {
        if (preg_match('/_v(\d+\.\d+\.\d+)_release\.zip$/', $fileName, $matches) === 1) {
            return $matches[1];
        }

        if (preg_match('/^v(\d+\.\d+\.\d+)\.zip$/', $fileName, $matches) === 1) {
            return $matches[1];
        }

        return '';
    }

    /**
     * Sync server information.
     */
    protected function syncServer(Server $server, ServerService $serverService): array
    {
        Log::info('ServerProvision: Syncing server info', ['server_id' => $server->id]);

        $result = $serverService->syncServerInfo($server);

        if (! ($result['success'] ?? false)) {
            throw new Exception('Failed to sync server info: '.($result['message'] ?? 'Unknown error'));
        }

        return [
            'summary' => $result['message'] ?? 'Server information synced successfully.',
            'updated_fields' => array_keys((array) ($result['data'] ?? [])),
        ];
    }

    /**
     * Non-fatal: provisioning continues even if optimization fails.
     */
    protected function applyPgOptimizations(Server $server): array
    {
        Log::info('ServerProvision: Applying PostgreSQL optimizations', ['server_id' => $server->id]);

        $server->refresh();

        $ramMb = (int) ($server->server_ram ?? 0);
        $cpuCores = (int) ($server->server_ccore ?? 1);

        if ($ramMb < 256) {
            Log::warning('ServerProvision: Skipping PG optimization — insufficient RAM info', [
                'server_id' => $server->id,
                'ram_mb' => $ramMb,
            ]);

            return [
                'summary' => 'Skipped — server RAM info not available.',
                'skipped' => true,
            ];
        }

        $recommendations = $this->calculatePgSettings($ramMb, $cpuCores);

        $result = HestiaClient::execute(
            'a-apply-pg-config',
            $server,
            [json_encode($recommendations)],
            120
        );

        if (! $result['success'] || empty($result['data'])) {
            $errorMsg = $result['message'] ?? 'HestiaClient execution failed';
            Log::warning('ServerProvision: PG optimization failed (non-fatal)', [
                'server_id' => $server->id,
                'error' => $errorMsg,
            ]);

            return [
                'summary' => 'PG optimization failed: '.$errorMsg,
                'success' => false,
            ];
        }

        $data = $result['data'];
        $applied = array_values(array_filter($data['applied'] ?? [], fn ($value): bool => $value !== ''));
        $failed = array_values(array_filter($data['failed'] ?? [], fn ($value): bool => $value !== ''));

        Log::info('ServerProvision: PG optimization completed', [
            'server_id' => $server->id,
            'applied' => count($applied),
            'failed' => count($failed),
            'restarted' => $data['restarted'] ?? false,
        ]);

        return [
            'summary' => sprintf(
                'Applied %d settings. %s',
                count($applied),
                $data['restarted'] ?? false ? 'PostgreSQL restarted.' : 'PostgreSQL reloaded.'
            ),
            'applied' => $applied,
            'failed' => $failed,
            'restarted' => $data['restarted'] ?? false,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function calculatePgSettings(int $ramMb, int $cpuCores): array
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

        $workMemMb = max(4, (int) (($ramMb - $sharedBuffersMb) / ($maxConn * 3)));

        $maintenanceWorkMemMb = min((int) ($ramMb / 16), 2048);
        $maintenanceWorkMem = $maintenanceWorkMemMb >= 1024
            ? round($maintenanceWorkMemMb / 1024, 1).'GB'
            : $maintenanceWorkMemMb.'MB';

        $walBuffersMb = max(1, min(64, (int) ($sharedBuffersMb * 0.03)));

        return [
            'max_connections' => (string) $maxConn,
            'shared_buffers' => $sharedBuffers,
            'effective_cache_size' => $effectiveCache,
            'maintenance_work_mem' => $maintenanceWorkMem,
            'checkpoint_completion_target' => '0.9',
            'wal_buffers' => $walBuffersMb.'MB',
            'default_statistics_target' => '100',
            'random_page_cost' => '1.1',
            'effective_io_concurrency' => '200',
            'work_mem' => $workMemMb.'MB',
            'huge_pages' => $ramGb >= 32 ? 'on' : 'off',
            'min_wal_size' => $ramGb >= 4 ? '1GB' : '512MB',
            'max_wal_size' => $ramGb >= 8 ? '4GB' : ($ramGb >= 4 ? '2GB' : '1GB'),
            'max_worker_processes' => (string) $cpuCores,
            'max_parallel_workers_per_gather' => (string) max(2, (int) ($cpuCores / 2)),
            'max_parallel_workers' => (string) $cpuCores,
            'max_parallel_maintenance_workers' => (string) max(2, (int) ($cpuCores / 4)),
            'wal_compression' => 'pglz',
            'wal_log_hints' => 'on',
            'checkpoint_timeout' => '15min',
            'log_checkpoints' => 'on',
            'log_temp_files' => '0',
            'log_lock_waits' => 'on',
            'idle_in_transaction_session_timeout' => '10min',
            'shared_preload_libraries' => 'pg_stat_statements',
        ];
    }
}
