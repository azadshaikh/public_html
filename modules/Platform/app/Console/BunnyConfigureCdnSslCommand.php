<?php

namespace Modules\Platform\Console;

use App\Enums\ActivityAction;
use App\Traits\ActivityTrait;
use Exception;
use Illuminate\Support\Facades\Log;
use Modules\Platform\Libs\BunnyApi;
use Modules\Platform\Models\Provider;
use Modules\Platform\Models\Website;
use Modules\Platform\Services\DomainService;

/**
 * Uploads the wildcard SSL certificate to the Bunny CDN pull zone and enables ForceSSL.
 *
 * Prerequisite: install_ssl must have run (cert is in platform_secrets, website.ssl_secret_id set).
 * Fallback: if custom cert upload fails, triggers Bunny AutoSSL (free cert via HTTP-01).
 */
class BunnyConfigureCdnSslCommand extends BaseCommand
{
    use ActivityTrait;

    protected $signature = 'platform:bunny:configure-cdn-ssl {website_id : The ID of the website}';

    protected $description = 'Upload wildcard SSL cert to Bunny CDN and enforce HTTPS at edge.';

    protected ?string $stepKey = 'configure_cdn_ssl';

    protected function handleCommand(Website $website): void
    {
        // Resolve CDN provider (fallback to DNS provider, matching BunnySetupCdnCommand pattern)
        $cdnProvider = $website->cdnProvider ?? $website->dnsProvider;
        throw_unless(
            $cdnProvider && $cdnProvider->vendor === 'bunny',
            Exception::class,
            'No Bunny CDN provider is associated with this website.'
        );

        // Get pull zone ID from CDN metadata (set by setup_bunny_cdn step)
        $pullzoneId = (int) $website->getMetadata('cdn.Id');
        throw_unless($pullzoneId, Exception::class, 'Pull zone ID not found in website CDN metadata. Ensure setup_bunny_cdn ran first.');

        // Load SSL certificate from platform_secrets
        $domainRecord = $website->domainRecord;
        throw_unless($domainRecord, Exception::class, 'Domain record not found for website.');

        $domainService = resolve(DomainService::class);
        $sslSecret = $domainService->getBestSslCertificate($domainRecord);
        throw_unless($sslSecret, Exception::class, 'No valid SSL certificate found. Ensure issue_ssl ran first.');

        $certificate = $sslSecret->getMetadata('certificate');
        $privateKey = $sslSecret->decrypted_value;

        throw_unless(
            ! empty($certificate) && ! empty($privateKey),
            Exception::class,
            'SSL certificate data is incomplete (missing certificate or private key).'
        );

        // Step 1: Upload custom SSL cert to Bunny pull zone
        $this->info(sprintf('Uploading SSL cert to pull zone %d...', $pullzoneId));

        $uploadSuccess = $this->uploadCertificate($cdnProvider, $pullzoneId, $certificate, $privateKey, $website);

        // Step 2: Enable ForceSSL for each hostname
        $hostnames = $website->getMetadata('cdn.Hostnames') ?? [];
        foreach ($hostnames as $hostname) {
            $hostValue = $hostname['Value'] ?? '';
            if (empty($hostValue)) {
                continue;
            }

            $this->line(sprintf('Enabling ForceSSL for %s...', $hostValue));
            try {
                BunnyApi::setForceSSL($cdnProvider, $pullzoneId, $hostValue, true);
            } catch (Exception $e) {
                // Non-fatal: ForceSSL can be set manually later
                $this->warn(sprintf('Failed to enable ForceSSL for %s: %s', $hostValue, $e->getMessage()));
                Log::warning(sprintf(
                    'ForceSSL failed for hostname %s on pullzone %d: %s',
                    $hostValue,
                    $pullzoneId,
                    $e->getMessage()
                ));
            }
        }

        // Step 3: Store CDN SSL metadata
        $website->setMetadata('cdn_ssl', [
            'configured_at' => now()->toIso8601String(),
            'cert_secret_id' => $sslSecret->id,
            'expires_at' => $sslSecret->expires_at?->toIso8601String(),
            'force_ssl' => true,
            'custom_cert_uploaded' => $uploadSuccess,
        ]);
        $website->save();

        $message = $uploadSuccess
            ? sprintf('CDN SSL configured with custom wildcard cert (expires %s).', $sslSecret->expires_at?->toDateString() ?? 'unknown')
            : sprintf('CDN SSL configured via Bunny AutoSSL fallback (custom cert upload failed).');

        $this->logActivity($website, ActivityAction::UPDATE, $message);
        $website->markProvisioningStepDone('configure_cdn_ssl', $message);
    }

    /**
     * Upload custom SSL certificate to Bunny pull zone.
     * Falls back to Bunny AutoSSL (free cert) if upload fails.
     */
    private function uploadCertificate(Provider $cdnProvider, int $pullzoneId, string $certificate, string $privateKey, Website $website): bool
    {
        try {
            $result = BunnyApi::updatePullZone($cdnProvider, $pullzoneId, [
                'CertificateKey' => $privateKey,
                'Certificate' => $certificate,
            ]);

            if (($result['status'] ?? '') !== 'success') {
                throw new Exception($result['message'] ?? 'Unknown error during cert upload.');
            }

            $this->info('Custom SSL certificate uploaded to CDN successfully.');

            return true;
        } catch (Exception $e) {
            $this->warn(sprintf('Custom cert upload failed: %s — falling back to Bunny AutoSSL.', $e->getMessage()));

            Log::warning(sprintf(
                'Custom SSL cert upload failed for pullzone %d: %s. Falling back to AutoSSL.',
                $pullzoneId,
                $e->getMessage()
            ));

            // Fallback: request Bunny's free Let's Encrypt cert via HTTP-01
            // This works because DNS already points to the CDN (setup_bunny_dns ran)
            $hostnames = $website->getMetadata('cdn.Hostnames') ?? [];
            foreach ($hostnames as $hostname) {
                $hostValue = $hostname['Value'] ?? '';
                if (empty($hostValue) || str_ends_with($hostValue, '.b-cdn.net')) {
                    continue; // Skip the b-cdn.net hostname — already has SSL
                }

                try {
                    BunnyApi::addFreeCertificate($cdnProvider, $hostValue);
                    $this->info(sprintf('Bunny AutoSSL requested for %s.', $hostValue));
                } catch (Exception $autoSslException) {
                    $this->warn(sprintf('AutoSSL fallback also failed for %s: %s', $hostValue, $autoSslException->getMessage()));
                }
            }

            return false;
        }
    }
}
