<?php

namespace Modules\Platform\Console;

use App\Enums\ActivityAction;
use App\Traits\ActivityTrait;
use Exception;
use Illuminate\Console\Command;
use Modules\Platform\Libs\HestiaClient;
use Modules\Platform\Models\Website;

/**
 * Reverts a website's Astero installation to a previous version based on update history.
 *
 * This command uses the 'a-revert-astero-updates' script on the Hestia
 * server to roll back the application files to a specific version.
 */
class HestiaRevertAsteroUpdatesCommand extends Command
{
    use ActivityTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'platform:hestia:revert-astero-updates {website_id : The ID of the website} {version : The version to revert to}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Revert Astero to a specific version.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            /** @var Website $website */
            $website = Website::query()->findOrFail($this->argument('website_id'));
            $this->handleCommand($website);

            return self::SUCCESS;
        } catch (Exception $exception) {
            $this->error('An error occurred: '.$exception->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * The core logic of the command.
     *
     * @param  Website  $website  The website instance.
     *
     * @throws Exception If the Astero revert fails.
     */
    protected function handleCommand(Website $website): void
    {
        $this->info(sprintf("Reverting Astero for '%s' to version '%s'.", $website->domain, $this->argument('version')));

        // This command relies on a custom script 'a-revert-astero-updates' on the Hestia server.
        $response = HestiaClient::execute(
            'a-revert-astero-updates',
            $website->server,
            [
                'arg1' => $website->website_username,
                'arg2' => $website->domain,
                'arg3' => 'main', // Package identifier
                'arg4' => $this->argument('version'),
            ]
        );

        if (! $response['success']) {
            $errorMessage = $response['message'] ?? 'Unknown error during Astero revert.';
            $this->updateWebsiteStep($website, $errorMessage, 'failed');
            throw new Exception($errorMessage);
        }

        $successMessage = sprintf("Astero reverted successfully for '%s'.", $website->domain);
        $this->logActivity($website, ActivityAction::UPDATE, $successMessage);
        $this->updateWebsiteStep($website, $successMessage, 'done');
    }

    /**
     * Updates the website's provisioning step for 'revert_astero'.
     *
     * @param  Website  $website  The website instance.
     * @param  string  $message  The message to log for the step.
     * @param  string  $status  The status of the step (e.g., 'done', 'failed').
     */
    private function updateWebsiteStep(Website $website, string $message, string $status): void
    {
        $website->updateProvisioningStep('revert_astero', $message, $status);
    }
}
