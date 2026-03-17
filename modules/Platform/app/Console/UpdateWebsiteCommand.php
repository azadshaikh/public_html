<?php

namespace Modules\Platform\Console;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Modules\Platform\Libs\BunnyApi;
use Modules\Platform\Models\Website;
use Modules\Platform\Services\WebsiteService;

/**
 * Orchestrates the end-to-end update of a website.
 *
 * This command acts as the master controller for the website update process.
 * It calls a series of individual, single-responsibility commands in a specific
 * order. This approach makes the update process modular, easy to debug, and maintainable.
 *
 * It is designed to be called directly from the command line for manual updates
 * or from a queued job for asynchronous, UI-driven updates.
 */
class UpdateWebsiteCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'platform:update-website {website_id : The ID of the website to update}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Orchestrates the step-by-step update of a website to the latest version.';

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

        // Check if update is available
        if (! $website->hasUpdateAvailable()) {
            $this->warn('No update available for website: '.$website->domain);
            $this->info('Website version: v'.$website->astero_version);
            $this->info('Server version: v'.$website->server_version);

            return Command::SUCCESS;
        }

        $oldVersion = $website->astero_version;
        $newVersion = $website->server_version;

        $this->info(sprintf('🚀 Starting update for website: %s (ID: %d)', $website->domain, $website->id));
        $this->info(sprintf('   Updating from v%s to v%s', $oldVersion, $newVersion));

        // Define the update steps
        $updateSteps = [
            'update_astero' => [
                'title' => 'Update Astero',
                'command' => 'platform:hestia:update-astero',
            ],
        ];

        foreach ($updateSteps as $key => $step) {
            $this->line(sprintf('➤ [%s]: %s...', $key, $step['title']));

            try {
                // Call the individual command for the step
                $exitCode = Artisan::call($step['command'], ['website_id' => $website->id]);

                if ($exitCode !== Command::SUCCESS) {
                    throw new Exception(sprintf("Artisan command '%s' failed with exit code %s.", $step['command'], $exitCode));
                }

                $this->info('✔ Success: '.$step['title']);
            } catch (Exception $e) {
                $this->error(sprintf("❌ Error on step '%s': ", $step['title']).$e->getMessage());
                Log::error(sprintf("Update failed for website #%d at step '%s'", $website->id, $key), [
                    'command' => $step['command'],
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                // Record update failure
                $this->recordUpdateHistory($website, $oldVersion, $newVersion, 'failed', $e->getMessage());

                // Re-throw the exception to notify the calling job of the failure
                throw $e;
            }
        }

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
            // Log but don't fail update if sync fails - it can be done manually later
            $this->warn('⚠ Website sync failed (non-fatal): '.$exception->getMessage());
            Log::warning(sprintf('Website sync failed for website #%d after update', $website->id), [
                'error' => $exception->getMessage(),
            ]);
        }

        // Record successful update
        $this->recordUpdateHistory($website, $oldVersion, $newVersion, 'done', 'Update completed successfully');

        // Purge CDN cache after successful update
        $this->purgeCdnCache($website);

        $this->info(sprintf('✅ Update completed successfully for %s!', $website->domain));
        $this->info(sprintf('   Updated from v%s to v%s', $oldVersion, $newVersion));

        return Command::SUCCESS;
    }

    /**
     * Record the update in the website's metadata history.
     * Uses addUpdateHistoryEntry() to keep a full history of all updates.
     */
    private function recordUpdateHistory(Website $website, string $oldVersion, string $newVersion, string $status, string $message): void
    {
        $updateData = [
            'old_version' => $oldVersion,
            'new_version' => $newVersion,
            'message' => $message,
            'started_at' => now()->toISOString(),
        ];

        $website->addUpdateHistoryEntry('update_platform', $updateData, $status);
    }

    /**
     * Purge CDN cache after a successful update (non-fatal on failure).
     */
    private function purgeCdnCache(Website $website): void
    {
        $pullzoneId = $website->pullzone_id;
        if (! $pullzoneId) {
            return;
        }

        $cdnProvider = $website->cdnProvider ?? $website->dnsProvider;
        if (! $cdnProvider || $cdnProvider->vendor !== 'bunny') {
            return;
        }

        $this->line('➤ Purging CDN cache...');

        try {
            $result = BunnyApi::purgePullZoneCache($cdnProvider, $pullzoneId);

            if (($result['status'] ?? '') === 'success') {
                $this->info('✔ CDN cache purged');
            } else {
                $this->warn('⚠ CDN purge returned non-success: '.($result['message'] ?? 'Unknown'));
            }
        } catch (Exception $e) {
            $this->warn('⚠ CDN purge failed (non-fatal): '.$e->getMessage());
            Log::warning(sprintf('CDN cache purge failed for website #%d after update', $website->id), [
                'pullzone_id' => $pullzoneId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
