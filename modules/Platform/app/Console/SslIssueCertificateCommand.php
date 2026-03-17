<?php

namespace Modules\Platform\Console;

use App\Enums\ActivityAction;
use App\Traits\ActivityTrait;
use Exception;
use Modules\Platform\Libs\HestiaClient;
use Modules\Platform\Models\Provider;
use Modules\Platform\Models\Website;
use Modules\Platform\Services\DomainService;
use Modules\Platform\Services\DomainSslCertificateService;
use Modules\Platform\Services\ServerSSHService;

/**
 * Issues a wildcard SSL certificate via acme.sh DNS-01 challenge on the Hestia server.
 *
 * Platform secrets is the single source of truth for certificates.
 * Flow: check platform_secrets → if missing/expiring → issue via acme.sh → store in platform_secrets.
 * Reuses existing wildcards to respect LE rate limits (50 certs/domain/week).
 */
class SslIssueCertificateCommand extends BaseCommand
{
    use ActivityTrait;

    protected $signature = 'platform:ssl:issue-certificate {website_id : The ID of the website}';

    protected $description = 'Issue wildcard SSL certificate via acme.sh DNS-01 challenge.';

    protected ?string $stepKey = 'issue_ssl';

    protected function handleCommand(Website $website): void
    {
        if ($website->skip_ssl_issue) {
            $message = 'Skipped ACME SSL issuance; the origin SSL step will reuse a domain certificate when available or generate a self-signed certificate.';

            $this->info($message);
            $website->markProvisioningStepDone('issue_ssl', $message);

            return;
        }

        $server = $website->server;
        throw_unless($server, Exception::class, 'No server associated with this website.');

        // Guard: server must have acme.sh configured
        throw_unless(
            $server->acme_configured,
            Exception::class,
            'SSL not ready: run platform:server:setup-acme on this server first.'
        );

        $domainRecord = $website->domainRecord;
        throw_unless($domainRecord, Exception::class, 'Domain record not found for website.');

        $rootDomain = $domainRecord->name;

        // Resolve DNS provider for API key
        $dnsProvider = $website->getProvider(Provider::TYPE_DNS);
        throw_unless(
            $dnsProvider && $dnsProvider->vendor === 'bunny',
            Exception::class,
            'No Bunny DNS provider is associated with this website.'
        );

        // Determine API key based on dns_mode
        // External DNS uses platform's Bunny key (challenge-alias writes to platform's zone)
        // Managed/subdomain uses agency's Bunny key
        $dnsMode = $website->dns_mode ?? 'subdomain';

        if ($dnsMode === 'external') {
            $platformDnsProvider = Provider::where('type', Provider::TYPE_DNS)
                ->where('vendor', 'bunny')
                ->whereNull('deleted_at')
                ->whereDoesntHave('agencies')
                ->first();

            throw_unless(
                $platformDnsProvider,
                Exception::class,
                'No platform-level Bunny DNS provider found for challenge-alias SSL issuance.'
            );

            $bunnyApiKey = $platformDnsProvider->credentials['api_key'];
        } else {
            $bunnyApiKey = $dnsProvider->credentials['api_key'];
        }

        // Check platform_secrets for existing valid wildcard (single source of truth)
        $domainService = resolve(DomainService::class);
        $existingCert = $domainService->getBestSslCertificate($domainRecord);

        if ($existingCert
            && $existingCert->getMetadata('is_wildcard')
            && $existingCert->expires_at
            && $existingCert->expires_at->greaterThan(now()->addDays(30))
        ) {
            $this->info(sprintf(
                'Reusing existing wildcard cert for %s (expires %s).',
                $rootDomain,
                $existingCert->expires_at->toDateString()
            ));

            // Ensure website points to this cert
            $this->linkCertToWebsite($website, $existingCert);
            $website->markProvisioningStepDone('issue_ssl', sprintf(
                'Reused existing wildcard cert (expires %s).',
                $existingCert->expires_at->toDateString()
            ));

            return;
        }

        // Issue new certificate via acme.sh DNS-01 challenge
        $this->info(sprintf('Issuing new wildcard cert for %s...', $rootDomain));

        $challengeAlias = ($dnsMode === 'external')
            ? ($domainRecord->getMetadata('challenge_alias') ?? '')
            : '';

        $issueResult = HestiaClient::execute(
            'a-issue-wildcard-ssl',
            $server,
            [$rootDomain, '--env:BUNNY_API_KEY='.$bunnyApiKey, $challengeAlias],
            180 // acme.sh DNS-01 can take up to 3 minutes
        );

        if (! ($issueResult['success'] ?? false)) {
            throw new Exception(sprintf(
                'acme.sh failed to issue cert for %s: %s',
                $rootDomain,
                $issueResult['message'] ?? 'Unknown error'
            ));
        }

        // Fetch the newly issued cert from server
        $this->info('Cert issued — fetching from server...');
        $certData = $this->fetchCertFromServer($server, $rootDomain);
        throw_unless($certData, Exception::class, 'Failed to fetch newly issued cert from server.');

        $secret = $this->storeCertificate($domainRecord, $rootDomain, $certData, $server);
        $this->linkCertToWebsite($website, $secret);

        $message = sprintf('Wildcard SSL certificate issued for *.%s', $rootDomain);
        $this->logActivity($website, ActivityAction::UPDATE, $message);
        $website->markProvisioningStepDone('issue_ssl', $message);
    }

    /**
     * Fetch certificate files from the server's acme.sh cert store via SSH.
     *
     * Returns the leaf certificate, CA chain (intermediate), and private key separately.
     * The .ssl-store has: cert.pem (leaf only), fullchain.pem (leaf + intermediate), privkey.pem.
     *
     * @return array{certificate: string, ca_bundle: string, privkey: string}|null
     */
    private function fetchCertFromServer($server, string $rootDomain): ?array
    {
        $sshService = resolve(ServerSSHService::class);
        $certDir = sprintf('/home/asterossl/.ssl-store/%s', $rootDomain);

        // Read cert.pem (leaf certificate only)
        $certResult = $sshService->executeCommand(
            $server,
            sprintf('cat %s/cert.pem', $certDir),
            30
        );

        if (! ($certResult['success'] ?? false)) {
            $this->warn('Failed to read cert.pem: '.($certResult['message'] ?? 'Unknown error'));

            return null;
        }

        // Read fullchain.pem (leaf + intermediate chain)
        $fullchainResult = $sshService->executeCommand(
            $server,
            sprintf('cat %s/fullchain.pem', $certDir),
            30
        );

        if (! ($fullchainResult['success'] ?? false)) {
            $this->warn('Failed to read fullchain.pem: '.($fullchainResult['message'] ?? 'Unknown error'));

            return null;
        }

        // Read privkey.pem
        $privkeyResult = $sshService->executeCommand(
            $server,
            sprintf('sudo cat %s/privkey.pem', $certDir),
            30
        );

        if (! ($privkeyResult['success'] ?? false)) {
            $this->warn('Failed to read privkey.pem: '.($privkeyResult['message'] ?? 'Unknown error'));

            return null;
        }

        $leafCert = trim($certResult['data']['output'] ?? $certResult['message'] ?? '');
        $fullchain = trim($fullchainResult['data']['output'] ?? $fullchainResult['message'] ?? '');
        $privkey = trim($privkeyResult['data']['output'] ?? $privkeyResult['message'] ?? '');

        if (empty($leafCert) || empty($fullchain) || empty($privkey)) {
            return null;
        }

        // Extract CA chain: fullchain minus the leaf cert = intermediate chain
        $caBundle = $this->extractCaBundle($fullchain, $leafCert);

        return [
            'certificate' => $leafCert,
            'ca_bundle' => $caBundle,
            'privkey' => $privkey,
        ];
    }

    /**
     * Extract the CA/intermediate chain from a fullchain by removing the leaf cert.
     *
     * The fullchain is: leaf cert + intermediate cert(s).
     * We strip the first certificate block to get only the intermediate chain.
     */
    private function extractCaBundle(string $fullchain, string $leafCert): string
    {
        // Try direct removal first (leaf cert is at the start of fullchain)
        $caBundle = str_replace($leafCert, '', $fullchain);
        $caBundle = trim($caBundle);

        // Fallback: if direct removal didn't work, split by END CERTIFICATE markers
        if (empty($caBundle)) {
            $parts = preg_split(
                '/(?<=-----END CERTIFICATE-----)\s+/',
                $fullchain,
                2
            );

            $caBundle = trim($parts[1] ?? '');
        }

        return $caBundle;
    }

    /**
     * Store a certificate in platform_secrets via DomainSslCertificateService.
     */
    private function storeCertificate($domainRecord, string $rootDomain, array $certData, $server)
    {
        $sslService = resolve(DomainSslCertificateService::class);

        $createData = [
            'name' => sprintf('wildcard.%s', $rootDomain),
            'private_key' => $certData['privkey'],
            'certificate' => $certData['certificate'],
            'is_wildcard' => true,
            'certificate_authority' => 'letsencrypt',
        ];

        // Include CA bundle if available (intermediate chain)
        if (! empty($certData['ca_bundle'])) {
            $createData['ca_bundle'] = $certData['ca_bundle'];
        }

        $secret = $sslService->create($domainRecord, $createData);

        // Lock renewal to this server (acme.sh account + cert history live here)
        $domainRecord->acme_server_id = $server->id;
        $domainRecord->ssl_status = 'active';
        $domainRecord->save();

        return $secret;
    }

    /**
     * Link the SSL certificate to the website for the install_ssl step.
     */
    private function linkCertToWebsite(Website $website, $secret): void
    {
        $website->ssl_secret_id = $secret->id;
        $website->save();
    }
}
