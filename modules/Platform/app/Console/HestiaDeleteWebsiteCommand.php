<?php

namespace Modules\Platform\Console;

use App\Enums\ActivityAction;
use App\Traits\ActivityTrait;
use Exception;
use Illuminate\Support\Facades\Log;
use Modules\Platform\Libs\HestiaClient;
use Modules\Platform\Models\Website;

/**
 * Deletes a website and its associated user from the Hestia server.
 *
 * This command handles the complete removal of a website's presence from the server
 * by deleting the system user, which automatically removes all web domains, databases,
 * mail accounts, and other resources associated with that user.
 *
 * It should be used with caution as this action is irreversible.
 */
class HestiaDeleteWebsiteCommand extends BaseCommand
{
    use ActivityTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'platform:hestia:delete-website {website_id : The ID of the website to delete}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete a website and its user from the Hestia server.';

    /**
     * The core logic of the command.
     *
     * @param  Website  $website  The website instance.
     *
     * @throws Exception If the deletion process fails.
     */
    protected function handleCommand(Website $website): void
    {
        $this->info('Processing deletion for website: '.$website->domain);

        // Delete the user - this automatically deletes all web domains, databases, etc.
        // Hestia automatically removes everything associated with the user
        $this->deleteUser($website);

        $this->info(sprintf('Website %s and all associated resources have been deleted from Hestia.', $website->domain));
    }

    /**
     * Deletes the system user from the Hestia server.
     * This automatically deletes all web domains, databases, mail accounts, etc. associated with the user.
     *
     * @param  Website  $website  The website instance.
     *
     * @throws Exception If the API call to delete the user fails.
     */
    private function deleteUser(Website $website): void
    {
        $this->info(sprintf('Attempting to delete user: %s (this will delete all associated resources)', $website->website_username));

        $server = $website->server;
        if (! $server) {
            $message = sprintf("No server found for website '%s'. Assuming server resources are already removed.", $website->domain);
            $this->warn($message);

            Log::warning('Hestia website deletion skipped: website server missing', [
                'website_id' => $website->id,
                'domain' => $website->domain,
                'website_username' => $website->website_username,
            ]);

            return;
        }

        $response = HestiaClient::execute(
            'v-delete-user',
            $server,
            [
                'arg1' => $website->website_username,
                'arg2' => 'yes', // Force deletion
            ]
        );

        if (! $response['success']) {
            // Hestia code 3 = "Object doesn't exist" — user was already removed from server
            if (isset($response['code']) && $response['code'] === 3) {
                $this->warn(sprintf("User '%s' does not exist on the server (already deleted). Continuing.", $website->website_username));
                $this->logActivity($website, ActivityAction::DELETE, sprintf("User '%s' was already removed from the server.", $website->website_username));

                return;
            }

            $errorMessage = $response['message'] ?? sprintf("Failed to delete user '%s'.", $website->website_username);
            $this->error($errorMessage);

            // Log activity without updating provisioning steps (website may be deleted)
            $this->logActivity($website, ActivityAction::DELETE, $errorMessage);

            throw new Exception($errorMessage);
        }

        $this->logActivity($website, ActivityAction::DELETE, sprintf("User '%s' and all associated resources deleted successfully from Hestia.", $website->website_username));
        $this->info(sprintf("User '%s' and all associated resources deleted successfully.", $website->website_username));
    }
}
