<?php

namespace Modules\Platform\Console;

use App\Enums\ActivityAction;
use App\Traits\ActivityTrait;
use Exception;
use Modules\Platform\Libs\HestiaClient;
use Modules\Platform\Models\Website;

/**
 * Sets up Supervisor-managed queue workers for a website.
 *
 * This command creates the Supervisor configuration on the Hestia server
 * via the a-setup-queue-worker script. Used to configure queue workers
 * for existing websites that don't have them configured.
 */
class HestiaSetupQueueWorkerCommand extends BaseCommand
{
    use ActivityTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'platform:hestia:setup-queue-worker
                            {website_id : The ID of the website}
                            {--workers=1 : Number of workers to configure}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Setup Supervisor queue workers for a website.';

    /**
     * The core logic of the command.
     *
     * @param  Website  $website  The website instance.
     *
     * @throws Exception If the operation fails.
     */
    protected function handleCommand(Website $website): void
    {
        $numWorkers = (int) $this->option('workers') ?: 1;

        $this->info(sprintf('Setting up queue workers (%s workers)...', $numWorkers));

        $startTime = microtime(true);

        // Call the a-setup-queue-worker script on the Hestia server
        $arguments = [
            'arg1' => $website->website_username,
            'arg2' => $website->domain,
            'arg3' => $numWorkers,
        ];

        $response = HestiaClient::execute(
            'a-setup-queue-worker',
            $website->server,
            $arguments,
            120 // 120 second timeout
        );

        $processTime = round(microtime(true) - $startTime, 2);

        $this->logActivity(
            $website,
            ActivityAction::UPDATE,
            'Queue worker setup: '.($response['message'] ?? sprintf('completed in %ss', $processTime)),
            [
                'success' => $response['success'],
                'num_workers' => $numWorkers,
                'website_id' => $website->site_id,
                'domain' => $website->domain,
                'process_time' => $processTime.' seconds',
            ]
        );

        if (! $response['success']) {
            throw new Exception('Queue worker setup failed: '.($response['message'] ?? 'Unknown error'));
        }

        $this->info('Queue workers setup completed successfully.');
    }
}
