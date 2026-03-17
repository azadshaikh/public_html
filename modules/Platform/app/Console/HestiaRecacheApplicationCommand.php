<?php

namespace Modules\Platform\Console;

use App\Enums\ActivityAction;
use App\Traits\ActivityTrait;
use Exception;
use Illuminate\Support\Facades\Log;
use Modules\Platform\Libs\HestiaClient;
use Modules\Platform\Models\Website;

/**
 * Runs Astero recache for a website on its Hestia server.
 */
class HestiaRecacheApplicationCommand extends BaseCommand
{
    use ActivityTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'platform:hestia:recache-application
                            {website_id : The ID of the website}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run astero:recache for a website on the remote Hestia server.';

    /**
     * The core logic of the command.
     *
     * @param  Website  $website  The website instance.
     *
     * @throws Exception If recache fails.
     */
    protected function handleCommand(Website $website): void
    {
        $server = $website->server;
        if (! $server) {
            $message = sprintf("No server found for website '%s'. Skipping application recache.", $website->domain);
            $this->warn($message);

            Log::warning('Application recache skipped: website server missing', [
                'website_id' => $website->id,
                'domain' => $website->domain,
            ]);

            return;
        }

        $this->info(sprintf("Running application recache for '%s'.", $website->domain));

        $startTime = microtime(true);

        $response = HestiaClient::execute(
            'a-recache-application',
            $server,
            [
                'arg1' => $website->website_username,
                'arg2' => $website->domain,
            ],
            180
        );

        $processTime = round(microtime(true) - $startTime, 2);

        $this->logActivity(
            $website,
            ActivityAction::UPDATE,
            'Application recache: '.($response['message'] ?? sprintf('completed in %ss', $processTime)),
            [
                'success' => $response['success'],
                'website_id' => $website->site_id,
                'domain' => $website->domain,
                'process_time' => $processTime.' seconds',
            ]
        );

        if (! $response['success']) {
            throw new Exception($response['message'] ?? 'Application recache failed.');
        }

        $this->info(sprintf("Application recache completed successfully for '%s'.", $website->domain));
    }
}
