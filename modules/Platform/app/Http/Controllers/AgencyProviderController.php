<?php

namespace Modules\Platform\Http\Controllers;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Platform\Http\Requests\AgencyAttachCdnProvidersRequest;
use Modules\Platform\Http\Requests\AgencyAttachDnsProvidersRequest;
use Modules\Platform\Models\Agency;
use Modules\Platform\Models\Provider;

class AgencyProviderController extends Controller
{
    /**
     * Get DNS providers associated with an agency
     */
    public function getDnsProviders($id): JsonResponse
    {
        /** @var Agency $agency */
        $agency = Agency::query()->findOrFail($id);

        /** @var Collection<int, Provider> $providersCollection */
        $providersCollection = $agency->dnsProviders()
            ->withPivot('is_primary')
            ->get();

        $providers = [];
        foreach ($providersCollection as $provider) {
            $providers[] = [
                'id' => $provider->id,
                'name' => $provider->name,
                'href' => route('platform.providers.show', $provider),
                'vendor' => $provider->vendor,
                'vendor_label' => $provider->vendor_label,
                'type' => $provider->type,
                'type_label' => $provider->type_label,
                'status' => $provider->status,
                'status_label' => $provider->status_label,
                'is_primary' => (bool) ($provider->pivot->is_primary ?? false),
                'can_remove' => true,
            ];
        }

        return response()->json([
            'success' => true,
            'providers' => $providers,
        ]);
    }

    /**
     * Get CDN providers associated with an agency
     */
    public function getCdnProviders($id): JsonResponse
    {
        /** @var Agency $agency */
        $agency = Agency::query()->findOrFail($id);

        /** @var Collection<int, Provider> $providersCollection */
        $providersCollection = $agency->cdnProviders()
            ->withPivot('is_primary')
            ->get();

        $providers = [];
        foreach ($providersCollection as $provider) {
            $providers[] = [
                'id' => $provider->id,
                'name' => $provider->name,
                'href' => route('platform.providers.show', $provider),
                'vendor' => $provider->vendor,
                'vendor_label' => $provider->vendor_label,
                'type' => $provider->type,
                'type_label' => $provider->type_label,
                'status' => $provider->status,
                'status_label' => $provider->status_label,
                'is_primary' => (bool) ($provider->pivot->is_primary ?? false),
                'can_remove' => true,
            ];
        }

        return response()->json([
            'success' => true,
            'providers' => $providers,
        ]);
    }

    /**
     * Attach DNS providers to an agency
     */
    public function attachDnsProviders(AgencyAttachDnsProvidersRequest $request, $id): JsonResponse
    {
        /** @var Agency $agency */
        $agency = Agency::query()->findOrFail($id);

        $providerIds = $request->input('provider_ids', []);
        $primaryProviderId = $request->input('primary_provider_id');

        // Sync DNS providers
        $agency->syncProvidersForType(Provider::TYPE_DNS, $providerIds, $primaryProviderId);

        activity()
            ->performedOn($agency)
            ->causedBy(auth()->user())
            ->withProperties(['provider_ids' => $providerIds, 'primary_provider_id' => $primaryProviderId, 'type' => 'dns'])
            ->log('updated_agency_dns_providers');

        return response()->json([
            'success' => true,
            'message' => 'Agency DNS providers updated successfully',
        ]);
    }

    /**
     * Attach CDN providers to an agency
     */
    public function attachCdnProviders(AgencyAttachCdnProvidersRequest $request, $id): JsonResponse
    {
        /** @var Agency $agency */
        $agency = Agency::query()->findOrFail($id);

        $providerIds = $request->input('provider_ids', []);
        $primaryProviderId = $request->input('primary_provider_id');

        // Sync CDN providers
        $agency->syncProvidersForType(Provider::TYPE_CDN, $providerIds, $primaryProviderId);

        activity()
            ->performedOn($agency)
            ->causedBy(auth()->user())
            ->withProperties(['provider_ids' => $providerIds, 'primary_provider_id' => $primaryProviderId, 'type' => 'cdn'])
            ->log('updated_agency_cdn_providers');

        return response()->json([
            'success' => true,
            'message' => 'Agency CDN providers updated successfully',
        ]);
    }

    /**
     * Detach a provider from an agency
     */
    public function detachProvider($id, $provider): JsonResponse
    {
        /** @var Agency $agency */
        $agency = Agency::query()->findOrFail($id);
        /** @var Provider $providerModel */
        $providerModel = Provider::query()->findOrFail($provider);

        $agency->removeProvider($providerModel->id);

        activity()
            ->performedOn($agency)
            ->causedBy(auth()->user())
            ->withProperties(['provider_id' => $providerModel->id, 'type' => $providerModel->type])
            ->log('detached_provider_from_agency');

        return response()->json([
            'success' => true,
            'message' => 'Provider removed from agency',
        ]);
    }

    /**
     * Set primary DNS provider for an agency
     */
    public function setPrimaryDnsProvider($id, $provider): JsonResponse
    {
        /** @var Agency $agency */
        $agency = Agency::query()->findOrFail($id);
        /** @var Provider $providerModel */
        $providerModel = Provider::query()->findOrFail($provider);

        if ($providerModel->type !== Provider::TYPE_DNS) {
            return response()->json([
                'success' => false,
                'message' => 'Provider is not a DNS provider',
            ], 422);
        }

        if (! $agency->dnsProviders()->where('platform_providers.id', $providerModel->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Provider is not attached to this agency',
            ], 422);
        }

        $agency->setPrimaryProvider(Provider::TYPE_DNS, $providerModel->id);

        activity()
            ->performedOn($agency)
            ->causedBy(auth()->user())
            ->withProperties(['provider_id' => $providerModel->id])
            ->log('set_primary_dns_provider');

        return response()->json([
            'success' => true,
            'message' => 'Primary DNS provider updated',
        ]);
    }

    /**
     * Set primary CDN provider for an agency
     */
    public function setPrimaryCdnProvider($id, $provider): JsonResponse
    {
        /** @var Agency $agency */
        $agency = Agency::query()->findOrFail($id);
        /** @var Provider $providerModel */
        $providerModel = Provider::query()->findOrFail($provider);

        if ($providerModel->type !== Provider::TYPE_CDN) {
            return response()->json([
                'success' => false,
                'message' => 'Provider is not a CDN provider',
            ], 422);
        }

        if (! $agency->cdnProviders()->where('platform_providers.id', $providerModel->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Provider is not attached to this agency',
            ], 422);
        }

        $agency->setPrimaryProvider(Provider::TYPE_CDN, $providerModel->id);

        activity()
            ->performedOn($agency)
            ->causedBy(auth()->user())
            ->withProperties(['provider_id' => $providerModel->id])
            ->log('set_primary_cdn_provider');

        return response()->json([
            'success' => true,
            'message' => 'Primary CDN provider updated',
        ]);
    }

    /**
     * Get available DNS providers that can be associated with this agency
     */
    public function getAvailableDnsProviders($id): JsonResponse
    {
        /** @var Agency $agency */
        $agency = Agency::query()->findOrFail($id);

        $attachedIds = $agency->dnsProviders()->pluck('platform_providers.id')->toArray();

        $availableProviders = Provider::query()->active()
            ->dns()
            ->whereNotIn('id', $attachedIds)
            ->orderBy('name')
            ->get(['id', 'name', 'vendor', 'type', 'status']);

        $providers = [];
        foreach ($availableProviders as $provider) {
            $providers[] = [
                'id' => $provider->id,
                'name' => $provider->name,
                'href' => route('platform.providers.show', $provider),
                'vendor' => $provider->vendor,
                'vendor_label' => $provider->vendor_label,
                'type' => $provider->type,
                'type_label' => $provider->type_label,
                'status' => $provider->status,
                'status_label' => $provider->status_label,
            ];
        }

        return response()->json([
            'success' => true,
            'providers' => $providers,
        ]);
    }

    /**
     * Get available CDN providers that can be associated with this agency
     */
    public function getAvailableCdnProviders($id): JsonResponse
    {
        /** @var Agency $agency */
        $agency = Agency::query()->findOrFail($id);

        $attachedIds = $agency->cdnProviders()->pluck('platform_providers.id')->toArray();

        $availableProviders = Provider::query()->active()
            ->cdn()
            ->whereNotIn('id', $attachedIds)
            ->orderBy('name')
            ->get(['id', 'name', 'vendor', 'type', 'status']);

        $providers = [];
        foreach ($availableProviders as $provider) {
            $providers[] = [
                'id' => $provider->id,
                'name' => $provider->name,
                'href' => route('platform.providers.show', $provider),
                'vendor' => $provider->vendor,
                'vendor_label' => $provider->vendor_label,
                'type' => $provider->type,
                'type_label' => $provider->type_label,
                'status' => $provider->status,
                'status_label' => $provider->status_label,
            ];
        }

        return response()->json([
            'success' => true,
            'providers' => $providers,
        ]);
    }
}
