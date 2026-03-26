<?php

namespace Modules\Platform\Console;

use App\Enums\ActivityAction;
use App\Traits\ActivityTrait;
use Exception;
use Illuminate\Console\Command;
use Modules\Platform\Libs\HestiaClient;
use Modules\Platform\Models\Website;

/**
 * Reverts website setup steps, acting as a rollback mechanism for provisioning.
 *
 * This command can undo specific steps of the website creation process, such as
 * user creation, domain setup, database creation, etc. It can be run for a single
 * step or for all steps to completely remove a user and their assets from the server.
 */
class HestiaRevertInstallationStepCommand extends Command
{
    use ActivityTrait;

    /**
     * The defined reversion steps in the correct order.
     */
    private const array REVERSION_STEPS = [
        'resolve_domain' => 'revertResolveDomain',
        'create_user' => 'revertUser',
        'create_website' => 'revertWebsite',
        'publish_domain_verification' => 'revertWebsite',
        'create_database' => 'revertDatabase',
        'install_ssl' => 'revertInstallSsl',
        'prepare_astero' => 'revertAsteroPreparation',
        'configure_env' => 'revertAddEnv',
        'install_astero' => 'revertAsteroInstallation',
        'setup_bunny_cdn' => 'revertBunnyCdn',
    ];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'platform:hestia:revert-installation-step {website_id : The ID of the website} {--step=all} {--force}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Revert a specific website installation step.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            /** @var Website $website */
            $website = Website::query()->findOrFail($this->argument('website_id'));

            if (! $this->option('force') && ! $this->confirm(sprintf('Are you sure you want to revert setup for %s? This is irreversible.', $website->domain))) {
                $this->info('Operation cancelled.');

                return self::SUCCESS;
            }

            $this->processReversion($website, $this->option('step'));

            return self::SUCCESS;
        } catch (Exception $exception) {
            $this->error('An error occurred: '.$exception->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Process the reversion workflow.
     */
    private function processReversion(Website $website, string $step): void
    {
        $this->info('Starting reversion for website: '.$website->domain);

        if ($step === 'all') {
            if ($website->pullzone_id) {
                $this->info('Reverting Bunny CDN before deleting the server user...');
                $this->executeStep($website, 'setup_bunny_cdn', 'revertBunnyCdn');
            }

            $this->info('Reverting all steps by deleting the user...');
            $this->executeStep($website, 'create_user', 'revertUser');

            return;
        }

        if (! isset(self::REVERSION_STEPS[$step])) {
            $this->error('Invalid step. Available steps: '.implode(', ', array_keys(self::REVERSION_STEPS)));

            return;
        }

        $this->executeStep($website, $step, self::REVERSION_STEPS[$step]);
    }

    /**
     * Execute a single reversion step and handle the result.
     */
    private function executeStep(Website $website, string $stepName, string $method): void
    {
        $this->line('➤ Processing step: '.$stepName);
        try {
            $message = $this->$method($website);
            $this->info('✔ Success: '.$message);
            $this->logReversionAttempt($website, $stepName, 'success', $message);
        } catch (Exception $exception) {
            $this->error(sprintf('✗ Error in %s: ', $stepName).$exception->getMessage());
            $this->logReversionAttempt($website, $stepName, 'error', $exception->getMessage());
            // Re-throw to be caught by the main handler
            throw $exception;
        }
    }

    /**
     * Reverts user creation. This is a destructive action that removes the user and all their assets.
     */
    private function revertUser(Website $website): string
    {
        $this->callHestiaApi('v-delete-user', $website, ['arg1' => $website->website_username]);
        $website->revertAllProvisioningSteps();

        return 'User and all associated data removed successfully.';
    }

    /**
     * Reverts domain record association.
     * Note: This only removes the association, NOT the domain record itself.
     * The domain record may be used by other websites or for other purposes.
     */
    private function revertResolveDomain(Website $website): string
    {
        $domainName = $website->domainRecord->domain_name ?? 'unknown';

        // Remove the domain association (but keep the domain record)
        $website->domain_id = null;
        $website->save();

        $website->markProvisioningStepReverted('resolve_domain');

        return sprintf("Domain association removed (domain record '%s' preserved).", $domainName);
    }

    /**
     * Reverts website domain creation.
     */
    private function revertWebsite(Website $website): string
    {
        $this->callHestiaApi('v-delete-web-domain', $website, ['arg1' => $website->website_username, 'arg2' => $website->domain]);
        $website->markProvisioningStepsReverted(['create_website', 'publish_domain_verification', 'create_database', 'install_ssl', 'prepare_astero', 'configure_env', 'install_astero']);

        return 'Website domain removed successfully.';
    }

    /**
     * Reverts database creation.
     */
    private function revertDatabase(Website $website): string
    {
        if (empty($website->db_name)) {
            return 'Database not found or already reverted, skipping.';
        }

        $this->callHestiaApi('v-delete-database', $website, ['arg1' => $website->website_username, 'arg2' => $website->db_name]);
        $this->removeAccountCredentials($website, 'database');

        // Use direct attribute assignment to trigger the mutator for metadata field
        $website->db_name = null;
        $website->save();

        $this->revertPlatformInstallation($website);
        $this->revertAddEnv($website);

        $website->markProvisioningStepsReverted(['create_database', 'prepare_astero', 'configure_env', 'install_astero']);

        return 'Database removed successfully.';
    }

    /**
     * Reverts SSL certificate installation.
     *
     * This removes the SSL configuration from Hestia and clears the certificate
     * association from the website (but preserves the certificate itself in secrets).
     */
    private function revertInstallSsl(Website $website): string
    {
        $this->callHestiaApi('v-delete-web-domain-ssl', $website, ['arg1' => $website->website_username, 'arg2' => $website->domain]);

        // Clear the SSL certificate association (but keep the certificate in secrets)
        $website->ssl_secret_id = null;
        $website->save();

        $website->markProvisioningStepsReverted(['install_ssl']);

        return 'SSL certificate installation removed (certificate preserved in domain secrets).';
    }

    /**
     * Reverts force SSL.
     */
    private function revertForceSsl(Website $website): string
    {
        $this->callHestiaApi('v-delete-web-domain-ssl-force', $website, ['arg1' => $website->website_username, 'arg2' => $website->domain]);
        $website->markProvisioningStepReverted('force_ssl');

        return 'Force SSL reverted successfully.';
    }

    /**
     * Reverts Astero preparation (release extraction).
     */
    private function revertAsteroPreparation(Website $website): string
    {
        $this->callHestiaApi('a-revert-installation-step', $website, ['arg1' => $website->website_username, 'arg2' => $website->domain, 'arg3' => 'platform-dl']);
        $this->revertAddEnv($website);
        $this->revertAsteroInstallation($website);
        $website->markProvisioningStepsReverted(['prepare_astero', 'configure_env', 'install_astero']);

        return 'Astero preparation reverted successfully.';
    }

    /**
     * Reverts .env file creation.
     */
    private function revertAddEnv(Website $website): string
    {
        // Revert the .env file only
        $this->callHestiaApi('a-revert-installation-step', $website, ['arg1' => $website->website_username, 'arg2' => $website->domain, 'arg3' => 'env-add']);

        // Mark step as reverted
        $website->markProvisioningStepReverted('configure_env');

        return 'Environment file reverted successfully.';
    }

    /**
     * Reverts Astero installation.
     */
    private function revertAsteroInstallation(Website $website): string
    {
        $this->callHestiaApi('a-revert-installation-step', $website, ['arg1' => $website->website_username, 'arg2' => $website->domain, 'arg3' => 'app-install']);
        $website->markProvisioningStepReverted('install_astero');

        return 'Astero installation reverted successfully.';
    }

    /**
     * Reverts platform installation (helper for database revert).
     */
    private function revertPlatformInstallation(Website $website): void
    {
        $this->callHestiaApi('a-revert-installation-step', $website, ['arg1' => $website->website_username, 'arg2' => $website->domain, 'arg3' => 'platform-dl']);
    }

    /**
     * Reverts Bunny CDN setup.
     */
    private function revertBunnyCdn(Website $website): string
    {
        $bunnyCdnCommand = new BunnySetupCdnCommand;

        return $bunnyCdnCommand->revert($website);
    }

    /**
     * Helper to call Hestia API and handle errors.
     */
    private function callHestiaApi(string $command, Website $website, array $args): void
    {
        $response = HestiaClient::execute($command, $website->server, $args);
        // Allow "does not exist" errors to pass, as the state is already what we want.
        if (! $response['success'] && ! str_contains($response['message'] ?? '', 'does not exist')) {
            throw new Exception($response['message'] ?? sprintf("Hestia API command '%s' failed.", $command));
        }
    }

    /**
     * Remove account credentials from password manager.
     */
    private function removeAccountCredentials(Website $website, string $groupName): void
    {
        // Delete the secret corresponding to this account group
        $website->deleteSecret($groupName.'_password');
    }

    /**
     * Log the reversion attempt.
     */
    private function logReversionAttempt(Website $website, string $step, string $status, string $message): void
    {
        $this->logActivity(
            model: $website,
            action: ActivityAction::UPDATE,
            message: sprintf("Website reversion for step '%s': %s", $step, $message),
            extraProperties: ['status' => $status, 'website_id' => $website->id, 'step' => $step]
        );
    }
}
