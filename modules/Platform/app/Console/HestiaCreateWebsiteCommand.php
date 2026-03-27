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
        $createdNow = $this->ensureWebDomainExists($website, $backendTemplate);
        $redirectTarget = $this->syncWebDomainRedirect($website);

        $successMessage = $redirectTarget === null
            ? ($createdNow
                ? sprintf("Web domain '%s' created and configured.", $website->domain)
                : sprintf("Web domain '%s' already existed and has been reconciled.", $website->domain))
            : ($createdNow
                ? sprintf("Web domain '%s' created and configured with redirect target '%s'.", $website->domain, $redirectTarget)
                : sprintf("Web domain '%s' already existed and redirect target '%s' has been reconciled.", $website->domain, $redirectTarget));

        $this->logActivity($website, ActivityAction::CREATE, $successMessage);
        $this->updateWebsiteStep($website, $successMessage, 'done');
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

    private function ensureWebDomainExists(Website $website, string $backendTemplate): bool
    {
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

        if ($response['success']) {
            return true;
        }

        if (! $this->isAlreadyExistsResponse($response)) {
            $errorMessage = 'Failed to create web domain: '.$response['message'];
            $this->updateWebsiteStep($website, $errorMessage, 'failed');
            throw new Exception($errorMessage);
        }

        $this->info(sprintf("Web domain '%s' already exists. Re-applying templates.", $website->domain));

        $templateResponse = HestiaClient::execute(
            'v-change-web-domain-tpl',
            $website->server,
            [
                $website->website_username,
                $website->domain,
                'astero-active',
            ]
        );

        if (! $templateResponse['success']) {
            $errorMessage = 'Failed to re-apply web template: '.$templateResponse['message'];
            $this->updateWebsiteStep($website, $errorMessage, 'failed');
            throw new Exception($errorMessage);
        }

        $backendResponse = HestiaClient::execute(
            'v-change-web-domain-backend-tpl',
            $website->server,
            [
                $website->website_username,
                $website->domain,
                $backendTemplate,
            ]
        );

        if (! $backendResponse['success']) {
            $errorMessage = 'Failed to re-apply backend template: '.$backendResponse['message'];
            $this->updateWebsiteStep($website, $errorMessage, 'failed');
            throw new Exception($errorMessage);
        }

        return false;
    }

    private function syncWebDomainRedirect(Website $website): ?string
    {
        $redirectTarget = $website->hestiaRedirectTarget();

        $deleteResponse = HestiaClient::execute(
            'v-delete-web-domain-redirect',
            $website->server,
            [
                $website->website_username,
                $website->domain,
            ]
        );

        if (! $deleteResponse['success'] && ! $this->isNotFoundResponse($deleteResponse)) {
            $errorMessage = 'Failed to clear existing web domain redirect: '.$deleteResponse['message'];
            $this->updateWebsiteStep($website, $errorMessage, 'failed');
            throw new Exception($errorMessage);
        }

        if ($redirectTarget === null) {
            return null;
        }

        $redirectResponse = HestiaClient::execute(
            'v-add-web-domain-redirect',
            $website->server,
            [
                $website->website_username,
                $website->domain,
                $redirectTarget,
                '301',
                'yes',
            ]
        );

        if (! $redirectResponse['success']) {
            $errorMessage = 'Failed to configure web domain redirect: '.$redirectResponse['message'];
            $this->updateWebsiteStep($website, $errorMessage, 'failed');
            throw new Exception($errorMessage);
        }

        return $redirectTarget;
    }

    private function isAlreadyExistsResponse(array $response): bool
    {
        $message = strtolower((string) ($response['message'] ?? ''));

        return (int) ($response['code'] ?? 0) === 4
            || str_contains($message, 'already exists');
    }

    private function isNotFoundResponse(array $response): bool
    {
        $message = strtolower((string) ($response['message'] ?? ''));

        return (int) ($response['code'] ?? 0) === 3
            || str_contains($message, "doesn't exist")
            || str_contains($message, 'not found');
    }
}
