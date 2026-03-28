<?php

namespace Modules\Platform\Services;

use App\Contracts\ScaffoldServiceInterface;
use App\Scaffold\ScaffoldDefinition;
use App\Traits\Scaffoldable;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Platform\Definitions\WebsiteDefinition;
use Modules\Platform\Enums\WebsiteStatus;
use Modules\Platform\Events\WebsiteCreatedEvent as EventsWebsiteCreated;
use Modules\Platform\Http\Resources\WebsiteResource;
use Modules\Platform\Jobs\SendAgencyWebhook;
use Modules\Platform\Jobs\WebsiteDelete;
use Modules\Platform\Jobs\WebsiteProvision;
use Modules\Platform\Jobs\WebsiteRemoveFromServer;
use Modules\Platform\Jobs\WebsiteSuspend;
use Modules\Platform\Jobs\WebsiteTrash;
use Modules\Platform\Jobs\WebsiteUnsuspend;
use Modules\Platform\Jobs\WebsiteUntrash;
use Modules\Platform\Models\Agency;
use Modules\Platform\Models\Domain;
use Modules\Platform\Models\Provider;
use Modules\Platform\Models\Server;
use Modules\Platform\Models\Website;
use Modules\Platform\Services\Concerns\InteractsWithWebsiteSyncPayload;
use RuntimeException;

class WebsiteService implements ScaffoldServiceInterface
{
    use InteractsWithWebsiteSyncPayload;
    use Scaffoldable {
        executeBulkAction as protected scaffoldExecuteBulkAction;
        applyFilters as protected traitApplyFilters;
    }

    public function __construct(
        private readonly ServerService $serverService,
        private readonly WebsiteAccountService $websiteAccountService
    ) {}

    public function getScaffoldDefinition(): ScaffoldDefinition
    {
        return new WebsiteDefinition;
    }

    public function getStatistics(): array
    {
        $statusCounts = Website::query()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return [
            'total' => array_sum($statusCounts),
            'failed' => ($statusCounts[WebsiteStatus::Provisioning->value] ?? 0) + ($statusCounts[WebsiteStatus::Failed->value] ?? 0),
            'active' => $statusCounts[WebsiteStatus::Active->value] ?? 0,
            'suspended' => $statusCounts[WebsiteStatus::Suspended->value] ?? 0,
            'expired' => $statusCounts[WebsiteStatus::Expired->value] ?? 0,
            'trash' => Website::onlyTrashed()->count(),
        ];
    }

    public function create(array $data): Model
    {
        return DB::transaction(function () use ($data) {
            $preparedData = $this->prepareCreateData($data);

            if ($auditUserId = $this->resolveAuditUserId()) {
                $preparedData['created_by'] = $auditUserId;
                $preparedData['updated_by'] = $auditUserId;
            }

            if (isset($data['is_agency']) && $data['is_agency'] && ! empty($preparedData['agency_id'])) {
                $existingAgencyWebsite = Website::query()->where('agency_id', $preparedData['agency_id'])
                    ->isAgencyWebsite()
                    ->exists();
                throw_if($existingAgencyWebsite, RuntimeException::class, 'This agency already has a designated agency website. Only one website per agency can have the is_agency flag enabled.');
            }

            /** @var Website $website */
            $website = Website::query()->create($preparedData);

            // Batch metadata flags into a single save to avoid redundant queries
            $metadataDirty = false;

            if (isset($data['is_www'])) {
                $website->is_www = Website::supportsWwwForDomain($website->domain) && (bool) $data['is_www'];
                $metadataDirty = true;
            }

            if (isset($data['skip_cdn'])) {
                $website->skip_cdn = $data['skip_cdn'];
                $metadataDirty = true;
            }

            if (isset($data['skip_dns'])) {
                $website->skip_dns = $data['skip_dns'];
                $metadataDirty = true;
            }

            if (isset($data['skip_ssl_issue'])) {
                $website->skip_ssl_issue = $data['skip_ssl_issue'];
                $metadataDirty = true;
            }

            if (isset($data['skip_email'])) {
                $website->skip_email = (bool) $data['skip_email'];
                $metadataDirty = true;
            }

            if (isset($data['is_agency']) && $data['is_agency']) {
                $website->is_agency = true;
                $metadataDirty = true;
            }

            if ($metadataDirty) {
                $website->save();
            }

            if (isset($data['is_agency']) && $data['is_agency'] && $website->agency) {
                $website->agency->agency_website_id = $website->id;
                $website->agency->generateSecretKey();
                $website->agency->save();
            }

            if (! empty($data['dns_provider_id'])) {
                $website->assignProvider((int) $data['dns_provider_id'], true);
            }

            if (! empty($data['cdn_provider_id'])) {
                $website->assignProvider((int) $data['cdn_provider_id'], true);
            }

            $website->assignSiteIdentifier($website->agency_id, $data['website_username'] ?? null);
            $website->refresh();

            $this->websiteAccountService->createAccountsForWebsite($website);

            dispatch(new WebsiteProvision($website))->onQueue('default');
            event(new EventsWebsiteCreated($website->server));

            return $website;
        });
    }

    public function update(Model $model, array $data): Model
    {
        if (! $model instanceof Website) {
            return $model;
        }

        return DB::transaction(function () use ($model, $data) {
            $preparedData = $this->prepareUpdateData($data);

            if ($auditUserId = $this->resolveAuditUserId()) {
                $preparedData['updated_by'] = $auditUserId;
            }

            $model->update($preparedData);

            if (isset($data['is_www'])) {
                $model->is_www = Website::supportsWwwForDomain($model->domain) && (bool) $data['is_www'];
            }

            if (isset($data['is_agency'])) {
                $wasAgencyWebsite = $model->is_agency;
                $willBeAgencyWebsite = (bool) $data['is_agency'];

                if ($willBeAgencyWebsite && ! $wasAgencyWebsite && $model->agency_id) {
                    $existingAgencyWebsite = Website::query()->where('agency_id', $model->agency_id)
                        ->where('id', '!=', $model->id)
                        ->isAgencyWebsite()
                        ->exists();
                    throw_if($existingAgencyWebsite, RuntimeException::class, 'This agency already has a designated agency website. Only one website per agency can have the is_agency flag enabled.');
                }

                $model->is_agency = $willBeAgencyWebsite;

                if ($model->agency) {
                    if ($willBeAgencyWebsite && ! $wasAgencyWebsite) {
                        $model->agency->agency_website_id = $model->id;
                        $model->agency->generateSecretKey();
                        $model->agency->save();
                    } elseif (! $willBeAgencyWebsite && $wasAgencyWebsite) {
                        $model->agency->agency_website_id = null;
                        $model->agency->secret_key = null;
                        $model->agency->save();
                    }
                }
            }

            if (isset($data['skip_cdn'])) {
                $model->skip_cdn = $data['skip_cdn'];
            }

            if (isset($data['skip_dns'])) {
                $model->skip_dns = $data['skip_dns'];
            }

            if (isset($data['skip_ssl_issue'])) {
                $model->skip_ssl_issue = $data['skip_ssl_issue'];
            }

            if (array_key_exists('dns_provider_id', $data)) {
                if (! empty($data['dns_provider_id'])) {
                    $model->assignProvider((int) $data['dns_provider_id'], true);
                } else {
                    $model->removeProvidersOfType(Provider::TYPE_DNS);
                }
            }

            if (array_key_exists('cdn_provider_id', $data)) {
                if (! empty($data['cdn_provider_id'])) {
                    $model->assignProvider((int) $data['cdn_provider_id'], true);
                } else {
                    $model->removeProvidersOfType(Provider::TYPE_CDN);
                }
            }

            $model->save();

            return $model->fresh();
        });
    }

    public function getTypeOptionsForForm(): array
    {
        return collect(config('platform.website.types', []))
            ->map(fn ($item): array => [
                'value' => $item['value'],
                'label' => $item['label'],
            ])
            ->values()
            ->all();
    }

    public function getPlanOptionsForForm(): array
    {
        return collect(config('astero.website_plans', []))
            ->map(fn ($item, $key): array => [
                'value' => $key,
                'label' => $item['label'] ?? $key,
            ])
            ->values()
            ->all();
    }

    public function getStatusOptionsForForm(): array
    {
        return collect(config('platform.website.statuses', []))
            ->reject(fn ($item, $key): bool => in_array($key, ['trash', 'deleted'], true))
            ->map(fn ($item, $key): array => [
                'value' => $key,
                'label' => $item['label'] ?? $key,
            ])
            ->values()
            ->all();
    }

    public function getDnsModeOptionsForForm(): array
    {
        return collect(config('platform.website.dns_modes', []))
            ->map(fn ($item, $key): array => [
                'value' => $item['value'] ?? $key,
                'label' => $item['label'] ?? $key,
            ])
            ->values()
            ->all();
    }

    // =============================================================================
    // WEBSITE SYNC
    // =============================================================================

    /**
     * Sync website information from the server.
     */
    public function syncWebsiteInfo(Website $website): array
    {
        $website->loadMissing('server');

        if (! $website->server) {
            return $this->errorResponse('Website has no server assigned.');
        }

        if (empty($website->website_username) || empty($website->domain)) {
            return $this->errorResponse('Website username or domain is missing.');
        }

        $updates = [];

        $fetchError = null;

        try {
            $fetchResult = $this->fetchWebsiteInfoPayload($website);

            if ($fetchResult['success'] && $fetchResult['payload'] !== null) {
                $payload = $fetchResult['payload'];
                $asteroVersion = $this->normalizeVersion(
                    $this->pickPayloadValue($payload, ['astero_version', 'app_version', 'version', 'current_release'])
                );
                if (! in_array($asteroVersion, [null, '', '0', $website->astero_version], true)) {
                    $website->astero_version = $asteroVersion;
                    $updates['astero_version'] = $asteroVersion;
                }

                $appName = $this->pickPayloadValue($payload, ['app_name', 'name']);
                if (is_string($appName)) {
                    $appName = trim($appName);
                    if ($appName !== '' && $appName !== $website->name) {
                        $website->name = $appName;
                        $updates['name'] = $appName;
                    }
                }

                $adminSlug = $this->pickPayloadValue($payload, ['admin_slug', 'ADMIN_SLUG', 'admin_login_url_slug', 'admin_path']);
                if (is_string($adminSlug)) {
                    $adminSlug = trim($adminSlug, " \t\n\r\0\x0B/");
                    if ($adminSlug !== '' && $adminSlug !== $website->admin_slug) {
                        $website->admin_slug = $adminSlug;
                        $updates['admin_slug'] = $adminSlug;
                    }
                }

                $laravelVersion = $this->pickPayloadValue($payload, ['laravel_version', 'laravel']);
                if (! empty($laravelVersion)) {
                    $website->setMetadata('laravel_version', $laravelVersion);
                    $updates['laravel_version'] = $laravelVersion;
                }

                $phpVersion = $this->pickPayloadValue($payload, ['php_version', 'php']);
                if (! empty($phpVersion)) {
                    $website->setMetadata('php_version', $phpVersion);
                    $updates['php_version'] = $phpVersion;
                }

                $appEnv = $this->pickPayloadValue($payload, ['app_env', 'env', 'app_environment']);
                if ($appEnv !== null && $appEnv !== '') {
                    $website->setMetadata('app_env', $appEnv);
                    $updates['app_env'] = $appEnv;
                }

                $appDebug = $this->pickPayloadValue($payload, ['app_debug', 'debug', 'app_debug_mode']);
                if ($appDebug !== null && $appDebug !== '') {
                    $website->setMetadata('app_debug', $this->normalizeDebugValue($appDebug));
                    $updates['app_debug'] = $this->normalizeDebugValue($appDebug);
                }

                // Queue worker status
                $queueWorker = $payload['queue_worker'] ?? null;
                if (is_array($queueWorker)) {
                    $queueRunningCount = (int) ($queueWorker['running_count'] ?? 0);
                    $queueTotalCount = (int) ($queueWorker['total_count'] ?? 0);
                    $queueStatus = $this->normalizeQueueWorkerStatus(
                        $queueWorker['status'] ?? 'unknown',
                        $queueRunningCount,
                        $queueTotalCount
                    ) ?? 'unknown';

                    $website->setMetadata('queue_worker_status', $queueStatus);
                    $website->setMetadata('queue_worker_running_count', $queueRunningCount);
                    $website->setMetadata('queue_worker_total_count', $queueTotalCount);
                    $updates['queue_worker'] = [
                        'status' => $queueStatus,
                        'running_count' => $queueRunningCount,
                        'total_count' => $queueTotalCount,
                    ];
                } else {
                    $queueStatus = $this->pickPayloadValue($payload, ['queue_worker_status', 'queue_status']);

                    if ($queueStatus !== null && $queueStatus !== '') {
                        $queueRunningCount = (int) ($payload['queue_worker_running_count'] ?? 0);
                        $queueTotalCount = (int) ($payload['queue_worker_total_count'] ?? 0);
                        $normalizedQueueStatus = $this->normalizeQueueWorkerStatus(
                            $queueStatus,
                            $queueRunningCount,
                            $queueTotalCount
                        ) ?? 'unknown';

                        $website->setMetadata('queue_worker_status', $normalizedQueueStatus);
                        $website->setMetadata('queue_worker_running_count', $queueRunningCount);
                        $website->setMetadata('queue_worker_total_count', $queueTotalCount);
                        $updates['queue_worker'] = [
                            'status' => $normalizedQueueStatus,
                            'running_count' => $queueRunningCount,
                            'total_count' => $queueTotalCount,
                        ];
                    }
                }

                // Cron status
                $cron = $payload['cron'] ?? null;
                if (is_array($cron)) {
                    $website->setMetadata('cron_status', $cron['status'] ?? 'unknown');
                    $updates['cron'] = $cron;
                } else {
                    $cronStatus = $this->pickPayloadValue($payload, ['cron_status', 'scheduler_status']);

                    if ($cronStatus !== null && $cronStatus !== '') {
                        $website->setMetadata('cron_status', (string) $cronStatus);
                        $updates['cron'] = ['status' => (string) $cronStatus];
                    }
                }

                // Disk usage (in bytes)
                $diskUsageBytes = $payload['disk_usage_bytes'] ?? null;
                if ($diskUsageBytes !== null) {
                    $website->setMetadata('disk_usage_bytes', (int) $diskUsageBytes);
                    $updates['disk_usage_bytes'] = (int) $diskUsageBytes;
                }
            } else {
                $fetchError = $fetchResult['error'] ?? 'Failed to fetch website info from server.';
            }
        } catch (Exception $exception) {
            return $this->errorResponse('Failed to sync website: '.$exception->getMessage());
        }

        if (empty($website->astero_version) && ! empty($website->server->astero_version)) {
            $website->astero_version = $website->server->astero_version;
            $updates['astero_version'] = $website->astero_version;
        }

        // Always update the last synced timestamp.
        $website->setMetadata('last_synced_at', now()->toIso8601String());
        $website->save();

        // If we couldn't fetch the payload at all, report the error.
        if ($fetchError !== null) {
            Log::warning('Website sync failed to fetch info', [
                'website_id' => $website->getKey(),
                'domain' => $website->domain,
                'server_id' => $website->server_id,
                'error' => $fetchError,
            ]);

            return $this->errorResponse('Could not fetch website info: '.$fetchError);
        }

        if ($updates === []) {
            return $this->infoResponse('Website synced — no changes detected.');
        }

        return $this->successResponse('Website information synced successfully.', $updates);
    }

    protected function getResourceClass(): ?string
    {
        return WebsiteResource::class;
    }

    protected function getEagerLoadRelationships(): array
    {
        return [
            'server:id,name,uid',
            'agency:id,name,uid',
            'providers:id,name,type',
            'createdBy:id,first_name,last_name',
            'updatedBy:id,first_name,last_name',
        ];
    }

    protected function applyStatusFilter(Builder $query, Request $request): void
    {
        $status = $request->input('status');

        if (empty($status) || $status === 'all') {
            return;
        }

        if ($status === 'failed') {
            $query->whereIn('status', [
                WebsiteStatus::Provisioning->value,
                WebsiteStatus::Failed->value,
            ]);

            return;
        }

        $query->where('status', $status);
    }

    protected function applyFilters(Builder $query, Request $request): void
    {
        $this->traitApplyFilters($query, $request);

        if ($customerRef = $request->input('customer_ref')) {
            $query->where('customer_ref', $customerRef);
        }

        if ($planRef = $request->input('plan_ref')) {
            $query->where('plan_ref', $planRef);
        }
    }

    // =============================================================================
    // CRUD (Complex business logic)
    // =============================================================================

    protected function prepareCreateData(array $data): array
    {
        /** @var Agency|null $agency */
        $agency = ! empty($data['agency_id']) ? Agency::query()->find($data['agency_id']) : null;
        throw_if(! empty($data['agency_id']) && ! $agency, RuntimeException::class, 'Invalid agency');

        /** @var Server|null $server */
        $server = null;
        if (! empty($data['server_id'])) {
            /** @var Server|null $resolvedServer */
            $resolvedServer = Server::query()->where('id', $data['server_id'])->where('status', 'active')->first();
            $server = $resolvedServer;
        } elseif ($agency) {
            $server = $this->serverService->findAvailableServer($agency->id, $data['type'] ?? 'paid');
        }

        throw_unless($server, RuntimeException::class, 'No available server found');

        $domainId = null;
        $allDomains = Domain::query()->select('id', 'name')->get()->pluck('name', 'id');

        if ($allDomains->contains($data['domain'])) {
            $domainId = $allDomains->search($data['domain']);
        } else {
            $domainParts = explode('.', (string) $data['domain']);
            $rootDomain = implode('.', array_slice($domainParts, 1));

            if ($allDomains->contains($rootDomain)) {
                $domainId = $allDomains->search($rootDomain);
            }
        }

        return [
            'type' => $data['type'] ?? 'paid',
            'plan_tier' => $data['plan_tier'] ?? $data['plan'] ?? 'basic',
            'niches' => $data['niches'] ?? [],
            'uid' => null,
            'secret_key' => null,
            'name' => $data['name'],
            'domain' => $data['domain'],
            'domain_id' => $domainId,
            'dns_mode' => $data['dns_mode'] ?? 'subdomain',
            'customer_ref' => $data['customer_ref'] ?? null,
            'customer_data' => $this->resolveCustomerData($data),
            'plan_ref' => $data['plan_ref'] ?? null,
            'plan_data' => $data['plan_data'] ?? null,
            'server_id' => $server->id,
            'agency_id' => $agency?->id,
            'status' => WebsiteStatus::Provisioning,
        ];
    }

    /**
     * Build customer_data from available sources.
     * Priority: explicitly passed customer_data array > manually entered customer_name/customer_email fields.
     */
    private function resolveCustomerData(array $data): ?array
    {
        if (! empty($data['customer_data'])) {
            return $data['customer_data'];
        }

        $name = trim($data['customer_name'] ?? '');
        $email = trim($data['customer_email'] ?? '');

        if ($name === '' && $email === '') {
            return null;
        }

        return array_filter([
            'name' => $name ?: null,
            'email' => $email ?: null,
        ]);
    }

    protected function prepareUpdateData(array $data): array
    {
        $preparedData = [
            'name' => $data['name'] ?? null,
            'type' => $data['type'] ?? null,
            'plan_tier' => $data['plan_tier'] ?? $data['plan'] ?? null,
            'dns_mode' => $data['dns_mode'] ?? null,
            'niches' => $data['niches'] ?? [],
            'customer_ref' => $data['customer_ref'] ?? null,
            'customer_data' => $data['customer_data'] ?? null,
            'plan_ref' => $data['plan_ref'] ?? null,
            'plan_data' => $data['plan_data'] ?? null,
            'server_id' => $data['server_id'] ?? null,
            'agency_id' => $data['agency_id'] ?? null,
            'status' => $data['status'] ?? null,
        ];

        if (array_key_exists('expired_on', $data)) {
            $preparedData['expired_on'] = empty($data['expired_on']) ? null : $data['expired_on'];
        }

        return array_filter(
            $preparedData,
            fn ($value, $key): bool => $value !== null || $key === 'expired_on' || $key === 'niches',
            ARRAY_FILTER_USE_BOTH
        );
    }

    protected function executeBulkAction(string $action, array $ids, Request $request): array
    {
        if (! in_array($action, ['delete', 'restore', 'force_delete', 'suspend', 'unsuspend', 'remove_from_server'], true)) {
            return $this->scaffoldExecuteBulkAction($action, $ids, $request);
        }

        $affected = 0;

        foreach ($ids as $id) {
            /** @var Website|null $website */
            $website = Website::withTrashed()->find((int) $id);
            if (! $website) {
                continue;
            }

            if ($action === 'suspend') {
                // Only suspend active websites
                if ($website->status !== WebsiteStatus::Active) {
                    continue;
                }

                $website->status = WebsiteStatus::Suspended;
                $website->updated_by = auth()->id();
                $website->save();

                dispatch(new WebsiteSuspend($website));
                SendAgencyWebhook::dispatchForWebsite($website, 'website.status_changed', [
                    'previous_status' => WebsiteStatus::Active->value,
                ]);

                $affected++;

                continue;
            }

            if ($action === 'unsuspend') {
                // Only unsuspend suspended websites
                if ($website->status !== WebsiteStatus::Suspended) {
                    continue;
                }

                $website->status = WebsiteStatus::Active;
                $website->updated_by = auth()->id();
                $website->save();

                dispatch(new WebsiteUnsuspend($website));
                SendAgencyWebhook::dispatchForWebsite($website, 'website.status_changed', [
                    'previous_status' => WebsiteStatus::Suspended->value,
                ]);

                $affected++;

                continue;
            }

            if ($action === 'delete') {
                if ($website->deleted_at !== null) {
                    continue;
                }

                $website->deleted_by = auth()->id();
                $website->status = WebsiteStatus::Trash;
                $website->updated_by = auth()->id();
                $website->save();

                if ($website->delete()) {
                    dispatch(new WebsiteTrash($website->id))
                        ->onQueue('default');
                }

                SendAgencyWebhook::dispatchForWebsiteAfterResponse($website, 'website.deleted', [
                    'status' => 'trash',
                ]);

                $affected++;

                continue;
            }

            if ($action === 'restore') {
                if ($website->deleted_at === null) {
                    continue;
                }

                $website->restore();
                $website->status = WebsiteStatus::Active;
                $website->deleted_by = null;
                $website->deleted_at = null;
                $website->updated_by = auth()->id();
                $website->save();

                dispatch(new WebsiteUntrash($website->id))
                    ->onQueue('default');

                SendAgencyWebhook::dispatchForWebsiteAfterResponse($website, 'website.restored');

                $affected++;

                continue;
            }

            if ($action === 'remove_from_server') {
                // Only process trashed websites that haven't been removed yet
                if ($website->deleted_at === null) {
                    continue;
                }

                if ($website->status === WebsiteStatus::Deleted) {
                    continue;
                }

                dispatch(new WebsiteRemoveFromServer($website->id));

                $affected++;

                continue;
            }

            if ($website->deleted_at === null) {
                $website->deleted_by = auth()->id();
                $website->status = WebsiteStatus::Trash;
                $website->updated_by = auth()->id();
                $website->save();
                $website->delete();
            }

            $website->updated_by = auth()->id();
            $website->save();

            dispatch(new WebsiteDelete($website->id))->onQueue('default');

            $affected++;
        }

        $messages = [
            'suspend' => $affected.' website(s) suspended',
            'unsuspend' => $affected.' website(s) unsuspended',
            'delete' => $affected.' website(s) moved to trash',
            'restore' => $affected.' website(s) restored',
            'remove_from_server' => $affected.' website(s) scheduled for removal from server',
            'force_delete' => $affected.' website(s) scheduled for deletion',
        ];

        $message = $messages[$action];

        return [
            'message' => $message,
            'affected' => $affected,
        ];
    }

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
}
