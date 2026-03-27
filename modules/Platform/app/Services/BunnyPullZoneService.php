<?php

namespace Modules\Platform\Services;

use Exception;
use Modules\Platform\Libs\BunnyApi;
use Modules\Platform\Models\Provider;
use Modules\Platform\Models\Website;

class BunnyPullZoneService
{
    public function resolveProvider(Website $website): Provider
    {
        $provider = $website->cdnProvider ?? $website->dnsProvider;

        throw_unless(
            $provider && $provider->vendor === 'bunny',
            Exception::class,
            'No Bunny CDN provider is associated with this website.'
        );

        return $provider;
    }

    public function resolvePullZoneId(Website $website): int
    {
        $pullZoneId = (int) $website->getMetadata('cdn.Id');

        throw_unless($pullZoneId, Exception::class, 'Pull zone ID not found in website CDN metadata.');

        return $pullZoneId;
    }

    public function resolveOriginUrl(Website $website): string
    {
        $server = $website->server;

        throw_unless($server?->ip, Exception::class, 'Server IP is not available for Bunny origin configuration.');

        return 'https://'.$server->ip;
    }

    public function syncOriginConfiguration(Website $website, Provider $provider, int $pullZoneId): array
    {
        $originUrl = $this->resolveOriginUrl($website);

        return BunnyApi::updatePullZone($provider, $pullZoneId, [
            'OriginUrl' => $originUrl,
            'OriginHostHeader' => $website->domain,
            'AddHostHeader' => false,
            'FollowRedirects' => false,
            'EnableAutoSSL' => true,
        ]);
    }

    public function fetchPullZone(Provider $provider, int $pullZoneId): array
    {
        return BunnyApi::getPullZone($provider, $pullZoneId);
    }

    public function purgeCache(Provider $provider, int $pullZoneId): array
    {
        return BunnyApi::purgePullZoneCache($provider, $pullZoneId);
    }
}
