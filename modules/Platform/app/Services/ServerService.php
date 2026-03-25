<?php

namespace Modules\Platform\Services;

use App\Contracts\ScaffoldServiceInterface;
use App\Scaffold\ScaffoldDefinition;
use App\Traits\Scaffoldable;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Modules\Platform\Definitions\ServerDefinition;
use Modules\Platform\Http\Resources\ServerResource;
use Modules\Platform\Libs\HestiaClient;
use Modules\Platform\Models\Provider;
use Modules\Platform\Models\Server;
use RuntimeException;
use Throwable;

/**
 * Service class for handling server-related business logic.
 */
class ServerService implements ScaffoldServiceInterface
{
    use Scaffoldable;

    private const string COLLECTION_KEY = 'servers';

    private const string ITEM_NAME = 'Server';

    private const string BULK_ACTION_CONTEXT = 'platform_servers';

    /**
     * Long-running release sync can exceed default API timeout.
     */
    private const int RELEASE_SYNC_TIMEOUT_SECONDS = 600;

    /**
     * Server info sync is lighter but may still need more than default timeout.
     */
    private const int SERVER_INFO_SYNC_TIMEOUT_SECONDS = 120;

    public function getScaffoldDefinition(): ScaffoldDefinition
    {
        return new ServerDefinition;
    }

    // =============================================================================
    // REQUIRED ABSTRACT IMPLEMENTATIONS
    // =============================================================================

    public function getModelClass(): string
    {
        return Server::class;
    }

    /**
     * Override create to assign a formatted server ID (e.g., S0001) after persistence.
     */
    public function create(array $data): Model
    {
        return DB::transaction(function () use ($data) {
            $modelClass = $this->getModelClass();
            $preparedData = $this->prepareCreateData($data);

            if ($auditUserId = $this->resolveAuditUserId()) {
                $preparedData['created_by'] = $auditUserId;
                $preparedData['updated_by'] = $auditUserId;
            }

            // Extract metadata fields and merge with any metadata from prepareCreateData (e.g., install_options)
            $metadataFields = $this->extractMetadataFields($data);
            $existingMetadata = $preparedData['metadata'] ?? [];
            $preparedData['metadata'] = array_merge($existingMetadata, $metadataFields) ?: null;

            /** @var Server $server */
            $server = $modelClass::create($preparedData);
            $server->update([
                'uid' => Server::generateServerCodeFromId((int) $server->id),
            ]);

            if (! empty($data['provider_id'])) {
                $server->assignProvider((int) $data['provider_id'], true);
            }

            $this->syncSshPrivateKeySecret($server, $data);
            $this->syncReleaseApiKeySecret($server, $data);

            return $server->fresh();
        });
    }

    /**
     * Override update to handle metadata fields
     */
    public function update(Model $model, array $data): Model
    {
        if (! $model instanceof Server) {
            return $model;
        }

        $preparedData = $this->prepareUpdateData($data);

        if ($auditUserId = $this->resolveAuditUserId()) {
            $preparedData['updated_by'] = $auditUserId;
        }

        // Extract and merge metadata fields
        $metadataFields = $this->extractMetadataFields($data);
        $existingMetadata = $model->metadata ?? [];
        $preparedData['metadata'] = array_merge($existingMetadata, $metadataFields);

        $model->update($preparedData);

        if (array_key_exists('provider_id', $data)) {
            if (! empty($data['provider_id'])) {
                $model->syncProvidersForType(Provider::TYPE_SERVER, [(int) $data['provider_id']], (int) $data['provider_id']);
            } else {
                $model->syncProvidersForType(Provider::TYPE_SERVER, []);
            }
        }

        $this->syncSshPrivateKeySecret($model, $data);
        $this->syncReleaseApiKeySecret($model, $data);

        return $model->fresh();
    }

    public function applyFilters(Builder $query, Request $request): void
    {
        if ($request->filled('type')) {
            $query->where('type', $request->string('type')->toString());
        }

        if ($request->filled('provider_id')) {
            $query->whereHas('providers', function ($q) use ($request): void {
                $q->where('platform_providers.id', $request->integer('provider_id'));
            });
        }
    }

    public function getEagerLoadRelationships(): array
    {
        return [
            'serverProviders:id,name,type',
            'createdBy:id,first_name,last_name',
            'updatedBy:id,first_name,last_name',
        ];
    }

    // =============================================================================
    // SEARCH & SORT CONFIGURATION
    // =============================================================================

    public function getSearchableFields(): array
    {
        return ['name', 'ip', 'server_os'];
    }

    public function getSearchableRelations(): array
    {
        return [
            'providers' => ['name'],
        ];
    }

    public function getSortableColumnMap(): array
    {
        return [
            'name' => 'name',
            'ip' => 'ip',
            'type' => 'type',
            'status' => 'status',
            'created_at' => 'created_at',
            'updated_at' => 'updated_at',
        ];
    }

    public function getAllowedSortColumns(): array
    {
        return ['name', 'ip', 'type', 'status', 'created_at', 'updated_at'];
    }

    // =============================================================================
    // STATISTICS & STATUS NAVIGATION
    // =============================================================================

    public function getStatistics(): array
    {
        $modelClass = $this->getModelClass();

        $statusCounts = $modelClass::query()
            ->selectRaw('status, COUNT(*) as count')
            ->whereNull('deleted_at')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return [
            'total' => array_sum($statusCounts),
            'active' => $statusCounts['active'] ?? 0,
            'failed' => ($statusCounts['failed'] ?? 0) + ($statusCounts['provisioning'] ?? 0),
            'inactive' => $statusCounts['inactive'] ?? 0,
            'maintenance' => $statusCounts['maintenance'] ?? 0,
            'trash' => $modelClass::onlyTrashed()->count(),
        ];
    }

    public function getStatusNavigation(string $currentStatus): array
    {
        $stats = $this->getStatistics();
        $baseRoute = 'platform.servers.index';

        return [
            [
                'key' => 'all',
                'label' => 'All',
                'icon' => 'ri-dashboard-line',
                'href' => route($baseRoute, ['status' => 'all']),
                'active' => $currentStatus === 'all',
                'count' => $stats['total'],
                'badgeClass' => 'bg-primary-subtle text-primary',
            ],
            [
                'key' => 'active',
                'label' => 'Active',
                'icon' => 'ri-checkbox-circle-line',
                'href' => route($baseRoute, ['status' => 'active']),
                'active' => $currentStatus === 'active',
                'count' => $stats['active'],
                'badgeClass' => 'bg-success-subtle text-success',
            ],
            [
                'key' => 'failed',
                'label' => 'Failed',
                'icon' => 'ri-error-warning-line',
                'href' => route($baseRoute, ['status' => 'failed']),
                'active' => $currentStatus === 'failed',
                'count' => $stats['failed'],
                'badgeClass' => 'bg-danger-subtle text-danger',
            ],
            [
                'key' => 'inactive',
                'label' => 'Inactive',
                'icon' => 'ri-close-circle-line',
                'href' => route($baseRoute, ['status' => 'inactive']),
                'active' => $currentStatus === 'inactive',
                'count' => $stats['inactive'],
                'badgeClass' => 'bg-warning-subtle text-warning',
            ],
            [
                'key' => 'maintenance',
                'label' => 'Maintenance',
                'icon' => 'ri-tools-line',
                'href' => route($baseRoute, ['status' => 'maintenance']),
                'active' => $currentStatus === 'maintenance',
                'count' => $stats['maintenance'],
                'badgeClass' => 'bg-info-subtle text-info',
            ],
            [
                'key' => 'trash',
                'label' => 'Trash',
                'icon' => 'ri-delete-bin-line',
                'href' => route($baseRoute, ['status' => 'trash']),
                'active' => $currentStatus === 'trash',
                'count' => $stats['trash'],
                'badgeClass' => 'bg-danger-subtle text-danger',
            ],
        ];
    }

    // =============================================================================
    // HELPER METHODS
    // =============================================================================

    public function getStatusOptions(): array
    {
        return config('platform.server_statuses', [
            'active' => ['label' => 'Active', 'value' => 'active'],
            'inactive' => ['label' => 'Inactive', 'value' => 'inactive'],
            'maintenance' => ['label' => 'Maintenance', 'value' => 'maintenance'],
        ]);
    }

    public function getStatusOptionsForForm(): array
    {
        return collect(config('platform.server_statuses', []))
            ->reject(fn ($item, $key): bool => $key === 'trash')
            ->map(fn ($item): array => [
                'value' => $item['value'] ?? null,
                'label' => $item['label'] ?? null,
            ])
            ->filter(fn (array $item): bool => $item['value'] !== null && $item['label'] !== null)
            ->values()
            ->all();
    }

    public function getTypeOptions(): array
    {
        return collect(config('platform.server_types'))
            ->mapWithKeys(fn ($item): array => [$item['value'] => $item['label']])
            ->toArray();
    }

    public function getTypeOptionsForForm(): array
    {
        return collect(config('platform.server_types'))
            ->map(fn ($item): array => [
                'value' => $item['value'],
                'label' => $item['label'],
            ])
            ->values()
            ->all();
    }

    public function getProviderOptions(): array
    {
        try {
            return Provider::ofType('server')
                ->active()
                ->orderBy('name')
                ->get()
                ->mapWithKeys(fn ($item): array => [$item->id => $item->name])
                ->toArray();
        } catch (Throwable $throwable) {
            if (App::runningInConsole()) {
                return [];
            }

            throw $throwable;
        }
    }

    public function getProviderOptionsForForm(): array
    {
        try {
            return Provider::ofType('server')
                ->active()
                ->orderBy('name')
                ->get()
                ->map(fn ($item): array => [
                    'value' => $item->id,
                    'label' => $item->name,
                ])
                ->toArray();
        } catch (Throwable $throwable) {
            if (App::runningInConsole()) {
                return [];
            }

            throw $throwable;
        }
    }

    /**
     * Finds an available active server associated with the given agency.
     */
    public function findAvailableServer(int $agency_id, string $website_type): ?Server
    {
        /** @var Server|null $server */
        $server = Server::query()->where('status', 'active')
            ->whereHas('agencies', fn ($q) => $q->where('platform_agencies.id', $agency_id))
            ->first();

        return $server;
    }

    // =============================================================================
    // SERVER SYNC & RELEASE MANAGEMENT
    // =============================================================================

    /**
     * Updates local releases by triggering the a-sync-releases script on the Hestia server.
     * The script handles fetching from central server and updating local repository.
     */
    public function updateLocalReleases(Server $server): array
    {
        if (empty($server->ip) || empty($server->access_key_id) || empty($server->access_key_secret)) {
            return $this->errorResponse('Server credentials are incomplete');
        }

        $syncArgs = ['application', 'main', '--set-active'];
        if ($server->isLocalhostType()) {
            $syncArgs[] = '--insecure';
        }

        try {
            $response = $this->executeHestiaCommand(
                'a-sync-releases',
                $server,
                $syncArgs,
                self::RELEASE_SYNC_TIMEOUT_SECONDS
            );

            if (! ($response['success'] ?? false)) {
                $message = (string) ($response['message'] ?? 'Unknown error');
                if ($this->isReleaseNoUpdateFoundMessage($message)) {
                    return $this->successResponse('No published release available yet. Skipping release sync.', [
                        'no_update' => true,
                        'execution_path' => 'hestia-api',
                    ]);
                }

                return $this->errorResponse('Failed to update releases: '.$message);
            }

            return $this->finalizeReleaseSyncResponse($server, $response, 'hestia-api');
        } catch (Throwable $throwable) {
            return $this->errorResponse('Failed to update releases: '.$throwable->getMessage());
        }
    }

    /**
     * Syncs server info by fetching current state from Hestia server.
     * Uses the custom a-get-server-info script for comprehensive data.
     */
    public function syncServerInfo(Server $server): array
    {
        if (empty($server->ip) || empty($server->access_key_id) || empty($server->access_key_secret)) {
            return $this->errorResponse('Server credentials are incomplete (IP, Access Key ID, and Secret Key required)');
        }

        try {
            // Call custom Astero script for comprehensive server info
            $response = $this->executeHestiaCommand(
                'a-get-server-info',
                $server,
                ['json'],
                self::SERVER_INFO_SYNC_TIMEOUT_SECONDS
            );

            if (! $response['success']) {
                // Fallback to standard Hestia v-list-sys-info if custom script not available
                return $this->syncServerInfoFallback($server);
            }

            // HestiaClient already parses JSON response and extracts data
            $serverData = $response['data'] ?? [];

            if (empty($serverData) || ! is_array($serverData)) {
                return $this->syncServerInfoFallback($server);
            }

            $updates = [];

            // Update hostname/FQDN
            if (! empty($serverData['hostname'])) {
                $server->fqdn = $serverData['hostname'];
                $updates['fqdn'] = $serverData['hostname'];
            }

            // Update IP address
            if (! empty($serverData['ip_address']) && empty($server->ip)) {
                $server->ip = $serverData['ip_address'];
                $updates['ip_address'] = $serverData['ip_address'];
            }

            // Update OS
            if (! empty($serverData['os'])) {
                $server->setMetadata('server_os', $serverData['os']);
                $updates['os'] = $serverData['os'];
            }

            // Update CPU info
            if (! empty($serverData['cpu'])) {
                $server->setMetadata('server_cpu', $serverData['cpu']);
                $updates['cpu'] = $serverData['cpu'];
            }

            if (! empty($serverData['cpu_cores'])) {
                $server->setMetadata('server_ccore', (string) $serverData['cpu_cores']);
                $updates['cpu_cores'] = $serverData['cpu_cores'];
            }

            // Update RAM (total, used, free in MB)
            if (! empty($serverData['ram_mb'])) {
                $server->setMetadata('server_ram', (int) $serverData['ram_mb']);
                $updates['ram_mb'] = $serverData['ram_mb'];
            }

            if (isset($serverData['ram_used_mb'])) {
                $server->setMetadata('server_ram_used', (int) $serverData['ram_used_mb']);
                $updates['ram_used_mb'] = $serverData['ram_used_mb'];
            }

            if (isset($serverData['ram_free_mb'])) {
                $server->setMetadata('server_ram_free', (int) $serverData['ram_free_mb']);
                $updates['ram_free_mb'] = $serverData['ram_free_mb'];
            }

            // Update storage (total, used, free in GB)
            if (! empty($serverData['storage_total_gb'])) {
                $server->setMetadata('server_storage', (int) $serverData['storage_total_gb']);
                $updates['storage_total_gb'] = $serverData['storage_total_gb'];
            }

            if (! empty($serverData['storage_used_gb'])) {
                $server->setMetadata('server_storage_used', (int) $serverData['storage_used_gb']);
                $updates['storage_used_gb'] = $serverData['storage_used_gb'];
            }

            if (! empty($serverData['storage_free_gb'])) {
                $server->setMetadata('server_storage_free', (int) $serverData['storage_free_gb']);
                $updates['storage_free_gb'] = $serverData['storage_free_gb'];
            }

            // Update versions
            if (! empty($serverData['hestia_version'])) {
                $server->setMetadata('hestia_version', $serverData['hestia_version']);
                $updates['hestia_version'] = $serverData['hestia_version'];
            }

            if (! empty($serverData['astero_version'])) {
                $server->setMetadata('astero_version', $serverData['astero_version']);
                $updates['astero_version'] = $serverData['astero_version'];
            }

            // Update releases list (array of available versions)
            if (! empty($serverData['astero_releases'])) {
                $releases = is_array($serverData['astero_releases'])
                    ? $serverData['astero_releases']
                    : explode(',', (string) $serverData['astero_releases']);
                $server->setMetadata('astero_releases', $releases);
                $updates['astero_releases'] = $releases;
            }

            // Update uptime and load
            if (! empty($serverData['uptime'])) {
                $server->setMetadata('server_uptime', $serverData['uptime']);
                $updates['uptime'] = $serverData['uptime'];
            }

            if (! empty($serverData['load_average'])) {
                $server->setMetadata('server_load', $serverData['load_average']);
                $updates['load_average'] = $serverData['load_average'];
            }

            // Domain count from Hestia (handle alternate keys + fallback API call)
            $domainCount = $this->resolveDomainCount($serverData, $server);

            if ($domainCount !== null) {
                $server->current_domains = $domainCount;
                $updates['domain_count'] = $domainCount;
            }

            // Sync acme.sh status via SSH (if credentials exist)
            if ($server->hasSshCredentials()) {
                try {
                    $sshService = resolve(ServerSSHService::class);
                    $acmeCheck = $sshService->executeCommand(
                        $server,
                        'id asterossl &>/dev/null && test -f /home/asterossl/.acme.sh/acme.sh && echo "ACME_OK" || echo "ACME_MISSING"',
                        15
                    );

                    $acmeInstalled = $acmeCheck['success'] && str_contains(trim($acmeCheck['data']['output'] ?? ''), 'ACME_OK');

                    if ($acmeInstalled !== (bool) $server->acme_configured) {
                        $server->acme_configured = $acmeInstalled;
                        $updates['acme_configured'] = $acmeInstalled;
                    }
                } catch (Throwable) {
                    // SSH check failed — don't block sync for this
                }
            }

            // Always update the last synced timestamp
            $server->setMetadata('last_synced_at', now()->toIso8601String());

            $server->save();

            if (empty($updates)) {
                return $this->infoResponse('Server synced but no information was updated');
            }

            return $this->successResponse(
                'Server information synced successfully',
                $updates
            );
        } catch (Exception $exception) {
            return $this->errorResponse('Failed to sync server: '.$exception->getMessage());
        }
    }

    protected function getResourceClass(): ?string
    {
        return ServerResource::class;
    }

    protected function getDataMethodName(): string
    {
        return 'getData';
    }

    protected function getCollectionKey(): string
    {
        return self::COLLECTION_KEY;
    }

    protected function getItemName(): string
    {
        return self::ITEM_NAME;
    }

    protected function getBulkActionContext(): string
    {
        return self::BULK_ACTION_CONTEXT;
    }

    protected function getBulkActionRoute(): string
    {
        return route('platform.servers.bulk-action');
    }

    /**
     * Extract metadata fields from input data
     */
    protected function extractMetadataFields(array $data): array
    {
        $metadataFields = [];
        foreach (Server::METADATA_FIELDS as $field) {
            if (array_key_exists($field, $data)) {
                $metadataFields[$field] = $data[$field];
            }
        }

        return $metadataFields;
    }

    protected function syncReleaseApiKeySecret(Server $server, array $data): void
    {
        $releaseApiKeyInput = null;
        if (array_key_exists('release_api_key', $data)) {
            $releaseApiKeyInput = trim((string) $data['release_api_key']);
        }

        if ($releaseApiKeyInput !== null && $releaseApiKeyInput !== '') {
            $server->setSecret('release_api_key', $releaseApiKeyInput, 'api_key');
        } elseif ($server->getSecretValue('release_api_key') === null) {
            $defaultReleaseApiKey = $this->resolveDefaultReleaseApiKey();
            if ($defaultReleaseApiKey !== '') {
                $server->setSecret('release_api_key', $defaultReleaseApiKey, 'api_key');
            }
        }

        if ($server->getMetadata('release_api_key') !== null) {
            $legacyReleaseApiKey = trim((string) $server->getMetadata('release_api_key'));
            if ($legacyReleaseApiKey !== '' && $server->getSecretValue('release_api_key') === null) {
                $server->setSecret('release_api_key', $legacyReleaseApiKey, 'api_key');
            }

            $server->setMetadata('release_api_key', null);
            $server->save();
        }
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

    protected function prepareCreateData(array $data): array
    {
        $creationMode = $data['creation_mode'] ?? 'manual';
        $isProvisionMode = $creationMode === 'provision';

        $prepared = [
            'name' => $data['name'] ?? null,
            'monitor' => $data['monitor'] ?? false,
            'ip' => $data['ip'] ?? null,
            'port' => $isProvisionMode
                ? (int) ($data['install_port'] ?? 8443)
                : ($data['port'] ?? 8443),
            'type' => $data['type'] ?? null,
            'driver' => 'hestia',
            'current_domains' => $data['current_domains'] ?? 0,
            'max_domains' => $data['max_domains'] ?? null,
            'fqdn' => $data['fqdn'] ?? null,
            'status' => $isProvisionMode ? 'provisioning' : 'active',
        ];

        // Manual mode: requires API credentials
        if (! $isProvisionMode) {
            $prepared['access_key_id'] = $data['access_key_id'] ?? null;
            $prepared['access_key_secret'] = $data['access_key_secret'] ?? null;

            // Manual mode can still store SSH credentials for reprovisioning and script updates.
            if (array_key_exists('ssh_port', $data) && $data['ssh_port'] !== null && $data['ssh_port'] !== '') {
                $prepared['ssh_port'] = (int) $data['ssh_port'];
            }

            if (array_key_exists('ssh_user', $data) && trim((string) $data['ssh_user']) !== '') {
                $prepared['ssh_user'] = $data['ssh_user'];
            }

            if (array_key_exists('ssh_public_key', $data) && trim((string) $data['ssh_public_key']) !== '') {
                $prepared['ssh_public_key'] = $data['ssh_public_key'];
            }
        }

        // Provision mode: requires SSH keys and sets pending status
        if ($isProvisionMode) {
            $prepared['ssh_port'] = (int) ($data['ssh_port'] ?? 22) ?: 22;
            $prepared['ssh_user'] = $data['ssh_user'] ?? 'root';
            $prepared['ssh_public_key'] = $data['ssh_public_key'] ?? null;
            $prepared['provisioning_status'] = Server::PROVISIONING_STATUS_PENDING;

            // Store HestiaCP install options in metadata
            $prepared['metadata'] = [
                'creation_mode' => 'provision',
                'release_zip_url' => isset($data['release_zip_url']) ? trim((string) $data['release_zip_url']) : null,
                'install_options' => [
                    // Server
                    'port' => (int) ($data['install_port'] ?? 8443),
                    'lang' => $data['install_lang'] ?? 'en',
                    // Web
                    'apache' => (bool) ($data['install_apache'] ?? false),
                    'phpfpm' => (bool) ($data['install_phpfpm'] ?? true),
                    'multiphp' => (bool) ($data['install_multiphp'] ?? false),
                    'multiphp_versions' => $data['install_multiphp_versions'] ?? '8.4',
                    // FTP
                    'vsftpd' => (bool) ($data['install_vsftpd'] ?? false),
                    'proftpd' => (bool) ($data['install_proftpd'] ?? false),
                    // DNS
                    'named' => (bool) ($data['install_named'] ?? false),
                    // Database
                    'mysql' => (bool) ($data['install_mysql'] ?? false),  // MariaDB
                    'mysql8' => (bool) ($data['install_mysql8'] ?? false),
                    'postgresql' => (bool) ($data['install_postgresql'] ?? true),
                    // Mail
                    'exim' => (bool) ($data['install_exim'] ?? false),
                    'dovecot' => (bool) ($data['install_dovecot'] ?? false),
                    'sieve' => (bool) ($data['install_sieve'] ?? false),
                    // Security (Mail)
                    'clamav' => (bool) ($data['install_clamav'] ?? false),
                    'spamassassin' => (bool) ($data['install_spamassassin'] ?? false),
                    // Firewall
                    'iptables' => (bool) ($data['install_iptables'] ?? true),
                    'fail2ban' => (bool) ($data['install_fail2ban'] ?? true),
                    // Resources
                    'quota' => (bool) ($data['install_quota'] ?? false),
                    'resourcelimit' => (bool) ($data['install_resourcelimit'] ?? false),
                    // Extras
                    'webterminal' => (bool) ($data['install_webterminal'] ?? true),
                    'api' => (bool) ($data['install_api'] ?? true),
                    'force' => (bool) ($data['install_force'] ?? false),
                ],
            ];
        } else {
            // Manual mode: server is already provisioned with HestiaCP
            $prepared['provisioning_status'] = Server::PROVISIONING_STATUS_READY;
        }

        return $prepared;
    }

    protected function prepareUpdateData(array $data): array
    {
        $preparedData = [
            'name' => $data['name'] ?? null,
            'monitor' => $data['monitor'] ?? false,
            'ip' => $data['ip'] ?? null,
            'port' => $data['port'] ?? 8443,
            'access_key_secret' => $data['access_key_secret'] ?? null,
            'type' => $data['type'] ?? null,
            'access_key_id' => $data['access_key_id'] ?? null,
            'fqdn' => $data['fqdn'] ?? null,
            'status' => $data['status'] ?? 'active',
            'max_domains' => $data['max_domains'] ?? null,
        ];

        // Only update access_key_secret if provided
        if (! array_key_exists('access_key_secret', $data) || $data['access_key_secret'] === null || $data['access_key_secret'] === '') {
            unset($preparedData['access_key_secret']);
        }

        // SSH fields are truly optional on update: only update when present and non-empty
        if (array_key_exists('ssh_port', $data) && $data['ssh_port'] !== null && $data['ssh_port'] !== '') {
            $preparedData['ssh_port'] = (int) $data['ssh_port'];
        }

        if (array_key_exists('ssh_user', $data) && trim((string) $data['ssh_user']) !== '') {
            $preparedData['ssh_user'] = $data['ssh_user'];
        }

        if (array_key_exists('ssh_public_key', $data) && trim((string) $data['ssh_public_key']) !== '') {
            $preparedData['ssh_public_key'] = $data['ssh_public_key'];
        }

        return $preparedData;
    }

    protected function syncSshPrivateKeySecret(Server $server, array $data): void
    {
        if (! array_key_exists('ssh_private_key', $data)) {
            return;
        }

        $sshPrivateKey = trim((string) ($data['ssh_private_key'] ?? ''));
        if ($sshPrivateKey === '') {
            return;
        }

        $serverName = trim((string) $server->name);
        $server->setSecret('ssh_private_key', $sshPrivateKey, 'ssh_key', $serverName !== '' ? $serverName : null);
    }

    protected function applyStatusFilter(Builder $query, Request $request): void
    {
        $status = $request->input('status');

        if (empty($status) || $status === 'all') {
            return;
        }

        if ($status === 'failed') {
            $query->whereIn('status', ['failed', 'provisioning']);

            return;
        }

        $query->where('status', $status);
    }

    protected function beforeDelete(Model $model): void
    {
        $this->assertServerHasNoWebsites($model);
    }

    protected function beforeForceDelete(Model $model): void
    {
        $this->assertServerHasNoWebsites($model);
    }

    protected function assertServerHasNoWebsites(Model $model): void
    {
        if (! $model instanceof Server) {
            return;
        }

        throw_if($this->hasAssociatedWebsites($model), RuntimeException::class, 'Cannot trash or delete this server because websites are associated with it. Reassign or delete the websites first.');
    }

    protected function hasAssociatedWebsites(Server $server): bool
    {
        // @phpstan-ignore-next-line method.notFound
        return $server->websites()->withTrashed()->exists();
    }

    protected function isReleaseNoUpdateFoundMessage(string $message): bool
    {
        return str_contains(strtolower($message), 'no update found');
    }

    /**
     * Extract domain counts from the server info payload, with a fallback API query when missing.
     */
    protected function resolveDomainCount(array $serverData, Server $server): ?int
    {
        $domainCount = $this->extractDomainCountFromPayload($serverData);

        // If the payload didn't include domains (or returned zero unexpectedly), pull from Hestia directly
        if ($domainCount === null || $domainCount === 0) {
            return $this->fetchDomainCountFromHestia($server);
        }

        return $domainCount;
    }

    /**
     * Handle the different response shapes we might see for domain counts.
     */
    protected function extractDomainCountFromPayload(array $serverData): ?int
    {
        foreach (['domain_count', 'web_domain_count', 'web_domains', 'domains'] as $domainKey) {
            if (isset($serverData[$domainKey])) {
                return (int) $serverData[$domainKey];
            }
        }

        // Nested structures: domains: { total: X }, stats: { domains: X }, etc.
        $nestedCounts = [
            $serverData['domains']['total'] ?? null,
            $serverData['domains']['count'] ?? null,
            $serverData['stats']['domains'] ?? null,
        ];

        foreach ($nestedCounts as $count) {
            if ($count !== null) {
                return (int) $count;
            }
        }

        return null;
    }

    /**
     * Call Hestia API directly to total web domains per user.
     *
     * Note: We can't rely on v-list-users WEB_DOMAINS count as it may be stale.
     * Instead, we iterate through each user and call v-list-web-domains to get
     * the actual count of domains.
     */
    protected function fetchDomainCountFromHestia(Server $server): ?int
    {
        try {
            // First, get the list of users
            $usersResponse = HestiaClient::execute('v-list-users', $server, ['json']);

            if (! $usersResponse['success']) {
                return null;
            }

            $data = $usersResponse['data'] ?? [];
            $users = $this->unwrapHestiaResponseData($data);

            // Some responses may wrap the data
            if (isset($users['users']) && is_array($users['users'])) {
                $users = $users['users'];
            }

            if ($users === []) {
                return null;
            }

            // Try the quick method first - sum WEB_DOMAINS from user list
            $quickCount = 0;
            $hasWebDomains = false;
            foreach ($users as $userData) {
                if (is_array($userData) && isset($userData['WEB_DOMAINS'])) {
                    $quickCount += (int) $userData['WEB_DOMAINS'];
                    $hasWebDomains = true;
                }
            }

            // If quick count returned domains, use it
            if ($hasWebDomains && $quickCount > 0) {
                return $quickCount;
            }

            // Fallback: iterate through users and count domains directly
            // This handles cases where WEB_DOMAINS count is stale/zero
            $domainCount = 0;
            foreach (array_keys($users) as $username) {
                // Skip if username is not a string (malformed data)
                if (! is_string($username)) {
                    continue;
                }

                $domainsResponse = HestiaClient::execute('v-list-web-domains', $server, [$username, 'json']);

                if ($domainsResponse['success']) {
                    $domains = $domainsResponse['data'] ?? [];
                    $domains = $this->unwrapHestiaResponseData($domains);

                    // Each key in the response is a domain name
                    $domainCount += count($domains);
                }
            }

            return $domainCount > 0 ? $domainCount : null;
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Normalize the common success wrapper from Hestia API responses.
     */
    protected function unwrapHestiaResponseData(array $data): array
    {
        if (isset($data['status']) && $data['status'] === 'success' && isset($data['data'])) {
            return $data['data'];
        }

        if (isset($data['data']) && is_array($data['data'])) {
            return $data['data'];
        }

        return $data;
    }

    /**
     * Fallback method using standard Hestia v-list-sys-info command.
     */
    protected function syncServerInfoFallback(Server $server): array
    {
        try {
            $response = HestiaClient::execute('v-list-sys-info', $server, ['json']);

            if (! $response['success']) {
                return $response;
            }

            $sysInfo = $response['data'] ?? [];
            $hostInfo = $sysInfo['sysinfo'] ?? $sysInfo;

            if (empty($hostInfo)) {
                return $this->errorResponse('Empty response from Hestia server');
            }

            $updates = [];

            if (! empty($hostInfo['OS'])) {
                $server->setMetadata('server_os', $hostInfo['OS']);
                $updates['os'] = $hostInfo['OS'];
            }

            if (! empty($hostInfo['HESTIA'])) {
                $server->setMetadata('hestia_version', $hostInfo['HESTIA']);
                $updates['hestia_version'] = $hostInfo['HESTIA'];
            }

            if (! empty($hostInfo['HOSTNAME'])) {
                $server->fqdn = $hostInfo['HOSTNAME'];
                $updates['fqdn'] = $hostInfo['HOSTNAME'];
            }

            // Attempt to backfill domain counts even in fallback mode
            $domainCount = $this->fetchDomainCountFromHestia($server);
            if ($domainCount !== null) {
                $server->current_domains = $domainCount;
                $updates['domain_count'] = $domainCount;
            }

            // Always update the last synced timestamp
            $server->setMetadata('last_synced_at', now()->toIso8601String());

            $server->save();

            if ($updates === []) {
                return $this->infoResponse('Server synced but no information was updated (fallback mode)');
            }

            return $this->successResponse(
                'Server synced (fallback mode). Updated: '.implode(', ', array_keys($updates)),
                $updates
            );
        } catch (Exception $exception) {
            return $this->errorResponse('Failed to sync server (fallback): '.$exception->getMessage());
        }
    }

    protected function extractReleaseVersionFromOutput(string $output): ?string
    {
        if (preg_match('/Synced:\s+\S+\/\S+\s+v([0-9A-Za-z._-]+)/', $output, $matches)) {
            return $matches[1];
        }

        if (preg_match('/Latest version:\s+v([0-9A-Za-z._-]+)/', $output, $matches)) {
            return $matches[1];
        }

        return null;
    }

    protected function finalizeReleaseSyncResponse(Server $server, array $response, string $executionPath): array
    {
        $data = (array) ($response['data'] ?? []);
        $version = $data['version'] ?? null;

        if (! is_string($version) || $version === '') {
            $rawOutput = (string) ($data['raw'] ?? $data['output_tail'] ?? '');
            $version = $this->extractReleaseVersionFromOutput($rawOutput);
        }

        if (is_string($version) && $version !== '') {
            $server->setMetadata('astero_version', $version);
            if ($server->exists) {
                $server->save();
            }
        } else {
            $version = null;
        }

        return $this->successResponse(
            (string) ($response['message'] ?? 'Releases updated successfully.'),
            array_merge($data, [
                'version' => $version,
                'execution_path' => $executionPath,
            ])
        );
    }

    // =============================================================================
    // RESPONSE HELPERS
    // =============================================================================

    protected function successResponse(string $message, array $data = []): array
    {
        return [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];
    }

    protected function errorResponse(string $message): array
    {
        return [
            'success' => false,
            'message' => $message,
        ];
    }

    protected function infoResponse(string $message): array
    {
        return [
            'success' => false,
            'message' => $message,
            'info' => true,
        ];
    }

    /**
     * Wrapper to make Hestia command execution testable and centrally configurable.
     */
    protected function executeHestiaCommand(string $command, Server $server, array $args = [], int $timeout = 60): array
    {
        return HestiaClient::execute($command, $server, $args, $timeout);
    }
}
