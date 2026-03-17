<?php

namespace Modules\Platform\Console;

use App\Enums\ActivityAction;
use App\Traits\ActivityTrait;
use Exception;
use Illuminate\Support\Facades\View;
use Modules\Platform\Libs\HestiaClient;
use Modules\Platform\Models\Website;

/**
 * Configures the application's .env file on the Hestia server for a given website.
 *
 * This command generates the .env content based on a template and the website's specific
 * data, then uses a custom Hestia script to write the file to the server.
 */
class HestiaConfigureEnvCommand extends BaseCommand
{
    use ActivityTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'platform:hestia:configure-env {website_id : The ID of the website}';

    protected $description = 'Configure the .env file for a website.';

    /**
     * The step key for this command.
     */
    protected ?string $stepKey = 'configure_env';

    /**
     * The core logic of the command.
     *
     * @param  Website  $website  The website instance.
     *
     * @throws Exception If the environment configuration fails.
     */
    protected function handleCommand(Website $website): void
    {
        $this->info(sprintf("Configuring .env file for '%s'.", $website->domain));

        $envContent = $this->generateEnvContent($website);

        // Base64 encode the env content for safe transmission
        $encodedContent = base64_encode($envContent);

        // This command relies on a custom script 'a-create-environment-file' on the Hestia server.
        $response = HestiaClient::execute(
            'a-create-environment-file',
            $website->server,
            [
                'arg1' => $website->website_username,
                'arg2' => $website->domain,
                'arg3' => $encodedContent,
            ]
        );

        if (! $response['success']) {
            $errorMessage = $response['message'] ?? 'Unknown error during .env configuration.';
            $this->updateWebsiteStep($website, $errorMessage, 'failed');
            throw new Exception($errorMessage);
        }

        $successMessage = sprintf(".env file configured successfully for '%s'.", $website->domain);
        $this->logActivity($website, ActivityAction::UPDATE, $successMessage);
        $this->updateWebsiteStep($website, $successMessage, 'done');
    }

    /**
     * Generates the content of the .env file from a Blade template.
     *
     * @param  Website  $website  The website instance.
     * @return string The rendered .env file content.
     */
    private function generateEnvContent(Website $website): string
    {
        // Refresh to ensure we have the latest metadata (e.g., db_name set by create-database step)
        $website->refresh();

        $this->info('Database name from metadata: '.($website->db_name ?? 'NULL'));
        $this->info('Website site_id: '.($website->site_id ?? 'NULL'));
        $this->info('Website plan: '.($website->plan ?? 'NULL'));
        $this->info('Website admin_slug: '.($website->admin_slug ?? 'NULL'));
        $this->info('Agency exists: '.($website->agency ? 'YES' : 'NO'));
        if ($website->agency) {
            $this->info('Agency UID: '.($website->agency->uid ?? 'NULL'));
            $this->info('Agency plan: '.($website->agency->plan ?? 'NULL'));
            $this->info('Agency isWhitelabel: '.($website->agency->isWhitelabel() ? 'YES' : 'NO'));
            $this->info('Agency branding_name: '.($website->agency->branding_name ?? 'NULL'));
            $this->info('Agency branding_website: '.($website->agency->branding_website ?? 'NULL'));
            $this->info('Agency branding_logo: '.($website->agency->branding_logo ?? 'NULL'));
            $this->info('Agency branding_icon: '.($website->agency->branding_icon ?? 'NULL'));
        }

        // Get database credentials from secrets
        $dbSecret = $website->getSecret('database_password');
        if (! $dbSecret) {
            throw new Exception(sprintf('Database password secret not found for website #%d.', $website->id));
        }

        // Get agency branding if agency has whitelabel enabled
        $brandingName = '';
        $brandingWebsite = '';
        $brandingLogo = '';
        $brandingIcon = '';

        if ($website->agency && $website->agency->isWhitelabel()) {
            $brandingName = $website->agency->branding_name ?? '';
            $brandingWebsite = $website->agency->branding_website ?? '';
            $brandingLogo = $website->agency->branding_logo ?? '';
            $brandingIcon = $website->agency->branding_icon ?? '';
        }

        // Determine database connection type from metadata (set by HestiaCreateDatabaseCommand)
        $dbType = $website->getMetadata('db_type', 'pgsql');
        $isPostgres = $dbType === 'pgsql';

        $viewData = [
            'app_name' => $website->name,
            'app_url' => 'https://'.$website->domain,
            'domain' => $website->domain,
            'agency_uid' => $website->agency ? $website->agency->uid : null,
            'website_id' => $website->site_id,
            'website_plan' => $website->plan ?? '',
            'agency_plan' => $website->agency ? $website->agency->plan : '',
            'secret_key' => $website->plain_secret_key, // Decrypted token for client .env
            'agency_secret_key' => $website->is_agency && $website->agency ? $website->agency->plain_secret_key : null, // Only for agency websites
            'site_id' => $website->site_id,
            'admin_slug' => $website->admin_slug,
            'media_slug' => $website->media_slug,
            'theme_uid' => 1000, // Placeholder or default theme UID
            'db_connection' => $isPostgres ? 'pgsql' : 'mysql',
            'db_port' => $isPostgres ? '5432' : '3306',
            'db_charset' => $isPostgres ? 'utf8' : 'utf8mb4',
            'db_collation' => $isPostgres ? '' : 'utf8mb4_unicode_ci',
            'database_name' => $website->db_name,
            'database_username' => $dbSecret['username'] ?? $website->site_id.'_db_user',
            'database_password' => $dbSecret['value'],
            'website_provider' => $website->provider,
            // Branding (from agency metadata if whitelabel enabled)
            'branding_name' => $brandingName,
            'branding_website' => $brandingWebsite,
            'branding_logo' => $brandingLogo,
            'branding_icon' => $brandingIcon,
        ];

        // Assumes a Blade template exists at 'platform::websites.partials.env-template'
        return View::make('platform::websites.partials.env-template', $viewData)->render();
    }

    /**
     * Updates the website's provisioning step for 'configure_env'.
     *
     * @param  Website  $website  The website instance.
     * @param  string  $message  The message to log for the step.
     * @param  string  $status  The status of the step (e.g., 'done', 'failed').
     */
    private function updateWebsiteStep(Website $website, string $message, string $status): void
    {
        $website->updateProvisioningStep('configure_env', $message, $status);
    }
}
