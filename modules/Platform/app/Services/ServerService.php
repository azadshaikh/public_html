<?php

namespace Modules\Platform\Services;

use App\Contracts\ScaffoldServiceInterface;
use App\Scaffold\ScaffoldDefinition;
use App\Traits\Scaffoldable;
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
use Modules\Platform\Services\Concerns\InteractsWithServerInfoSync;
use Modules\Platform\Services\Concerns\InteractsWithServerReleaseSync;
use RuntimeException;
use Throwable;

/**
 * Service class for handling server-related business logic.
 */
class ServerService implements ScaffoldServiceInterface
{
    use InteractsWithServerInfoSync;
    use InteractsWithServerReleaseSync;
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
