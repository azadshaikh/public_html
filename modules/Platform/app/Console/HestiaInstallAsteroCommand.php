<?php

namespace Modules\Platform\Console;

use App\Enums\ActivityAction;
use App\Traits\ActivityTrait;
use Exception;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Modules\Platform\Libs\HestiaClient;
use Modules\Platform\Models\Website;

/**
 * Installs Astero on the Hestia server for a given website.
 *
 * This command runs after prepare_astero and configure_env. It executes
 * 'a-install-astero' on the server to run the Laravel installation command
 * and set up the scheduler cron job.
 */
class HestiaInstallAsteroCommand extends BaseCommand
{
    use ActivityTrait;

    private const int DATABASE_REPAIR_RETRY_DELAY_SECONDS = 2;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'platform:hestia:install-astero {website_id : The ID of the website}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run Astero installation and setup scheduler.';

    /**
     * The step key for this command.
     */
    protected ?string $stepKey = 'install_astero';

    /**
     * The core logic of the command.
     *
     * @param  Website  $website  The website instance.
     *
     * @throws Exception If the installation fails.
     */
    protected function handleCommand(Website $website): void
    {
        $this->info(sprintf("Installing Astero for '%s'.", $website->domain));

        $installData = $this->prepareInstallData($website);
        $response = $this->runInstallCommand($website, $installData);

        if (! $response['success'] && $this->isDatabaseAuthenticationFailure((string) $response['message'])) {
            $this->warn(sprintf("Detected database authentication failure while installing '%s'. Attempting credential repair and one retry.", $website->domain));

            if ($this->repairDatabaseCredentials($website)) {
                $this->pauseBeforeInstallRetry();
                $response = $this->runInstallCommand($website, $installData);
            }
        }

        if (! $response['success']) {
            $errorMessage = $response['message'];
            $this->updateWebsiteStep($website, $errorMessage, 'failed');
            throw new Exception($errorMessage);
        }

        $successMessage = sprintf("Astero installed successfully for '%s'.", $website->domain);
        $this->logActivity($website, ActivityAction::UPDATE, $successMessage);
        $this->updateWebsiteStep($website, $successMessage, 'done');
    }

    /**
     * Run the remote installation script via Hestia API.
     *
     * @param  array<string, string>  $installData
     * @return array{success:bool,message:string,data:array,code:int}
     */
    protected function runInstallCommand(Website $website, array $installData): array
    {
        return $this->executeHestiaCommand(
            'a-install-astero',
            $website,
            [
                'arg1' => $website->website_username,
                'arg2' => $website->domain,
                'arg3' => json_encode($installData),
            ]
        );
    }

    /**
     * Repair DB credentials on the server when install fails due auth mismatch.
     */
    protected function repairDatabaseCredentials(Website $website): bool
    {
        $website->refresh();
        $dbSecret = $website->getSecret('database_password');
        $dbName = (string) ($website->db_name ?? '');

        if (! $dbSecret || $dbName === '' || empty($dbSecret['value'])) {
            $this->warn('Database credential repair skipped: missing database name or secret.');

            return false;
        }

        $this->info(sprintf("Repairing database password for '%s' (database: %s).", $website->domain, $dbName));

        $response = $this->executeHestiaCommand(
            'v-change-database-password',
            $website,
            [
                'arg1' => $website->website_username,
                'arg2' => $dbName,
                'arg3' => (string) $dbSecret['value'],
            ]
        );

        if (! $response['success']) {
            $this->warn('Database credential repair failed: '.$response['message']);

            return false;
        }

        return true;
    }

    /**
     * Match known DB authentication signatures from installer output.
     */
    protected function isDatabaseAuthenticationFailure(string $message): bool
    {
        $normalizedMessage = strtolower($message);

        return str_contains($normalizedMessage, 'password authentication failed')
            || str_contains($normalizedMessage, 'access denied for user')
            || str_contains($normalizedMessage, 'sqlstate[28p01]');
    }

    protected function pauseBeforeInstallRetry(): void
    {
        Sleep::sleep(self::DATABASE_REPAIR_RETRY_DELAY_SECONDS);
    }

    /**
     * Wrapper for Hestia API calls to make command logic testable.
     *
     * @param  array<string, string>  $args
     * @return array{success:bool,message:string,data:array,code:int}
     */
    protected function executeHestiaCommand(string $command, Website $website, array $args): array
    {
        return HestiaClient::execute($command, $website->server, $args);
    }

    /**
     * Prepares the data payload required by the installation script.
     *
     * @param  Website  $website  The website instance.
     * @return array The installation data.
     */
    protected function prepareInstallData(Website $website): array
    {
        // Customer data is stored as JSON on the website (set by Agency API)
        $customerEmail = $website->customer_data['email'] ?? null;
        $customerName = $website->customer_data['name'] ?? 'Admin User';
        $nameParts = explode(' ', $customerName, 2);

        $websiteAdmin = $this->getOrCreateAccount($website, 'website_admin', $customerEmail ?? 'admin@astero.in');
        $superUser = $this->getOrCreateAccount($website, 'super_user', 'su@astero.in');

        return [
            'owner_first_name' => $nameParts[0],
            'owner_last_name' => ! empty($nameParts[1]) ? $nameParts[1] : 'User',
            'owner_email' => $websiteAdmin['username'],
            'owner_password' => $websiteAdmin['password'],
            'super_user_email' => $superUser['username'],
            'super_user_password' => $superUser['password'],
            'theme_id' => '1',
        ];
    }

    /**
     * Retrieves or creates an account in the password manager for the website.
     *
     * @param  Website  $website  The website instance.
     * @param  string  $groupSlug  The slug of the account group.
     * @param  string  $defaultEmail  The default email to use if the account doesn't exist.
     * @return array The account credentials.
     */
    protected function getOrCreateAccount(Website $website, string $groupSlug, string $defaultEmail): array
    {
        // Try to get existing secret
        $secretKey = $groupSlug.'_password';
        $secretData = $website->getSecret($secretKey);

        if ($secretData) {
            return [
                'username' => $secretData['username'] ?? $defaultEmail,
                'password' => $secretData['value'],
            ];
        }

        // Create new secret with username
        $newPassword = Str::random(12);
        $website->setSecret($secretKey, $newPassword, 'password', $defaultEmail);

        return [
            'username' => $defaultEmail,
            'password' => $newPassword,
        ];
    }

    /**
     * Updates the website's provisioning step for 'install_astero'.
     *
     * @param  Website  $website  The website instance.
     * @param  string  $message  The message to log for the step.
     * @param  string  $status  The status of the step (e.g., 'done', 'failed').
     */
    private function updateWebsiteStep(Website $website, string $message, string $status): void
    {
        $website->updateProvisioningStep('install_astero', $message, $status);
    }
}
