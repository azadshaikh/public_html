<?php

namespace Modules\Platform\Console;

use App\Enums\ActivityAction;
use App\Traits\ActivityTrait;
use Exception;
use Modules\Platform\Libs\HestiaClient;
use Modules\Platform\Models\Website;

/**
 * Generates and installs a self-signed SSL certificate for a website on the Hestia server.
 *
 * This command is used during the initial provisioning of a website to ensure it is
 * accessible via HTTPS, even before a formal SSL certificate is issued.
 */
class HestiaGenerateSslCommand extends BaseCommand
{
    use ActivityTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'platform:hestia:generate-ssl {website_id : The ID of the website}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate and install a self-signed SSL certificate for a website.';

    /**
     * The step key for this command.
     */
    protected ?string $stepKey = 'generate_ssl';

    /**
     * The core logic of the command.
     *
     * @param  Website  $website  The website instance.
     *
     * @throws Exception If the SSL generation fails.
     */
    protected function handleCommand(Website $website): void
    {
        $this->info(sprintf("Generating self-signed SSL for '%s'.", $website->domain));

        // Note: This command uses a custom script 'a-generate-ssl-certificate' on the Hestia server.
        // This script is expected to handle the generation and installation of the certificate.
        $response = HestiaClient::execute(
            'a-generate-ssl-certificate',
            $website->server,
            [
                'arg1' => $website->website_username,
                'arg2' => $website->domain,
                'arg3' => 'support@astero.in', // Email
                'arg4' => 'UK',                         // Country
                'arg5' => 'London',                     // State
                'arg6' => 'Covent Garden',              // City
                'arg7' => 'AsteroDigital',              // Organization
                'arg8' => 'IT',                         // Department
            ],
            60 // Timeout in seconds
        );

        if (! $response['success']) {
            $errorMessage = $response['message'] ?? 'Unknown error during SSL generation.';
            $this->updateWebsiteStep($website, $errorMessage, 'failed');
            throw new Exception($errorMessage);
        }

        $successMessage = sprintf("Self-signed SSL certificate for '%s' generated and installed successfully.", $website->domain);
        $this->logActivity($website, ActivityAction::UPDATE, $successMessage);
        $this->updateWebsiteStep($website, $successMessage, 'done');
    }

    /**
     * Updates the website's provisioning step for 'generate_ssl'.
     *
     * @param  Website  $website  The website instance.
     * @param  string  $message  The message to log for the step.
     * @param  string  $status  The status of the step (e.g., 'done', 'failed').
     */
    private function updateWebsiteStep(Website $website, string $message, string $status): void
    {
        $website->updateProvisioningStep('generate_ssl', $message, $status);
    }
}
