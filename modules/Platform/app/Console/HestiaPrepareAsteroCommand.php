<?php

namespace Modules\Platform\Console;

use App\Enums\ActivityAction;
use App\Traits\ActivityTrait;
use Exception;
use Modules\Platform\Libs\HestiaClient;
use Modules\Platform\Models\Website;

/**
 * Prepares Astero for installation by extracting from the local releases repository.
 *
 * This command uses a custom server script 'a-prepare-astero' to extract the
 * release from the local repository and set up shared directories (storage, themes).
 */
class HestiaPrepareAsteroCommand extends BaseCommand
{
    use ActivityTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'platform:hestia:prepare-astero {website_id : The ID of the website}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prepare Astero release for a website (extract and setup shared directories).';

    /**
     * The step key for this command.
     */
    protected ?string $stepKey = 'prepare_astero';

    /**
     * The core logic of the command.
     *
     * @param  Website  $website  The website instance.
     *
     * @throws Exception If the preparation fails.
     */
    protected function handleCommand(Website $website): void
    {
        $this->info(sprintf("Preparing Astero for '%s'.", $website->domain));

        // This command relies on a custom script 'a-prepare-astero' on the Hestia server.
        $response = HestiaClient::execute(
            'a-prepare-astero',
            $website->server,
            [
                'arg1' => $website->website_username,
                'arg2' => $website->domain,
                'arg3' => 'main', // Package identifier
            ]
        );

        if (! $response['success']) {
            $errorMessage = $response['message'] ?? 'Unknown error during Astero preparation.';
            $this->updateWebsiteStep($website, $errorMessage, 'failed');
            throw new Exception($errorMessage);
        }

        $successMessage = sprintf("Astero prepared successfully for '%s'.", $website->domain);
        $this->logActivity($website, ActivityAction::UPDATE, $successMessage);
        $this->updateWebsiteStep($website, $successMessage, 'done');
    }

    /**
     * Updates the website's provisioning step for 'prepare_astero'.
     *
     * @param  Website  $website  The website instance.
     * @param  string  $message  The message to log for the step.
     * @param  string  $status  The status of the step (e.g., 'done', 'failed').
     */
    private function updateWebsiteStep(Website $website, string $message, string $status): void
    {
        $website->updateProvisioningStep('prepare_astero', $message, $status);
    }
}
