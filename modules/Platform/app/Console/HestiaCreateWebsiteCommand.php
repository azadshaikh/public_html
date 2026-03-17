<?php

namespace Modules\Platform\Console;

use App\Enums\ActivityAction;
use App\Traits\ActivityTrait;
use Exception;
use Modules\Platform\Libs\HestiaClient;
use Modules\Platform\Models\Website;

/**
 * Creates a new web domain on the Hestia server for a given website.
 *
 * This command uses the custom 'a-create-web-domain' script which combines:
 * - v-add-web-domain (creates the domain)
 * - v-change-web-domain-tpl (sets nginx web template)
 * - v-change-web-domain-backend-tpl (sets backend template)
 */
class HestiaCreateWebsiteCommand extends BaseCommand
{
    use ActivityTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'platform:hestia:create-website {website_id : The ID of the website}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new Hestia web domain for a website.';

    /**
     * The step key for this command.
     */
    protected ?string $stepKey = 'create_website';

    /**
     * The core logic of the command.
     *
     * @param  Website  $website  The website instance.
     *
     * @throws Exception If any step of the website creation fails.
     */
    protected function handleCommand(Website $website): void
    {
        $this->info(sprintf("Creating web domain for '%s'.", $website->domain));

        // Resolve plan-specific PHP-FPM backend template from config
        $planConfig = config('astero.website_plans.'.$website->plan_tier, []);
        $backendTemplate = $planConfig['backend_template'] ?? 'astero-basic';

        // Use custom script that combines all 3 commands into one API call
        $response = HestiaClient::execute(
            'a-create-web-domain',
            $website->server,
            [
                $website->website_username,
                $website->domain,
                'astero-active',
                $backendTemplate,
            ]
        );

        if (! $response['success']) {
            $errorMessage = 'Failed to create web domain: '.$response['message'];
            $this->updateWebsiteStep($website, $errorMessage, 'failed');
            throw new Exception($errorMessage);
        }

        $this->logActivity($website, ActivityAction::CREATE, sprintf("Web domain '%s' created and configured.", $website->domain));
        $this->updateWebsiteStep($website, sprintf("Web domain '%s' created and configured successfully.", $website->domain), 'done');
    }

    /**
     * Updates the website's provisioning step for 'create_website'.
     *
     * @param  Website  $website  The website instance.
     * @param  string  $message  The message to log for the step.
     * @param  string  $status  The status of the step (e.g., 'done', 'failed').
     */
    private function updateWebsiteStep(Website $website, string $message, string $status): void
    {
        $website->updateProvisioningStep('create_website', $message, $status);
    }
}
