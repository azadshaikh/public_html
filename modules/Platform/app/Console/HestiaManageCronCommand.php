<?php

namespace Modules\Platform\Console;

use App\Enums\ActivityAction;
use App\Traits\ActivityTrait;
use Exception;
use Modules\Platform\Libs\HestiaClient;
use Modules\Platform\Models\Website;

/**
 * Manages the website's scheduler cron job.
 *
 * This command suspends or unsuspends the cron job on the Hestia server
 * via the a-manage-cron script. Used to pause scheduled tasks when a
 * website is suspended/expired/trashed and resume when reactivated.
 */
class HestiaManageCronCommand extends BaseCommand
{
    use ActivityTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'platform:hestia:manage-cron
                            {website_id : The ID of the website}
                            {action : The action to perform (suspend, unsuspend)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage the scheduler cron job for a website (suspend/unsuspend).';

    /**
     * Valid actions for the command.
     */
    protected array $validActions = ['suspend', 'unsuspend'];

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

        $this->info('Cron job action: '.$action);

        $startTime = microtime(true);

        // Call the a-manage-cron script on the Hestia server
        $arguments = [
            'arg1' => $website->website_username,
            'arg2' => $website->domain,
            'arg3' => $action,
        ];

        $response = HestiaClient::execute(
            'a-manage-cron',
            $website->server,
            $arguments,
            60 // 60 second timeout
        );

        $processTime = round(microtime(true) - $startTime, 2);

        $this->logActivity(
            $website,
            ActivityAction::UPDATE,
            sprintf('Cron job %s: ', $action).($response['message'] ?? sprintf('completed in %ss', $processTime)),
            [
                'success' => $response['success'],
                'action' => $action,
                'website_id' => $website->site_id,
                'domain' => $website->domain,
                'process_time' => $processTime.' seconds',
            ]
        );

        if (! $response['success']) {
            // Log but don't fail - cron management is non-critical
            $this->warn(sprintf('Cron job %s may have failed: ', $action).($response['message'] ?? 'Unknown error'));

            return;
        }

        $this->info(sprintf('Cron job %s completed successfully.', $action));
    }
}
