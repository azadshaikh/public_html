<?php

namespace Modules\Platform\Console;

use App\Enums\ActivityAction;
use App\Traits\ActivityTrait;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use Modules\Platform\Exceptions\WaitingException;
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

    private const int AUTO_SSL_REQUEST_COOLDOWN_SECONDS = 300;

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

        $customHostnames = $this->customHostnames($website);
        throw_unless($customHostnames !== [], Exception::class, 'No custom CDN hostnames found for this website.');

        // Step 1: Upload custom SSL cert to Bunny pull zone
        $this->info(sprintf('Uploading SSL cert to pull zone %d...', $pullzoneId));

        $uploadSuccess = $this->uploadCertificate($cdnProvider, $pullzoneId, $certificate, $privateKey, $website);

        // Step 2: Request/verify live Bunny hostname certificates before enabling ForceSSL
        $liveHostnames = $this->fetchLiveHostnameStatuses($cdnProvider, $pullzoneId);
        $pendingHostnames = $this->pendingCustomHostnames($customHostnames, $liveHostnames);

        if ($pendingHostnames !== []) {
            $this->requestAutoSslForPendingHostnames($website, $cdnProvider, $pendingHostnames);
            $liveHostnames = $this->fetchLiveHostnameStatuses($cdnProvider, $pullzoneId);
            $pendingHostnames = $this->pendingCustomHostnames($customHostnames, $liveHostnames);
        }

        if ($pendingHostnames !== []) {
            $this->disableForceSslForHostnames($cdnProvider, $pullzoneId, $pendingHostnames, $liveHostnames);
            $liveHostnames = $this->applyForceSslState($liveHostnames, $pendingHostnames, false);
            $this->persistWaitingMetadata($website, $sslSecret, $liveHostnames, $pendingHostnames, $uploadSuccess);

            $message = sprintf(
                'Waiting for Bunny custom hostname SSL to become active for %s.',
                implode(', ', $pendingHostnames)
            );

            $website->markProvisioningStepWaiting('configure_cdn_ssl', $message);

            throw new WaitingException($message);
        }

        // Step 3: Enable ForceSSL only after Bunny reports the custom hostname cert is active
        $this->enableForceSslForHostnames($cdnProvider, $pullzoneId, $customHostnames);
        $liveHostnames = $this->applyForceSslState($liveHostnames, $customHostnames, true);

        // Step 4: Store CDN SSL metadata
        $website->setMetadata('cdn_ssl', [
            'configured_at' => now()->toIso8601String(),
            'last_checked_at' => now()->toIso8601String(),
            'cert_secret_id' => $sslSecret->id,
            'expires_at' => $sslSecret->expires_at?->toIso8601String(),
            'force_ssl' => true,
            'auto_ssl' => $this->hasAutoSslRequests($website),
            'custom_cert_uploaded' => $uploadSuccess,
            'pending_hostnames' => [],
            'hostnames' => $liveHostnames,
        ]);
        $website->save();

        $message = $uploadSuccess
            ? sprintf('CDN SSL configured and hostname certificate is active (expires %s).', $sslSecret->expires_at?->toDateString() ?? 'unknown')
            : 'CDN SSL configured via Bunny hostname certificate activation.';

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
            $this->warn(sprintf('Custom cert upload failed: %s — Bunny hostname SSL will be requested.', $e->getMessage()));

            Log::warning(sprintf(
                'Custom SSL cert upload failed for pullzone %d: %s. Bunny hostname SSL will be requested.',
                $pullzoneId,
                $e->getMessage()
            ));

            return false;
        }
    }

    /**
     * @return list<string>
     */
    private function customHostnames(Website $website): array
    {
        $hostnames = $website->getMetadata('cdn.Hostnames') ?? [];

        return collect($hostnames)
            ->map(fn (array $hostname): string => trim((string) ($hostname['Value'] ?? '')))
            ->filter(fn (string $hostname): bool => $hostname !== '' && ! str_ends_with($hostname, '.b-cdn.net'))
            ->values()
            ->all();
    }

    /**
     * @return array<string, array{hostname: string, force_ssl: bool, has_certificate: bool}>
     */
    private function fetchLiveHostnameStatuses(Provider $cdnProvider, int $pullzoneId): array
    {
        $result = BunnyApi::getPullZone($cdnProvider, $pullzoneId);
        $hostnames = $result['data']['Hostnames'] ?? [];

        return collect($hostnames)
            ->mapWithKeys(fn (array $hostname): array => [
                strtolower((string) ($hostname['Value'] ?? '')) => [
                    'hostname' => (string) ($hostname['Value'] ?? ''),
                    'force_ssl' => (bool) ($hostname['ForceSSL'] ?? false),
                    'has_certificate' => (bool) ($hostname['HasCertificate'] ?? false),
                ],
            ])
            ->all();
    }

    /**
     * @param  list<string>  $customHostnames
     * @param  array<string, array{hostname: string, force_ssl: bool, has_certificate: bool}>  $liveHostnames
     * @return list<string>
     */
    private function pendingCustomHostnames(array $customHostnames, array $liveHostnames): array
    {
        return collect($customHostnames)
            ->filter(function (string $hostname) use ($liveHostnames): bool {
                $state = $liveHostnames[strtolower($hostname)] ?? null;

                return ! $state || ! $state['has_certificate'];
            })
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $hostnames
     */
    private function enableForceSslForHostnames(Provider $cdnProvider, int $pullzoneId, array $hostnames): void
    {
        foreach ($hostnames as $hostname) {
            $this->line(sprintf('Enabling ForceSSL for %s...', $hostname));
            $this->setForceSsl($cdnProvider, $pullzoneId, $hostname, true);
        }
    }

    /**
     * @param  list<string>  $pendingHostnames
     * @param  array<string, array{hostname: string, force_ssl: bool, has_certificate: bool}>  $liveHostnames
     */
    private function disableForceSslForHostnames(Provider $cdnProvider, int $pullzoneId, array $pendingHostnames, array $liveHostnames): void
    {
        foreach ($pendingHostnames as $hostname) {
            $state = $liveHostnames[strtolower($hostname)] ?? null;

            if ($state && ! $state['force_ssl']) {
                continue;
            }

            $this->line(sprintf('Disabling ForceSSL for %s until Bunny hostname SSL is ready...', $hostname));
            $this->setForceSsl($cdnProvider, $pullzoneId, $hostname, false);
        }
    }

    private function setForceSsl(Provider $cdnProvider, int $pullzoneId, string $hostname, bool $enabled): void
    {
        try {
            BunnyApi::setForceSSL($cdnProvider, $pullzoneId, $hostname, $enabled);
        } catch (Exception $e) {
            $mode = $enabled ? 'enable' : 'disable';
            $this->warn(sprintf('Failed to %s ForceSSL for %s: %s', $mode, $hostname, $e->getMessage()));
            Log::warning(sprintf(
                'Failed to %s ForceSSL for hostname %s on pullzone %d: %s',
                $mode,
                $hostname,
                $pullzoneId,
                $e->getMessage()
            ));
        }
    }

    /**
     * @param  list<string>  $pendingHostnames
     */
    private function requestAutoSslForPendingHostnames(Website $website, Provider $cdnProvider, array $pendingHostnames): void
    {
        foreach ($pendingHostnames as $hostname) {
            if (! $this->shouldRequestAutoSsl($website, $hostname)) {
                continue;
            }

            try {
                BunnyApi::addFreeCertificate($cdnProvider, $hostname);
                $this->info(sprintf('Requested Bunny hostname SSL for %s.', $hostname));
                $this->rememberAutoSslRequest($website, $hostname);
            } catch (Exception $autoSslException) {
                $this->warn(sprintf('Bunny hostname SSL request failed for %s: %s', $hostname, $autoSslException->getMessage()));
                Log::warning(sprintf(
                    'Bunny hostname SSL request failed for hostname %s: %s',
                    $hostname,
                    $autoSslException->getMessage()
                ));
            }
        }
    }

    private function shouldRequestAutoSsl(Website $website, string $hostname): bool
    {
        $requestHistory = $website->getMetadata('cdn_ssl.auto_ssl_requested_at', []);
        $lastRequestedAt = $requestHistory[$hostname] ?? null;

        if (! is_string($lastRequestedAt) || $lastRequestedAt === '') {
            return true;
        }

        return Carbon::parse($lastRequestedAt)->diffInSeconds(now()) >= self::AUTO_SSL_REQUEST_COOLDOWN_SECONDS;
    }

    private function rememberAutoSslRequest(Website $website, string $hostname): void
    {
        $requestHistory = $website->getMetadata('cdn_ssl.auto_ssl_requested_at', []);
        $requestHistory[$hostname] = now()->toIso8601String();
        $website->setMetadata('cdn_ssl.auto_ssl_requested_at', $requestHistory);
        $website->save();
    }

    /**
     * @param  array<string, array{hostname: string, force_ssl: bool, has_certificate: bool}>  $liveHostnames
     * @param  list<string>  $pendingHostnames
     */
    private function persistWaitingMetadata(Website $website, $sslSecret, array $liveHostnames, array $pendingHostnames, bool $uploadSuccess): void
    {
        $website->setMetadata('cdn_ssl', array_merge(
            $website->getMetadata('cdn_ssl', []),
            [
                'last_checked_at' => now()->toIso8601String(),
                'cert_secret_id' => $sslSecret->id,
                'expires_at' => $sslSecret->expires_at?->toIso8601String(),
                'force_ssl' => false,
                'auto_ssl' => true,
                'custom_cert_uploaded' => $uploadSuccess,
                'pending_hostnames' => $pendingHostnames,
                'hostnames' => $liveHostnames,
            ]
        ));
        $website->save();
    }

    private function hasAutoSslRequests(Website $website): bool
    {
        return $website->getMetadata('cdn_ssl.auto_ssl_requested_at', []) !== [];
    }

    /**
     * @param  array<string, array{hostname: string, force_ssl: bool, has_certificate: bool}>  $liveHostnames
     * @param  list<string>  $hostnames
     * @return array<string, array{hostname: string, force_ssl: bool, has_certificate: bool}>
     */
    private function applyForceSslState(array $liveHostnames, array $hostnames, bool $enabled): array
    {
        foreach ($hostnames as $hostname) {
            $key = strtolower($hostname);

            if (! isset($liveHostnames[$key])) {
                continue;
            }

            $liveHostnames[$key]['force_ssl'] = $enabled;
        }

        return $liveHostnames;
    }
}
