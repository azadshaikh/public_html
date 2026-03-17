<?php

namespace Modules\Platform\Console;

use App\Enums\ActivityAction;
use App\Traits\ActivityTrait;
use Exception;
use Modules\Platform\Libs\HestiaClient;
use Modules\Platform\Models\Website;

/**
 * Clears caches for a website (BunnyCDN and rebuilds nginx config).
 *
 * This command purges the BunnyCDN pullzone cache and rebuilds the
 * web domain configuration on the Hestia server to ensure changes take effect.
 */
class HestiaClearCacheCommand extends BaseCommand
{
    use ActivityTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'platform:hestia:clear-cache
                            {website_id : The ID of the website}
                            {--cdn : Clear BunnyCDN cache only}
                            {--nginx : Rebuild nginx configuration only}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear caches for a website (BunnyCDN and/or nginx rebuild).';

    /**
     * The core logic of the command.
     *
     * @param  Website  $website  The website instance.
     *
     * @throws Exception If the cache clearing fails.
     */
    protected function handleCommand(Website $website): void
    {
        $cdnOnly = $this->option('cdn');
        $nginxOnly = $this->option('nginx');

        // If no specific option, do both
        $clearCdn = ! $nginxOnly || $cdnOnly;
        $clearNginx = ! $cdnOnly || $nginxOnly;

        if ($clearCdn && ! empty($website->pullzone_id)) {
            $this->clearCdnCache();
        }

        if ($clearNginx) {
            $this->rebuildWebDomain($website);
        }

        $this->info('Cache clearing completed successfully.');
    }

    /**
     * Clears the CDN cache for the website.
     */
    private function clearCdnCache(): void
    {
        $this->info('Clearing CDN cache...');
        $this->warn('CDN cache clearing not yet implemented.');
    }

    /**
     * Rebuilds the web domain configuration on Hestia server.
     *
     * @param  Website  $website  The website instance.
     *
     * @throws Exception If the API call fails.
     */
    private function rebuildWebDomain(Website $website): void
    {
        $this->info('Rebuilding web domain configuration...');

        $startTime = microtime(true);

        $arguments = [
            'arg1' => $website->website_username,
            'arg2' => $website->domain,
            'arg3' => 'yes', // restart web server
        ];

        $response = HestiaClient::execute(
            'v-rebuild-web-domain',
            $website->server,
            $arguments,
            120 // Extended timeout for rebuild
        );

        $processTime = round(microtime(true) - $startTime, 2);

        $this->logActivity(
            $website,
            ActivityAction::UPDATE,
            $response['message'] ?? sprintf('Web domain rebuilt (completed in %ss)', $processTime),
            [
                'success' => $response['success'],
                'code' => $response['code'] ?? null,
                'website_id' => $website->site_id,
                'domain' => $website->domain,
                'process_time' => $processTime.' seconds',
            ]
        );

        if (! $response['success']) {
            throw new Exception($response['message'] ?? 'Unknown error occurred while rebuilding web domain.');
        }

        $this->info('Web domain rebuilt successfully.');
    }
}
