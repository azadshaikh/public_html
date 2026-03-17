<?php

namespace Modules\Platform\Console;

use App\Enums\ActivityAction;
use App\Traits\ActivityTrait;
use Exception;
use Modules\Platform\Enums\WebsiteStatus;
use Modules\Platform\Libs\HestiaClient;
use Modules\Platform\Models\Website;

/**
 * Suspends a website on the Hestia server.
 *
 * This command uses the 'v-suspend-web-domain' API call to temporarily
 * disable a website without deleting its files or configuration.
 */
class HestiaSuspendWebsiteCommand extends BaseCommand
{
    use ActivityTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'platform:hestia:suspend-website {website_id : The ID of the website to suspend}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Suspend a website on the Hestia server.';

    /**
     * The core logic of the command.
     *
     * @param  Website  $website  The website instance.
     *
     * @throws Exception If the suspension process fails.
     */
    protected function handleCommand(Website $website): void
    {
        $this->info('Attempting to suspend website: '.$website->domain);
        $this->suspendWebsite($website);
        $this->info('Website suspended successfully.');
    }

    /**
     * Executes the website suspension process on the Hestia server.
     *
     * @param  Website  $website  The website instance.
     *
     * @throws Exception If the API call to suspend the website fails.
     */
    private function suspendWebsite(Website $website): void
    {
        $startTime = microtime(true);

        $arguments = [
            'arg1' => $website->website_username,
            'arg2' => $website->domain,
        ];

        $response = HestiaClient::execute(
            'v-suspend-web-domain',
            $website->server,
            $arguments
        );

        $processTime = round(microtime(true) - $startTime, 2);

        $this->logActivity(
            $website,
            ActivityAction::UPDATE,
            $response['message'] ?? sprintf('Website suspension attempt logged (completed in %ss)', $processTime),
            [
                'success' => $response['success'],
                'code' => $response['code'] ?? null,
                'website_id' => $website->site_id,
                'domain' => $website->domain,
                'process_time' => $processTime.' seconds',
            ]
        );

        if (! $response['success']) {
            throw new Exception($response['message'] ?? 'Unknown error occurred while suspending website.');
        }

        $website->update(['status' => WebsiteStatus::Suspended]);
    }
}
