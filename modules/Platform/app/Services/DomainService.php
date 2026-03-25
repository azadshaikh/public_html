<?php

namespace Modules\Platform\Services;

use App\Contracts\ScaffoldServiceInterface;
use App\Scaffold\ScaffoldDefinition;
use App\Traits\Scaffoldable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Modules\Platform\Definitions\DomainDefinition;
use Modules\Platform\Http\Resources\DomainResource;
use Modules\Platform\Models\Agency;
use Modules\Platform\Models\Domain;
use Modules\Platform\Models\Provider;
use Modules\Platform\Models\Secret;
use Throwable;

class DomainService implements ScaffoldServiceInterface
{
    use Scaffoldable;

    public function getScaffoldDefinition(): ScaffoldDefinition
    {
        return new DomainDefinition;
    }

    public function applyFilters(Builder $query, Request $request): void
    {
        if ($request->filled('agency_id')) {
            $query->where('agency_id', $request->integer('agency_id'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->string('type')->toString());
        }

        if ($request->filled('registrar_id')) {
            $registrarId = $request->integer('registrar_id');
            $query->whereHas('providers', function ($q) use ($registrarId): void {
                $q->where('platform_providers.id', $registrarId)
                    ->where('platform_providers.type', Provider::TYPE_DOMAIN_REGISTRAR);
            });
        }

        $registeredFrom = $request->input('registered_date_from');
        $registeredTo = $request->input('registered_date_to');
        if ($registeredFrom) {
            $query->whereDate('registered_date', '>=', $registeredFrom);
        }

        if ($registeredTo) {
            $query->whereDate('registered_date', '<=', $registeredTo);
        }

        $expiryFrom = $request->input('expiry_date_from');
        $expiryTo = $request->input('expiry_date_to');
        if ($expiryFrom) {
            $query->whereDate('expiry_date', '>=', $expiryFrom);
        }

        if ($expiryTo) {
            $query->whereDate('expiry_date', '<=', $expiryTo);
        }
    }

    public function getAgencyOptions(): array
    {
        try {
            /** @var Collection<int, Agency> $agencies */
            $agencies = Agency::query()
                ->select('id', 'name')
                ->where('status', 'active')
                ->orderBy('name')
                ->get();

            return $agencies
                ->map(fn (Agency $agency): array => [
                    'value' => $agency->id,
                    'label' => $agency->name,
                ])
                ->all();
        } catch (Throwable $throwable) {
            if (App::runningInConsole()) {
                return [];
            }

            throw $throwable;
        }
    }

    public function getTypeOptionsForForm(): array
    {
        return collect(config('platform.domain.types', []))
            ->map(fn ($type, $key): array => [
                'value' => $key,
                'label' => $type['label'] ?? $key,
            ])
            ->values()
            ->all();
    }

    public function getRegistrarOptionsForSelect(): array
    {
        try {
            return Provider::query()->ofType(Provider::TYPE_DOMAIN_REGISTRAR)
                ->active()
                ->orderBy('name')
                ->get()
                ->map(fn (Provider $p): array => ['value' => $p->id, 'label' => $p->name])
                ->toArray();
        } catch (Throwable $throwable) {
            if (App::runningInConsole()) {
                return [];
            }

            throw $throwable;
        }
    }

    public function getStatusOptions(): array
    {
        return collect(config('platform.domain.statuses', []))
            ->map(fn ($status, $key): array => [
                'value' => $key,
                'label' => $status['label'] ?? ucfirst((string) $key),
            ])
            ->values()
            ->all();
    }

    // =============================================================================
    // WHOIS LOOKUP
    // =============================================================================

    public function getDomainDetailsFromWhois(string $domainName): ?array
    {
        $whoisService = resolve(WhoisService::class);
        $data = $whoisService->getWhoisData($domainName, Domain::class);

        if (! isset($data['success']) || $data['success'] !== true) {
            return null;
        }

        $registrar = $data['domain_registrar'] ?? '';
        $registrarPattern = '%'.$this->escapeLike((string) $registrar).'%';

        $matchedProvider = Provider::query()->ofType(Provider::TYPE_DOMAIN_REGISTRAR)
            ->active()
            ->where(function ($query) use ($registrarPattern): void {
                $query->where('name', 'ilike', $registrarPattern)
                    ->orWhere('vendor', 'ilike', $registrarPattern);
            })
            ->first();

        $otherProvider = Provider::query()->ofType(Provider::TYPE_DOMAIN_REGISTRAR)
            ->where('vendor', 'other')
            ->first();

        return [
            'domain_registrar_id' => $matchedProvider ? $matchedProvider->id : 0,
            'other_registrar_id' => $otherProvider ? $otherProvider->id : 0,
            'domain_registrar' => $registrar,
            'registered_on' => $data['registered_on'] ?? null,
            'expires_on' => $data['expires_on'] ?? null,
            'updated_on' => $data['updated_on'] ?? null,
            'name_server_1' => $data['name_server_1'] ?? null,
            'name_server_2' => $data['name_server_2'] ?? null,
            'name_server_3' => $data['name_server_3'] ?? null,
            'name_server_4' => $data['name_server_4'] ?? null,
        ];
    }

    public function refreshWhois(Domain $domain): array
    {
        if (empty($domain->name)) {
            return $this->errorResponse('Domain name is missing.');
        }

        $whois = $this->getDomainDetailsFromWhois($domain->name);

        if ($whois === null || $whois === []) {
            return $this->errorResponse('Could not fetch WHOIS data for this domain.');
        }

        $payload = [];
        $updates = [];

        if (! empty($whois['domain_registrar'])) {
            $payload['registrar_name'] = trim((string) $whois['domain_registrar']);
            $updates['registrar_name'] = $payload['registrar_name'];
        }

        if (! empty($whois['domain_registrar_id'])) {
            $domain->syncProvidersForType(
                Provider::TYPE_DOMAIN_REGISTRAR,
                [(int) $whois['domain_registrar_id']],
                (int) $whois['domain_registrar_id']
            );
        }

        if (! empty($whois['registered_on'])) {
            $payload['registered_date'] = $whois['registered_on'];
            $updates['registered_date'] = $whois['registered_on'];
        }

        if (! empty($whois['expires_on'])) {
            $payload['expiry_date'] = $whois['expires_on'];
            $updates['expiry_date'] = $whois['expires_on'];
        }

        if (! empty($whois['updated_on'])) {
            $payload['updated_date'] = $whois['updated_on'];
            $updates['updated_date'] = $whois['updated_on'];
        }

        foreach (['name_server_1', 'name_server_2', 'name_server_3', 'name_server_4'] as $nsField) {
            if (! empty($whois[$nsField])) {
                $payload[$nsField] = $whois[$nsField];
                $updates[$nsField] = $whois[$nsField];
            }
        }

        if ($payload === []) {
            return $this->infoResponse('WHOIS lookup completed but no new data was available to update.');
        }

        if ($auditUserId = $this->resolveAuditUserId()) {
            $payload['updated_by'] = $auditUserId;
        }

        $domain->fill($payload);
        $domain->save();

        return $this->successResponse('WHOIS data refreshed successfully.', $updates);
    }

    // =============================================================================
    // SSL CERTIFICATE HELPERS
    // =============================================================================

    /**
     * Get the best SSL certificate for a domain.
     */
    public function getBestSslCertificate(Domain $domain): ?Secret
    {
        $certificates = $domain->secrets()
            ->where('type', DomainSslCertificateService::SECRET_TYPE)
            ->where('is_active', true)
            ->get();

        /** @var \Illuminate\Support\Collection<int, Secret> $certificates */
        if ($certificates->isEmpty()) {
            return null;
        }

        $validCertificates = $certificates->filter(function (Secret $certificate): bool {
            if ($certificate->expires_at && $certificate->expires_at->isPast()) {
                return false;
            }

            $certData = $certificate->getMetadata('certificate');

            return ! empty($certData) && ! empty($certificate->decrypted_value);
        });

        if ($validCertificates->isEmpty()) {
            return null;
        }

        /** @var \Illuminate\Support\Collection<int, Secret> $sortedCertificates */
        $sortedCertificates = $validCertificates->sort(function (Secret $a, Secret $b): int {
            $aWildcard = (bool) $a->getMetadata('is_wildcard', false);
            $bWildcard = (bool) $b->getMetadata('is_wildcard', false);

            if ($aWildcard !== $bWildcard) {
                return $aWildcard ? -1 : 1;
            }

            $aExpiry = $a->expires_at?->getTimestamp() ?? 0;
            $bExpiry = $b->expires_at?->getTimestamp() ?? 0;

            if ($aExpiry !== $bExpiry) {
                return $bExpiry <=> $aExpiry;
            }

            $aCreated = $a->created_at?->getTimestamp() ?? 0;
            $bCreated = $b->created_at?->getTimestamp() ?? 0;

            return $bCreated <=> $aCreated;
        });

        $certificate = $sortedCertificates->first();

        return $certificate instanceof Secret ? $certificate : null;
    }

    /**
     * Check whether a certificate covers a specific domain.
     */
    public function certificateCoversDomain(Secret $certificate, string $domain): bool
    {
        $domain = strtolower(trim($domain));

        if ($domain === '') {
            return false;
        }

        $domains = $certificate->getMetadata('domains', []);
        if (! is_array($domains) || $domains === []) {
            $subject = $certificate->getMetadata('subject');
            $domains = $subject ? [$subject] : [];
        }

        foreach ($domains as $entry) {
            $entry = strtolower(trim((string) $entry));
            if ($entry === '') {
                continue;
            }

            if ($entry === $domain) {
                return true;
            }

            if (str_starts_with($entry, '*.')) {
                $suffix = substr($entry, 2);
                if ($suffix === '') {
                    continue;
                }

                if ($domain !== $suffix && str_ends_with($domain, '.'.$suffix)) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function getResourceClass(): ?string
    {
        return DomainResource::class;
    }

    protected function getEagerLoadRelationships(): array
    {
        return [
            'agency:id,name',
            'domainRegistrars:id,name',
        ];
    }

    protected function prepareCreateData(array $data): array
    {
        return $this->prepareData($data);
    }

    protected function prepareUpdateData(array $data): array
    {
        return $this->prepareData($data);
    }

    protected function afterCreate(Model $model, array $data): void
    {
        if (! $model instanceof Domain) {
            return;
        }

        $this->syncRegistrarProvider($model, $data);
    }

    protected function afterUpdate(Model $model, array $data): void
    {
        if (! $model instanceof Domain) {
            return;
        }

        $this->syncRegistrarProvider($model, $data);
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
            'info' => true,
            'message' => $message,
        ];
    }

    protected function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }

    private function prepareData(array $data): array
    {
        return [
            'name' => $data['name'] ?? $data['domain_name'] ?? null,
            'agency_id' => $data['agency_id'] ?? null,
            'type' => $data['type'] ?? null,
            'status' => $data['status'] ?? $data['status_id'] ?? 'pending',
            'dns_provider' => $data['dns_provider'] ?? null,
            'dns_zone_id' => $data['dns_zone_id'] ?? null,
            'registrar_name' => $data['registrar_name'] ?? null,
            'registered_date' => $data['registered_date'] ?? null,
            'expiry_date' => $data['expires_date'] ?? $data['expiry_date'] ?? null,
            'updated_date' => $data['updated_date'] ?? null,
            'name_server_1' => $data['domain_name_server_1'] ?? $data['name_server_1'] ?? null,
            'name_server_2' => $data['domain_name_server_2'] ?? $data['name_server_2'] ?? null,
            'name_server_3' => $data['domain_name_server_3'] ?? $data['name_server_3'] ?? null,
            'name_server_4' => $data['domain_name_server_4'] ?? $data['name_server_4'] ?? null,
            'metadata' => $data['metadata'] ?? null,
        ];
    }

    private function syncRegistrarProvider(Domain $domain, array $data): void
    {
        if (! array_key_exists('registrar_id', $data)) {
            return;
        }

        $registrarId = $data['registrar_id'] ?? null;

        if ($registrarId) {
            $domain->syncProvidersForType(Provider::TYPE_DOMAIN_REGISTRAR, [(int) $registrarId], (int) $registrarId);
            $registrar = Provider::query()->find((int) $registrarId);
            if ($registrar) {
                $domain->update(['registrar_name' => $registrar->name]);
            }

            return;
        }

        $domain->syncProvidersForType(Provider::TYPE_DOMAIN_REGISTRAR, []);
        $domain->update(['registrar_name' => null]);
    }

    // =============================================================================
    // Lifecycle Hooks
    // =============================================================================

    /**
     * Before permanently deleting a domain, cascade force-delete all associated
     * SSL certificates (secrets), DNS records, and notes.
     */
    protected function beforeForceDelete(Model $model): void
    {
        if (! $model instanceof Domain) {
            return;
        }

        // Force-delete all secrets (SSL certificates and any other stored secrets)
        $model->secrets()->forceDelete();

        // Force-delete all DNS records
        $model->dnsRecords()->forceDelete();

        // Force-delete all notes
        $model->notes()->forceDelete();
    }
}
