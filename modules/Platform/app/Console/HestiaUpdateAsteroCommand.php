<?php

namespace Modules\Platform\Console;

use App\Enums\ActivityAction;
use App\Traits\ActivityTrait;
use Exception;
use Modules\Platform\Libs\HestiaClient;
use Modules\Platform\Models\Website;

/**
 * Updates Astero for a specific website on the Hestia server.
 *
 * This command runs the 'a-update-astero' script on the server, which
 * handles downloading a specific version, setting up shared directories,
 * running migrations, and performing an atomic symlink switch.
 */
class HestiaUpdateAsteroCommand extends BaseCommand
{
    use ActivityTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'platform:hestia:update-astero {website_id : The ID of the website} {--target= : Optional specific version to update to}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update Astero to a specific version (or latest) on the server.';

    /**
     * The core logic of the command.
     *
     * @param  Website  $website  The website instance.
     *
     * @throws Exception If the Astero update fails.
     */
    protected function handleCommand(Website $website): void
    {
        $version = $this->option('target');
        $versionInfo = $version ? ' to version '.$version : ' to latest version';

        $this->info(sprintf("Updating Astero for '%s'%s.", $website->domain, $versionInfo));

        $args = [
            'arg1' => $website->website_username,
            'arg2' => $website->domain,
            'arg3' => 'main', // Package identifier
        ];

        // Add version argument if specified
        if ($version) {
            $args['arg4'] = $version;
        }

        // This command relies on a custom script 'a-update-astero' on the Hestia server.
        $response = HestiaClient::execute(
            'a-update-astero',
            $website->server,
            $args
        );

        if (! $response['success']) {
            $errorMessage = $response['message'] ?? 'Unknown error during Astero update.';
            $this->updateWebsiteStep($website, $errorMessage, 'failed');
            throw new Exception($errorMessage);
        }

        $successMessage = sprintf("Astero updated successfully for '%s'.", $website->domain);
        $this->logActivity($website, ActivityAction::UPDATE, $successMessage);
        $this->updateWebsiteStep($website, $successMessage, 'done');
    }

    /**
     * Updates the website's provisioning step for 'update_astero'.
     *
     * @param  Website  $website  The website instance.
     * @param  string  $message  The message to log for the step.
     * @param  string  $status  The status of the step (e.g., 'done', 'failed').
     */
    private function updateWebsiteStep(Website $website, string $message, string $status): void
    {
        $website->updateProvisioningStep('update_astero', $message, $status);
    }
}
