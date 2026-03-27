<?php

namespace Modules\Platform\Console;

use App\Enums\ActivityAction;
use App\Traits\ActivityTrait;
use Exception;
use Modules\Platform\Libs\BunnyApi;
use Modules\Platform\Libs\BunnyApiException;
use Modules\Platform\Models\Provider;
use Modules\Platform\Models\Website;
use Modules\Platform\Services\BunnyPullZoneService;

/**
 * Sets up Bunny CDN (pull zone) for a website.
 *
 * This command creates a pull zone (CDN) for the website using the associated Bunny account.
 * It configures the CDN to pull content from the website's server and adds the domain as a hostname.
 */
class BunnySetupCdnCommand extends BaseCommand
{
    use ActivityTrait;

    public function __construct(private readonly BunnyPullZoneService $pullZoneService)
    {
        parent::__construct();
    }

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'platform:bunny:setup-cdn {website_id : The ID of the website}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Setup Bunny CDN (pull zone) for a website.';

    /**
     * The step key for this command.
     */
    protected ?string $stepKey = 'setup_bunny_cdn';

    /**
     * Revert the Bunny CDN setup by deleting the pull zone.
     *
     * @param  Website  $website  The website instance.
     *
     * @throws Exception If bunny account is not set or pullzone deletion fails.
     */
    public function revert(Website $website): string
    {
        // Get the CDN provider (or DNS provider as fallback)
        $cdnProvider = $website->cdnProvider ?? $website->dnsProvider;

        throw_unless($cdnProvider, Exception::class, 'CDN/DNS Provider is not associated with this website.');

        // If not bunny, just skip/return? Or revert logic specific to driver?
        // This command is Bunny specific, so we assume bunny vendor.
        if ($cdnProvider->vendor !== 'bunny') {
            return 'Provider is not Bunny, skipping Bunny CDN revert.';
        }

        // Get pullzone ID from metadata
        $pullzoneId = $website->pullzone_id;
        if (! $pullzoneId) {
            // If no pullzone ID exists, consider it already reverted
            $website->markProvisioningStepReverted('setup_bunny_cdn', 'Pull zone not found or already deleted.');

            return 'Pull zone not found or already deleted, skipping.';
        }

        // Delete the pull zone
        try {
            BunnyApi::deletePullZone($cdnProvider, $pullzoneId);
        } catch (BunnyApiException $bunnyApiException) {
            // Allow "not found" errors to pass, as the state is already what we want
            $errorMessage = $bunnyApiException->getMessage();
            if (stripos($errorMessage, 'not found') === false && stripos($errorMessage, 'does not exist') === false) {
                throw new Exception('Failed to delete pull zone: '.$errorMessage, $bunnyApiException->getCode(), $bunnyApiException);
            }

            // Pull zone already deleted or not found - this is fine, continue
        }

        // Remove CDN metadata from website
        $website->setMetadata('cdn', null);
        $website->save();

        // Mark the provisioning step as reverted
        $website->markProvisioningStepReverted('setup_bunny_cdn', 'Bunny CDN (pull zone) deleted successfully.');

        // Log activity
        $successMessage = sprintf("Bunny CDN (pull zone) deleted successfully for '%s'. Pull Zone ID: %s", $website->domain, $pullzoneId);
        $this->logActivity($website, ActivityAction::UPDATE, $successMessage);

        return 'Bunny CDN (pull zone) deleted successfully.';
    }

    /**
     * The core logic of the command.
     *
     * @param  Website  $website  The website instance.
     *
     * @throws Exception If bunny account is not set or CDN setup fails.
     */
    protected function handleCommand(Website $website): void
    {
        // Get the Bunny CDN provider for this website
        $cdnProvider = $website->cdnProvider;

        // If no CDN provider, try DNS provider as fallback (Bunny can be used for both)
        if (! $cdnProvider) {
            $cdnProvider = $website->dnsProvider;
        }

        throw_unless($cdnProvider, Exception::class, 'CDN/DNS Provider (Bunny account) is not associated with this website.');

        if ($cdnProvider->vendor !== 'bunny') {
            // If not bunny, we shouldn't be running this command
            throw new Exception(sprintf('Provider is not using Bunny vendor (Vendor: %s).', $cdnProvider->vendor));
        }

        // Construct origin URL from server
        $originUrl = $this->pullZoneService->resolveOriginUrl($website);

        // Collect hostnames to add
        $hostnames = [$website->domain];
        if ($website->is_www) {
            $hostnames[] = 'www.'.$website->domain;
        }

        // Create pull zone with OriginHostHeader set to the primary domain
        $this->info(sprintf("Creating pull zone for '%s' with origin '%s'.", $website->domain, $originUrl));
        $pullZone = $this->createPullZone($website, $cdnProvider, $originUrl, $website->domain);

        $pullZoneId = $pullZone['data']['Id'] ?? null;
        throw_unless($pullZoneId, Exception::class, 'Failed to create pull zone: Pull zone ID not returned from API.');

        // Persist the origin configuration explicitly. Bunny's create response may not
        // materialize the origin host header in a stable way for IP-based origins.
        $this->syncOriginConfiguration($website, $cdnProvider, $pullZoneId);

        // Add all hostnames to the pull zone with rollback on failure
        try {
            foreach ($hostnames as $hostname) {
                $this->info(sprintf("Adding hostname '%s' to pull zone.", $hostname));
                $this->addHostname($cdnProvider, $pullZoneId, $hostname);
            }
        } catch (Exception $exception) {
            // Hostname addition failed - rollback by deleting the pull zone we just created
            $this->error('Failed to add hostname. Rolling back pull zone creation...');
            $this->rollbackPullZone($cdnProvider, $pullZoneId);

            throw $exception;
        }

        // Refresh pull zone data to get updated hostnames list
        $this->info('Refreshing pull zone data to get updated hostnames...');
        $updatedPullZone = BunnyApi::getPullZone($cdnProvider, $pullZoneId);

        // Store full CDN response in website metadata (with updated hostnames)
        $website->setMetadata('cdn', $updatedPullZone['data']);
        $website->save();

        $successMessage = sprintf("Bunny CDN (pull zone) setup completed successfully for '%s'. Pull Zone ID: %s", $website->domain, $pullZoneId);
        $this->logActivity($website, ActivityAction::UPDATE, $successMessage);
        $this->updateWebsiteStep($website, $successMessage, 'done');
    }

    /**
     * Rollback pull zone creation by deleting it.
     *
     * @param  Provider  $cdnProvider  The Provider instance.
     * @param  int  $pullZoneId  The pull zone ID to delete.
     */
    private function rollbackPullZone(Provider $cdnProvider, int $pullZoneId): void
    {
        try {
            BunnyApi::deletePullZone($cdnProvider, $pullZoneId);
            $this->warn(sprintf('Pull zone %d deleted successfully during rollback.', $pullZoneId));
        } catch (BunnyApiException $bunnyApiException) {
            // Log the error but don't throw - we want the original error to be reported
            $this->error('Warning: Failed to delete pull zone during rollback: '.$bunnyApiException->getMessage());
        }
    }

    /**
     * Create a pull zone for the website.
     *
     * @param  Website  $website  The website instance.
     * @param  Provider  $cdnProvider  The Provider instance.
     * @param  string  $originUrl  The origin URL for the pull zone.
     * @param  string  $originHostHeader  The host header to send to the origin server.
     * @return array The API response.
     *
     * @throws Exception If the API call fails after all retries.
     */
    private function createPullZone(Website $website, Provider $cdnProvider, string $originUrl, string $originHostHeader): array
    {
        throw_unless($website->uid, Exception::class, 'Website uid is not set. Cannot generate pull zone name.');

        // Generate base name from uid (lowercase, only alphanumeric and hyphens)
        // First convert to lowercase, then remove non-alphanumeric characters (except hyphens)
        $baseName = strtolower((string) preg_replace('/[^a-zA-Z0-9-]/', '', (string) $website->uid));

        throw_if($baseName === '' || $baseName === '0', Exception::class, 'Invalid uid: Cannot generate valid pull zone name from uid.');

        // Pull zone options - set the origin host header so the origin server
        // knows which domain is being requested
        $options = [
            'OriginHostHeader' => $originHostHeader,
            'AddHostHeader' => false,
            'FollowRedirects' => false,
            'EnableAutoSSL' => true,
        ];

        $maxRetries = 26; // a-z
        $suffix = '';

        for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
            $pullZoneName = $baseName.$suffix;

            $this->info('Attempting to create pull zone with name: '.$pullZoneName);

            try {
                $response = BunnyApi::createPullZone(
                    $cdnProvider,
                    $pullZoneName,
                    $originUrl,
                    $options
                );

                // If we get here, the pull zone was created successfully
                $this->info('Successfully created pull zone with name: '.$pullZoneName);

                return $response;
            } catch (BunnyApiException $e) {
                // Check if it's a name conflict error (409 Conflict or name validation error)
                $errorMessage = $e->getMessage();
                $errorCode = $e->getCode();

                // Check for name conflicts or validation errors
                // Specifically check for the "pullzone.name_taken" error key
                $isNameConflict = $errorCode === 409
                    || stripos($errorMessage, 'pullzone.name_taken') !== false
                    || stripos($errorMessage, 'name') !== false
                    || stripos($errorMessage, 'already exists') !== false
                    || stripos($errorMessage, 'already taken') !== false
                    || stripos($errorMessage, 'must consist') !== false;

                if ($isNameConflict && $attempt < $maxRetries) {
                    $this->line(sprintf("Pull zone name '%s' is not available (Error: %s), trying next...", $pullZoneName, $errorMessage));
                    // Generate next suffix: empty for first attempt, then a, b, c, ..., z
                    $suffix = $attempt === 0 ? 'a' : chr(ord('a') + $attempt);

                    continue;
                }

                // For other errors or if we've exhausted all retries, throw
                throw $e;
            }
        }

        throw new Exception(sprintf('Failed to create pull zone: All name variations (up to %d attempts) are not available.', $maxRetries));
    }

    /**
     * Persist origin settings after pull-zone creation to avoid Bunny falling back
     * to the raw IP host header for IP-based origins.
     */
    private function syncOriginConfiguration(Website $website, Provider $cdnProvider, int $pullZoneId): void
    {
        $this->info(sprintf('Syncing origin configuration for pull zone %d.', $pullZoneId));

        $response = $this->pullZoneService->syncOriginConfiguration($website, $cdnProvider, $pullZoneId);

        if (($response['status'] ?? '') !== 'success') {
            $errorMessage = $response['message'] ?? 'Unknown error during origin configuration sync.';
            throw new Exception('Failed to sync Bunny origin configuration: '.$errorMessage);
        }
    }

    /**
     * Add a hostname to the pull zone.
     *
     * Note: This method doesn't check if hostname already exists in the pull zone
     * since we call it right after creating a new zone. The API will return an error
     * if the hostname is already registered to another pull zone.
     *
     * @param  Provider  $cdnProvider  The Provider instance.
     * @param  int  $pullZoneId  The pull zone ID.
     * @param  string  $hostname  The hostname to add.
     *
     * @throws Exception If the API call fails (except for already registered hostnames).
     */
    private function addHostname(Provider $cdnProvider, int $pullZoneId, string $hostname): void
    {
        try {
            $response = BunnyApi::addPullZoneHostname($cdnProvider, $pullZoneId, $hostname);

            if (($response['status'] ?? '') !== 'success') {
                $errorMessage = $response['message'] ?? 'Unknown error during hostname addition.';
                throw new Exception(sprintf("Failed to add hostname '%s': %s", $hostname, $errorMessage));
            }

            $this->info(sprintf("Successfully added hostname '%s' to pull zone.", $hostname));
        } catch (BunnyApiException $bunnyApiException) {
            $errorMessage = $bunnyApiException->getMessage();
            $errorCode = $bunnyApiException->getCode();

            // Check if the error is about hostname already being registered to another pull zone
            $isHostnameAlreadyRegistered = stripos($errorMessage, 'hostname is already registered') !== false
                || stripos($errorMessage, 'already registered') !== false
                || stripos($errorMessage, 'hostname already exists') !== false
                || ($errorCode === 400 && stripos($errorMessage, 'hostname') !== false);

            // Hostname is registered to a different pull zone - this is a critical error
            // that prevents CDN from working properly for this domain
            throw_if($isHostnameAlreadyRegistered, Exception::class, sprintf("Hostname '%s' is already registered to another pull zone. ", $hostname)
            .'You must remove it from the other pull zone first before it can be added here.', $errorCode, $bunnyApiException);

            // For other errors, re-throw the exception
            throw new Exception(sprintf("Failed to add hostname '%s': %s", $hostname, $errorMessage), $errorCode, $bunnyApiException);
        }
    }

    /**
     * Updates the website's provisioning step for 'setup_bunny_cdn'.
     *
     * @param  Website  $website  The website instance.
     * @param  string  $message  The message to log for the step.
     * @param  string  $status  The status of the step (e.g., 'done', 'failed').
     */
    private function updateWebsiteStep(Website $website, string $message, string $status): void
    {
        $website->updateProvisioningStep('setup_bunny_cdn', $message, $status);
    }
}
