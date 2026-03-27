<?php

namespace Modules\Platform\Console;

use Exception;
use Modules\Platform\Models\Website;
use Modules\Platform\Services\BunnyPullZoneService;

class BunnyRepairCdnCommand extends BaseCommand
{
    protected $signature = 'platform:bunny:repair-cdn
                            {website_id : The ID of the website}
                            {--purge : Purge the pull-zone cache after syncing origin settings}';

    protected $description = 'Repair Bunny pull-zone origin settings for an existing website.';

    public function __construct(private readonly BunnyPullZoneService $pullZoneService)
    {
        parent::__construct();
    }

    protected function handleCommand(Website $website): void
    {
        $provider = $this->pullZoneService->resolveProvider($website);
        $pullZoneId = $this->pullZoneService->resolvePullZoneId($website);

        $this->info(sprintf('Repairing Bunny pull zone %d for %s.', $pullZoneId, $website->domain));

        $response = $this->pullZoneService->syncOriginConfiguration($website, $provider, $pullZoneId);

        if (($response['status'] ?? '') !== 'success') {
            $errorMessage = $response['message'] ?? 'Unknown error during Bunny origin repair.';
            throw new Exception('Bunny origin repair failed: '.$errorMessage);
        }

        if ($this->option('purge')) {
            $purgeResponse = $this->pullZoneService->purgeCache($provider, $pullZoneId);

            if (($purgeResponse['status'] ?? '') !== 'success') {
                $errorMessage = $purgeResponse['message'] ?? 'Unknown error while purging Bunny cache.';
                throw new Exception('Bunny cache purge failed: '.$errorMessage);
            }
        }

        $pullZone = $this->pullZoneService->fetchPullZone($provider, $pullZoneId);
        $data = $pullZone['data'] ?? [];
        $hostnames = collect($data['Hostnames'] ?? [])
            ->map(function (array $hostname): array {
                return [
                    'value' => $hostname['Value'] ?? null,
                    'has_certificate' => $hostname['HasCertificate'] ?? null,
                    'force_ssl' => $hostname['ForceSSL'] ?? null,
                ];
            })
            ->values()
            ->all();

        $website->setMetadata('cdn', $data);
        $website->save();

        $this->line('Origin URL: '.($data['OriginUrl'] ?? 'unknown'));
        $this->line('Origin host header: '.($data['OriginHostHeader'] ?? 'unknown'));
        $this->line('Forward host header: '.(($data['AddHostHeader'] ?? false) ? 'enabled' : 'disabled'));
        $this->line('Follow redirects: '.(($data['FollowRedirects'] ?? false) ? 'enabled' : 'disabled'));
        $this->line('Strip response cookies: '.(($data['DisableCookies'] ?? false) ? 'enabled' : 'disabled'));
        $this->line('Auto SSL: '.(($data['EnableAutoSSL'] ?? false) ? 'enabled' : 'disabled'));
        $this->line('Hostnames: '.json_encode($hostnames, JSON_UNESCAPED_SLASHES));
        $this->info('Bunny CDN repair completed successfully.');
    }
}
