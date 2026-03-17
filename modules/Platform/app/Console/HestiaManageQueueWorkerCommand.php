<?php

namespace Modules\Platform\Console;

use App\Enums\ActivityAction;
use App\Traits\ActivityTrait;
use Exception;
use Illuminate\Support\Facades\Log;
use Modules\Platform\Libs\HestiaClient;
use Modules\Platform\Models\Website;

/**
 * Manages Supervisor queue workers for a website.
 *
 * This command controls the queue worker lifecycle on the Hestia server
 * via the a-manage-queue-worker script. Used to stop workers when a
 * website is suspended/expired/trashed and start them when reactivated.
 */
class HestiaManageQueueWorkerCommand extends BaseCommand
{
    use ActivityTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'platform:hestia:manage-queue-worker
                            {website_id : The ID of the website}
                            {action : The action to perform (start, stop, restart, scale, remove)}
                            {--workers=1 : Number of workers for scale action}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage Supervisor queue workers for a website (start/stop/restart/scale/remove).';

    /**
     * Valid actions for the command.
     */
    protected array $validActions = ['start', 'stop', 'restart', 'scale', 'remove'];

    /**
     * The core logic of the command.
     *
     * @param  Website  $website  The website instance.
     *
     * @throws Exception If the operation fails.
     */
    protected function handleCommand(Website $website): void
    {
        $action = $this->argument('action');

        if (! in_array($action, $this->validActions)) {
            throw new Exception(sprintf("Invalid action '%s'. Valid actions: ", $action).implode(', ', $this->validActions));
        }

        $server = $website->server;
        if (! $server) {
            $message = sprintf("No server found for website '%s'. Skipping queue worker %s.", $website->domain, $action);
            $this->warn($message);

            Log::warning('Queue worker management skipped: website server missing', [
                'website_id' => $website->id,
                'domain' => $website->domain,
                'action' => $action,
            ]);

            return;
        }

        $this->info('Queue worker action: '.$action);

        $startTime = microtime(true);

        // Call the a-manage-queue-worker script on the Hestia server
        $arguments = [
            'arg1' => $website->website_username,
            'arg2' => $website->domain,
            'arg3' => $action,
        ];

        // Add worker count for scale action
        if ($action === 'scale') {
            $arguments['arg4'] = (int) $this->option('workers');
        }

        $response = HestiaClient::execute(
            'a-manage-queue-worker',
            $server,
            $arguments,
            60 // 60 second timeout
        );

        $processTime = round(microtime(true) - $startTime, 2);

        $this->logActivity(
            $website,
            ActivityAction::UPDATE,
            sprintf('Queue worker %s: ', $action).($response['message'] ?? sprintf('completed in %ss', $processTime)),
            [
                'success' => $response['success'],
                'action' => $action,
                'website_id' => $website->site_id,
                'domain' => $website->domain,
                'process_time' => $processTime.' seconds',
            ]
        );

        if (! $response['success']) {
            // Log but don't fail - queue worker management is non-critical
            $this->warn(sprintf('Queue worker %s may have failed: ', $action).($response['message'] ?? 'Unknown error'));

            return;
        }

        $this->info(sprintf('Queue worker %s completed successfully.', $action));
    }
}
