<?php

namespace Modules\Platform\Console;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\Platform\Libs\BunnyApi;
use Modules\Platform\Libs\HestiaClient;
use Modules\Platform\Models\Domain;
use Modules\Platform\Models\Provider;
use Modules\Platform\Models\Secret;
use Modules\Platform\Models\Website;
use Modules\Platform\Services\DomainSslCertificateService;
use Modules\Platform\Services\ServerSSHService;

/**
 * Renews SSL certificates expiring within 15 days.
 *
 * Scheduled daily at 02:00 in PlatformServiceProvider.
 *
 * Flow per expiring certificate:
 * 1. Identify renewal server (domain.acme_server_id)
 * 2. SSH → acme.sh --renew --force
 * 3. Fetch renewed cert from server
 * 4. Update platform_secrets (new cert + expiry)
 * 5. Re-install on all websites using this domain's cert
 * 6. Re-upload to Bunny CDN pull zone per website
 */
class SslRenewExpiringCommand extends Command
{
    protected $signature = 'platform:ssl:renew-expiring
        {--dry-run : List expiring certs without renewing}';

    protected $description = 'Renew SSL certificates expiring within 15 days.';

    private int $renewed = 0;

    private int $failed = 0;

    private int $skipped = 0;

    public function handle(): int
    {
        $sslService = resolve(DomainSslCertificateService::class);

        // Get certs expiring within 30 days (service broadens the window), then narrow to
        // certs that are still in the future AND within 15 days. Without isFuture() check,
        // already-expired certs would also match since diffInDays() returns absolute values.
        $expiringCerts = $sslService->getAllCertificates('expiring')
            ->filter(fn (Secret $cert) => $cert->expires_at
                && $cert->expires_at->isFuture()
                && $cert->expires_at->diffInDays(now()) <= 15);

        if ($expiringCerts->isEmpty()) {
            $this->info('No SSL certificates expiring within 15 days.');
            Log::info('platform:ssl:renew-expiring — no certs need renewal.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Found %d certificate(s) expiring within 15 days.', $expiringCerts->count()));

        if ($this->option('dry-run')) {
            $this->table(['ID', 'Domain', 'Expires At', 'Days Left'], $expiringCerts->map(fn (Secret $cert) => [
                $cert->id,
                $cert->getMetadata('name', $cert->key),
                $cert->expires_at->toDateString(),
                $cert->expires_at->diffInDays(now()),
            ])->toArray());

            return self::SUCCESS;
        }

        foreach ($expiringCerts as $cert) {
            $this->renewCertificate($cert);
        }

        $this->newLine();
        $this->info(sprintf('Renewal complete: %d renewed, %d failed, %d skipped.', $this->renewed, $this->failed, $this->skipped));

        Log::info('platform:ssl:renew-expiring — completed', [
            'renewed' => $this->renewed,
            'failed' => $this->failed,
            'skipped' => $this->skipped,
        ]);

        return $this->failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Renew a single certificate and re-distribute to all websites.
     */
    private function renewCertificate(Secret $cert): void
    {
        /** @var Domain|null $domain */
        $domain = $cert->secretable;

        if (! $domain instanceof Domain) {
            $this->warn(sprintf('Cert #%d is not attached to a domain, skipping.', $cert->id));
            $this->skipped++;

            return;
        }

        $rootDomain = $domain->name ?? $domain->domain_name;
        $this->line(sprintf('→ Renewing cert for %s (expires %s, %d days left)...',
            $rootDomain,
            $cert->expires_at->toDateString(),
            $cert->expires_at->diffInDays(now()),
        ));

        // Check ssl_auto_renew flag
        if ($domain->ssl_auto_renew === false) {
            $this->warn(sprintf('  Auto-renewal disabled for %s, skipping.', $rootDomain));
            $this->skipped++;

            return;
        }

        try {
            // Step 1: Determine renewal server
            $renewalServer = $domain->acmeServer;
            if (! $renewalServer || $renewalServer->trashed()) {
                $this->warn(sprintf('  acme_server_id is null or server deleted for %s, skipping.', $rootDomain));
                Log::warning('SSL renewal skipped: no acme server', [
                    'domain_id' => $domain->id,
                    'domain' => $rootDomain,
                ]);
                $this->skipped++;

                return;
            }

            // Step 2: Resolve Bunny API key (same logic as SslIssueCertificateCommand)
            $dnsMode = $this->resolveDnsMode($domain);
            $bunnyApiKey = $this->resolveBunnyApiKey($domain, $dnsMode);

            // Step 3: Determine challenge alias for external mode
            $challengeAlias = ($dnsMode === 'external')
                ? ($domain->getMetadata('challenge_alias') ?? '')
                : '';

            // Step 4: SSH → acme.sh --renew --force
            // Pass API key via --env: prefix so a-exec sets it as an env var
            // instead of a positional arg (avoids leaking in /proc/PID/cmdline)
            $this->info(sprintf('  Renewing on server %s...', $renewalServer->name ?? $renewalServer->id));
            $renewResult = HestiaClient::execute(
                'a-renew-wildcard-ssl',
                $renewalServer,
                [$rootDomain, '--env:BUNNY_API_KEY='.$bunnyApiKey, $challengeAlias],
                180
            );

            if (! ($renewResult['success'] ?? false)) {
                throw new Exception(sprintf(
                    'acme.sh renewal failed for %s: %s',
                    $rootDomain,
                    $renewResult['message'] ?? 'Unknown error'
                ));
            }

            // Step 5: Fetch renewed cert from server
            $this->info('  Fetching renewed cert from server...');
            $certData = $this->fetchCertFromServer($renewalServer, $rootDomain);
            if (! $certData) {
                throw new Exception('Failed to fetch renewed cert from server.');
            }

            // Step 6: Update platform_secrets record
            $this->info('  Updating certificate in platform_secrets...');
            $newSecret = $this->updateCertificate($domain, $rootDomain, $certData, $cert);

            // Step 7: Re-install on all websites using this domain
            $websites = Website::query()
                ->where('domain_id', $domain->id)
                ->whereNull('deleted_at')
                ->whereIn('status', ['active', 'suspended'])
                ->get();

            $this->info(sprintf('  Re-installing on %d website(s)...', $websites->count()));
            $this->reinstallOnWebsites($websites, $newSecret);

            // Step 8: Re-upload to CDN for each website
            $this->reuploadToCdn($websites, $newSecret);

            // Update domain SSL status
            $domain->ssl_status = 'active';
            $domain->save();

            $this->info(sprintf('  ✔ Renewed successfully (new expiry: %s)', $newSecret->expires_at?->toDateString() ?? 'unknown'));
            $this->renewed++;

        } catch (Exception $e) {
            $this->error(sprintf('  ✖ Renewal failed for %s: %s', $rootDomain, $e->getMessage()));
            Log::critical('SSL renewal failed', [
                'domain_id' => $domain->id,
                'domain' => $rootDomain,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Update domain SSL status
            $domain->ssl_status = 'renewal_failed';
            $domain->save();

            $this->failed++;
        }
    }

    /**
     * Resolve the dns_mode for a domain by checking its websites.
     */
    private function resolveDnsMode(Domain $domain): string
    {
        $website = Website::query()
            ->where('domain_id', $domain->id)
            ->whereNull('deleted_at')
            ->whereNotNull('dns_mode')
            ->first();

        return $website->dns_mode ?? $domain->dns_mode ?? 'subdomain';
    }

    /**
     * Resolve Bunny API key based on dns_mode.
     * External DNS uses platform's Bunny key (challenge-alias writes to platform's zone).
     * Managed/subdomain uses agency's Bunny key.
     */
    private function resolveBunnyApiKey(Domain $domain, string $dnsMode): string
    {
        if ($dnsMode === 'external') {
            $platformDnsProvider = Provider::where('type', Provider::TYPE_DNS)
                ->where('vendor', 'bunny')
                ->whereNull('deleted_at')
                ->whereDoesntHave('agencies')
                ->first();

            throw_unless(
                $platformDnsProvider,
                Exception::class,
                'No platform-level Bunny DNS provider found for challenge-alias renewal.'
            );

            return $platformDnsProvider->credentials['api_key'];
        }

        // For managed/subdomain, find the DNS provider via a website linked to this domain
        $website = Website::query()
            ->where('domain_id', $domain->id)
            ->whereNull('deleted_at')
            ->first();

        if ($website) {
            $dnsProvider = $website->getProvider(Provider::TYPE_DNS);
            if ($dnsProvider && $dnsProvider->vendor === 'bunny') {
                return $dnsProvider->credentials['api_key'];
            }
        }

        throw new Exception('No Bunny DNS provider found for domain '.($domain->name ?? $domain->domain_name));
    }

    /**
     * Fetch certificate files from the server's acme.sh cert store via SSH.
     *
     * @return array{certificate: string, ca_bundle: string, privkey: string}|null
     */
    private function fetchCertFromServer($server, string $rootDomain): ?array
    {
        $sshService = resolve(ServerSSHService::class);
        $certDir = sprintf('/home/asterossl/.ssl-store/%s', $rootDomain);

        $certResult = $sshService->executeCommand($server, sprintf('cat %s/cert.pem', $certDir), 30);
        if (! ($certResult['success'] ?? false)) {
            return null;
        }

        $fullchainResult = $sshService->executeCommand($server, sprintf('cat %s/fullchain.pem', $certDir), 30);
        if (! ($fullchainResult['success'] ?? false)) {
            return null;
        }

        $privkeyResult = $sshService->executeCommand($server, sprintf('sudo cat %s/privkey.pem', $certDir), 30);
        if (! ($privkeyResult['success'] ?? false)) {
            return null;
        }

        $leafCert = trim($certResult['data']['output'] ?? $certResult['message'] ?? '');
        $fullchain = trim($fullchainResult['data']['output'] ?? $fullchainResult['message'] ?? '');
        $privkey = trim($privkeyResult['data']['output'] ?? $privkeyResult['message'] ?? '');

        if (empty($leafCert) || empty($fullchain) || empty($privkey)) {
            return null;
        }

        // Extract CA chain: fullchain minus the leaf cert = intermediate chain
        $caBundle = str_replace($leafCert, '', $fullchain);
        $caBundle = trim($caBundle);

        if (empty($caBundle)) {
            $parts = preg_split('/(?<=-----END CERTIFICATE-----)\s+/', $fullchain, 2);
            $caBundle = trim($parts[1] ?? '');
        }

        return [
            'certificate' => $leafCert,
            'ca_bundle' => $caBundle,
            'privkey' => $privkey,
        ];
    }

    /**
     * Store renewed certificate in platform_secrets, deactivate old cert.
     */
    private function updateCertificate(Domain $domain, string $rootDomain, array $certData, Secret $oldCert): Secret
    {
        $sslService = resolve(DomainSslCertificateService::class);

        $createData = [
            'name' => sprintf('wildcard.%s', $rootDomain),
            'private_key' => $certData['privkey'],
            'certificate' => $certData['certificate'],
            'is_wildcard' => true,
            'certificate_authority' => 'letsencrypt',
        ];

        if (! empty($certData['ca_bundle'])) {
            $createData['ca_bundle'] = $certData['ca_bundle'];
        }

        // Create new cert (keeps old one for audit trail)
        $newSecret = $sslService->create($domain, $createData);

        // Deactivate old cert
        $oldCert->is_active = false;
        $oldCert->save();

        return $newSecret;
    }

    /**
     * Re-install the renewed certificate on all websites using this domain.
     * Processes in chunks of 10 to limit concurrent SSH footprint.
     */
    private function reinstallOnWebsites($websites, Secret $newSecret): void
    {
        $certData = $newSecret->getMetadata('certificate');
        $privateKey = $newSecret->decrypted_value;
        $caBundle = $newSecret->getMetadata('ca_bundle', '');

        if (empty($certData) || empty($privateKey)) {
            $this->warn('  Cert data incomplete, skipping re-installation on websites.');

            return;
        }

        $certB64 = base64_encode((string) $certData);
        $keyB64 = base64_encode($privateKey);
        $caB64 = empty($caBundle) ? '' : base64_encode((string) $caBundle);

        foreach ($websites->chunk(10) as $batch) {
            foreach ($batch as $website) {
                $server = $website->server;
                if (! $server) {
                    $this->warn(sprintf('  No server for website %s, skipping.', $website->domain));

                    continue;
                }

                try {
                    $args = [
                        'arg1' => $website->website_username,
                        'arg2' => $website->domain,
                        'arg3' => $certB64,
                        'arg4' => $keyB64,
                    ];
                    if ($caB64 !== '' && $caB64 !== '0') {
                        $args['arg5'] = $caB64;
                    }

                    $response = HestiaClient::execute('a-install-ssl-certificate', $server, $args, 60);

                    if (! ($response['success'] ?? false)) {
                        $this->warn(sprintf('  SSL install failed on %s: %s', $website->domain, $response['message'] ?? 'Unknown'));

                        continue;
                    }

                    // Update website's SSL reference
                    $website->ssl_secret_id = $newSecret->id;
                    $website->save();

                    $this->line(sprintf('    ✔ Installed on %s', $website->domain));
                } catch (Exception $e) {
                    $this->warn(sprintf('  SSL install error on %s: %s', $website->domain, $e->getMessage()));
                    Log::warning('SSL renewal re-install failed', [
                        'website_id' => $website->id,
                        'domain' => $website->domain,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    /**
     * Re-upload the renewed certificate to CDN pull zones.
     */
    private function reuploadToCdn($websites, Secret $newSecret): void
    {
        $certData = $newSecret->getMetadata('certificate');
        $privateKey = $newSecret->decrypted_value;

        if (empty($certData) || empty($privateKey)) {
            return;
        }

        foreach ($websites as $website) {
            $pullzoneId = $website->pullzone_id;
            if (! $pullzoneId) {
                continue;
            }

            $cdnProvider = $website->cdnProvider ?? $website->dnsProvider;
            if (! $cdnProvider || $cdnProvider->vendor !== 'bunny') {
                continue;
            }

            try {
                $result = BunnyApi::updatePullZone($cdnProvider, $pullzoneId, [
                    'CertificateKey' => $privateKey,
                    'Certificate' => $certData,
                ]);

                if (($result['status'] ?? '') === 'success') {
                    $this->line(sprintf('    ✔ CDN cert updated for %s', $website->domain));
                } else {
                    $this->warn(sprintf('    CDN cert upload returned non-success for %s', $website->domain));
                }

                // Update CDN SSL metadata
                $website->setMetadata('cdn_ssl.cert_secret_id', $newSecret->id);
                $website->setMetadata('cdn_ssl.expires_at', $newSecret->expires_at?->toIso8601String());
                $website->setMetadata('cdn_ssl.configured_at', now()->toIso8601String());
                $website->save();

            } catch (Exception $e) {
                $this->warn(sprintf('  CDN cert upload failed for %s: %s', $website->domain, $e->getMessage()));
                Log::warning('SSL renewal CDN re-upload failed', [
                    'website_id' => $website->id,
                    'pullzone_id' => $pullzoneId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
