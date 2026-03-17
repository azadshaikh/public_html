<?php

namespace Modules\Platform\Console;

use App\Enums\ActivityAction;
use App\Traits\ActivityTrait;
use Exception;
use Modules\Platform\Enums\WebsiteStatus;
use Modules\Platform\Libs\HestiaClient;
use Modules\Platform\Models\Website;

/**
 * Unsuspends a website on the Hestia server.
 *
 * This command uses the 'v-unsuspend-web-domain' API call to reactivate
 * a previously suspended website.
 */
class HestiaUnsuspendWebsiteCommand extends BaseCommand
{
    use ActivityTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'platform:hestia:unsuspend-website {website_id : The ID of the website to unsuspend}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Unsuspend a website on the Hestia server.';

    /**
     * The core logic of the command.
     *
     * @param  Website  $website  The website instance.
     *
     * @throws Exception If the unsuspension process fails.
     */
    protected function handleCommand(Website $website): void
    {
        $this->info('Attempting to unsuspend website: '.$website->domain);
        $this->unsuspendWebsite($website);
        $this->info('Website unsuspended successfully.');
    }

    /**
     * Executes the website unsuspension process on the Hestia server.
     *
     * @param  Website  $website  The website instance.
     *
     * @throws Exception If the API call to unsuspend the website fails.
     */
    private function unsuspendWebsite(Website $website): void
    {
        $startTime = microtime(true);

        $arguments = [
            'arg1' => $website->website_username,
            'arg2' => $website->domain,
        ];

        $response = HestiaClient::execute(
            'v-unsuspend-web-domain',
            $website->server,
            $arguments
        );

        $processTime = round(microtime(true) - $startTime, 2);

        $this->logActivity(
            $website,
            ActivityAction::UPDATE,
            $response['message'] ?? sprintf('Website unsuspension attempt logged (completed in %ss)', $processTime),
            [
                'success' => $response['success'],
                'code' => $response['code'] ?? null,
                'website_id' => $website->site_id,
                'domain' => $website->domain,
                'process_time' => $processTime.' seconds',
            ]
        );

        if (! $response['success']) {
            throw new Exception($response['message'] ?? 'Unknown error occurred while unsuspending website.');
        }

        $website->update(['status' => WebsiteStatus::Active]);
    }
}
