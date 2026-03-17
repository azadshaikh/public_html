<?php

namespace Modules\Platform\Console;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Modules\Platform\Enums\WebsiteStatus;
use Modules\Platform\Models\Website;
use Modules\Platform\Services\WebsiteService;

/**
 * Orchestrates the end-to-end provisioning of a new website.
 *
 * This command acts as the master controller for the website creation process.
 * It calls a series of individual, single-responsibility commands in a specific
 * order, as defined in the platform configuration file. This approach makes the
 * provisioning process modular, easy to debug, and maintainable.
 *
 * It is designed to be called directly from the command line for manual provisioning
 * or from a queued job for asynchronous, UI-driven provisioning.
 */
class ProvisionWebsiteCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'platform:provision-website {website_id : The ID of the website to provision}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Orchestrates the step-by-step provisioning of a new website.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $websiteId = $this->argument('website_id');
        /** @var Website|null $website */
        $website = Website::query()->find($websiteId);

        if (! $website) {
            $this->error(sprintf('Website with ID %s not found.', $websiteId));

            return Command::FAILURE;
        }

        $this->info(sprintf('🚀 Starting provisioning for website: %s (ID: %d)', $website->domain, $website->id));

        // Get the sequence of steps from the config file
        $provisioningSteps = config('platform.website.steps');

        foreach ($provisioningSteps as $key => $step) {
            // Skip CDN step if skip_cdn flag is set
            if ($website->skip_cdn && in_array($key, ['setup_bunny_cdn', 'configure_cdn_ssl'])) {
                $this->line(sprintf('➤ [%s]: %s... (skipped - CDN setup disabled)', $key, $step['title']));
                $website->refresh();
                $website->updateProvisioningStep($key, 'Skipped - CDN configured manually', 'done');

                continue;
            }

            // Skip DNS step if skip_dns flag is set
            if ($website->skip_dns && $key === 'setup_bunny_dns') {
                $this->line(sprintf('➤ [%s]: %s... (skipped - DNS setup disabled)', $key, $step['title']));
                $website->refresh();
                $website->updateProvisioningStep($key, 'Skipped - DNS configured manually', 'done');

                continue;
            }

            // Skip verify_dns for subdomains (no external DNS propagation needed)
            if ($key === 'verify_dns' && (! $website->dns_mode || $website->dns_mode === 'subdomain')) {
                $this->line(sprintf('➤ [%s]: %s... (skipped - subdomain DNS)', $key, $step['title']));
                $website->refresh();
                $website->updateProvisioningStep($key, 'Skipped - agency subdomain DNS auto-managed', 'done');

                continue;
            }

            // Skip steps that don't have a command defined (e.g., manual steps)
            if (! isset($step['command'])) {
                continue;
            }

            // Determine if this command should run:
            // 1. Platform-level commands (platform:*) that are NOT provider-specific - always run
            // 2. Provider-specific commands (platform:{provider}:*) - only run for matching provider
            // 3. Bunny commands (platform:bunny:*) - always run (provider-agnostic)
            $commandParts = explode(':', (string) $step['command']);
            $isProviderCommand = isset($commandParts[1]) && str_starts_with((string) $step['command'], sprintf('platform:%s:', $website->provider));
            $isBunnyCommand = str_starts_with((string) $step['command'], 'platform:bunny:');
            $isPlatformCommand = $commandParts[0] === 'platform' && ! str_contains((string) $step['command'], ':hestia:') && ! str_contains((string) $step['command'], ':bunny:');

            // Skip provider-specific commands that don't match the website's provider
            if (! $isProviderCommand && ! $isBunnyCommand && ! $isPlatformCommand) {
                continue;
            }

            // Skip steps that are already completed
            $existingStep = $website->getProvisioningStep($key);
            if ($existingStep && $existingStep['status'] === 'done') {
                $this->line(sprintf('➤ [%s]: %s... (already done, skipping)', $key, $step['title']));

                continue;
            }

            $this->line(sprintf('➤ [%s]: %s...', $key, $step['title']));

            try {
                // Call the individual command for the step
                $exitCode = Artisan::call($step['command'], ['website_id' => $website->id]);

                // Exit code 2 = WAITING (e.g., DNS propagation pending)
                // Stop pipeline gracefully — poll job will resume when ready
                if ($exitCode === 2) {
                    $website->refresh();
                    $website->status = WebsiteStatus::WaitingForDns;
                    $website->save();
                    $this->info(sprintf('⏳ Provisioning paused at [%s]: waiting for DNS verification.', $key));

                    return Command::SUCCESS;
                }

                if ($exitCode !== Command::SUCCESS) {
                    throw new Exception(sprintf("Artisan command '%s' failed with exit code %s.", $step['command'], $exitCode));
                }

                $this->info('✔ Success: '.$step['title']);
            } catch (Exception $e) {
                $this->error(sprintf("❌ Error on step '%s': ", $step['title']).$e->getMessage());
                Log::error(sprintf("Provisioning failed for website #%d at step '%s'", $website->id, $key), [
                    'command' => $step['command'],
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                // Re-throw the exception to notify the calling job of the failure
                throw $e;
            }
        }

        // Refresh the website to get the latest metadata (updated by individual steps)
        $website->refresh();

        // Provisioning successful - update website status to active and set expiry date
        $website->status = WebsiteStatus::Active;

        // Set expiry date based on website type
        if ($website->type === 'trial') {
            $website->expired_on = now()->addDays(15);
        } elseif ($website->type === 'paid') {
            $website->expired_on = now()->addYear();
        }

        $website->save();

        // Sync website information from the server to update database with version info
        $this->line('➤ Syncing website information from server...');
        try {
            $websiteService = resolve(WebsiteService::class);
            $syncResult = $websiteService->syncWebsiteInfo($website);

            if ($syncResult['success'] ?? false) {
                $this->info('✔ Website information synced successfully');
            } else {
                $this->warn('⚠ Website sync completed with warnings: '.($syncResult['message'] ?? 'Unknown'));
            }
        } catch (Exception $exception) {
            // Log but don't fail provisioning if sync fails - it can be done manually later
            $this->warn('⚠ Website sync failed (non-fatal): '.$exception->getMessage());
            Log::warning(sprintf('Website sync failed for website #%d after provisioning', $website->id), [
                'error' => $exception->getMessage(),
            ]);
        }

        $this->info(sprintf('✅ Provisioning completed successfully for %s!', $website->domain));
        $this->info('   Status: active | Expires: '.$website->expired_on);

        return Command::SUCCESS;
    }
}
