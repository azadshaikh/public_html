<?php

namespace Modules\Platform\Traits;

use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Modules\Platform\Models\Provider;

/**
 * Trait Providerable
 *
 * Provides polymorphic many-to-many relationships for models that can have
 * multiple providers (DNS, CDN, server, domain registrar, etc.)
 *
 * Usage: Add `use Providerable;` to your model.
 */
trait Providerable
{
    /**
     * Get all providers for this model.
     */
    public function providers(): MorphToMany
    {
        return $this->morphToMany(Provider::class, 'providerable', 'platform_providerables')
            ->withPivot('is_primary');
    }

    /**
     * Get providers filtered by type.
     */
    public function providersOfType(string $type): MorphToMany
    {
        return $this->morphToMany(Provider::class, 'providerable', 'platform_providerables')
            ->where('type', $type)
            ->withPivot('is_primary');
    }

    /**
     * Get the primary provider for a specific type.
     */
    public function primaryProvider(string $type): MorphToMany
    {
        return $this->morphToMany(Provider::class, 'providerable', 'platform_providerables')
            ->where('type', $type)
            ->wherePivot('is_primary', true)
            ->withPivot('is_primary');
    }

    /**
     * Get the primary DNS provider.
     */
    public function primaryDnsProvider(): MorphToMany
    {
        return $this->primaryProvider(Provider::TYPE_DNS);
    }

    /**
     * Get the primary CDN provider.
     */
    public function primaryCdnProvider(): MorphToMany
    {
        return $this->primaryProvider(Provider::TYPE_CDN);
    }

    /**
     * Get the primary server provider.
     */
    public function primaryServerProvider(): MorphToMany
    {
        return $this->primaryProvider(Provider::TYPE_SERVER);
    }

    /**
     * Get the primary domain registrar.
     */
    public function primaryDomainRegistrar(): MorphToMany
    {
        return $this->primaryProvider(Provider::TYPE_DOMAIN_REGISTRAR);
    }

    /**
     * Get all DNS providers.
     */
    public function dnsProviders(): MorphToMany
    {
        return $this->providersOfType(Provider::TYPE_DNS);
    }

    /**
     * Get all CDN providers.
     */
    public function cdnProviders(): MorphToMany
    {
        return $this->providersOfType(Provider::TYPE_CDN);
    }

    /**
     * Get all server providers.
     */
    public function serverProviders(): MorphToMany
    {
        return $this->providersOfType(Provider::TYPE_SERVER);
    }

    /**
     * Get all domain registrars.
     */
    public function domainRegistrars(): MorphToMany
    {
        return $this->providersOfType(Provider::TYPE_DOMAIN_REGISTRAR);
    }

    /**
     * Assign a provider to the model.
     *
     * @param  int|Provider  $provider  Provider ID or instance
     * @param  bool  $primary  Whether this should be the primary provider for its type
     */
    public function assignProvider($provider, bool $primary = false): void
    {
        $providerId = $provider instanceof Provider ? $provider->id : $provider;
        $providerModel = $provider instanceof Provider ? $provider : Provider::query()->find($providerId);

        if (! $providerModel) {
            return;
        }

        if ($primary) {
            // Remove primary flag from other providers of the same type
            $this->providers()
                ->where('type', $providerModel->type)
                ->updateExistingPivot(
                    $this->providersOfType($providerModel->type)->pluck('platform_providers.id')->toArray(),
                    ['is_primary' => false]
                );
        }

        $this->providers()->syncWithoutDetaching([
            $providerId => ['is_primary' => $primary],
        ]);
    }

    /**
     * Sync providers for a specific type with optional primary.
     *
     * @param  string  $type  Provider type (dns, cdn, server, domain_registrar)
     * @param  array  $ids  Array of provider IDs
     * @param  int|null  $primaryId  ID of the primary provider
     */
    public function syncProvidersForType(string $type, array $ids, ?int $primaryId = null): void
    {
        // Get current providers of this type
        $currentIds = $this->providersOfType($type)->pluck('platform_providers.id')->toArray();

        // Detach providers of this type that are not in the new list
        $toDetach = array_diff($currentIds, $ids);
        if ($toDetach !== []) {
            $this->providers()->detach($toDetach);
        }

        // Prepare pivot data
        $pivotData = [];
        foreach ($ids as $id) {
            $pivotData[$id] = ['is_primary' => ($id === $primaryId)];
        }

        // Sync the new providers
        if ($pivotData !== []) {
            $this->providers()->syncWithoutDetaching($pivotData);
        }
    }

    /**
     * Sync all providers with optional primary per type.
     *
     * @param  array  $providerData  Array of ['id' => X, 'type' => Y, 'is_primary' => bool]
     */
    public function syncProviders(array $providerData): void
    {
        $pivotData = [];
        foreach ($providerData as $data) {
            $pivotData[$data['id']] = ['is_primary' => $data['is_primary'] ?? false];
        }

        $this->providers()->sync($pivotData);
    }

    /**
     * Set the primary provider for a specific type.
     *
     * @param  string  $type  Provider type
     * @param  int  $providerId  Provider ID to set as primary
     */
    public function setPrimaryProvider(string $type, int $providerId): void
    {
        // First, unset all primary flags for this type
        $typeProviderIds = $this->providersOfType($type)->pluck('platform_providers.id')->toArray();

        foreach ($typeProviderIds as $id) {
            $this->providers()->updateExistingPivot($id, ['is_primary' => ($id === $providerId)]);
        }
    }

    /**
     * Get the first/primary provider for a type (helper for single provider access).
     */
    public function getProvider(string $type): ?Provider
    {
        /** @var Provider|null $primaryProvider */
        $primaryProvider = $this->primaryProvider($type)->first();

        if ($primaryProvider !== null) {
            return $primaryProvider;
        }

        /** @var Provider|null $provider */
        $provider = $this->providersOfType($type)->first();

        return $provider;
    }

    /**
     * Check if this model has a provider of a specific type.
     */
    public function hasProviderOfType(string $type): bool
    {
        return $this->providersOfType($type)->exists();
    }

    /**
     * Check if this model has a specific provider.
     */
    public function hasProvider(int $providerId): bool
    {
        return $this->providers()->where('platform_providers.id', $providerId)->exists();
    }

    /**
     * Remove a provider from this model.
     */
    public function removeProvider(int $providerId): void
    {
        $this->providers()->detach($providerId);
    }

    /**
     * Remove all providers of a specific type from this model.
     */
    public function removeProvidersOfType(string $type): void
    {
        $ids = $this->providersOfType($type)->pluck('platform_providers.id')->toArray();
        $this->providers()->detach($ids);
    }
}
