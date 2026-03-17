<?php

namespace Modules\Platform\Console;

use Modules\Platform\Jobs\DomainSyncWhois;
use Modules\Platform\Models\Domain;
use Modules\Platform\Models\Provider;
use Modules\Platform\Models\Website;
use Modules\Platform\Services\WhoisService;
use Throwable;

/**
 * Resolves and associates a domain record for a website during provisioning.
 *
 * This command extracts the root domain from the website's full domain (which may include
 * subdomains), checks if it exists in the domains table, and either associates the existing
 * domain or creates a new one. This ensures every website has a proper domain record that
 * can be used for SSL certificates, DNS management, and other domain-related operations.
 *
 * For example:
 * - azad83.10.157.14.98.traefik.me -> checks for traefik.me
 * - azad.astero.site -> checks for astero.site
 * - www.example.com -> checks for example.com
 */
class DomainResolveCommand extends BaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'platform:resolve-domain {website_id : The ID of the website}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Resolve and associate the root domain record for a website.';

    /**
     * The step key for this command.
     */
    protected ?string $stepKey = 'resolve_domain';

    /**
     * The core logic of the command.
     *
     * @param  Website  $website  The website instance.
     */
    protected function handleCommand(Website $website): void
    {
        // Extract the root domain from the website's full domain
        $rootDomain = $this->extractRootDomain($website->domain);

        $this->info('Website domain: '.$website->domain);
        $this->info('Extracted root domain: '.$rootDomain);

        // Check if domain already exists in the domains table
        $domain = Domain::query()->where('name', $rootDomain)->first();
        /** @var Domain|null $domain */
        if ($domain) {
            $this->info(sprintf("Domain '%s' found in database (ID: %d)", $rootDomain, $domain->id));
            $this->associateDomain($website, $domain);
            $this->dispatchWhoisSync($domain);
        } else {
            $this->info(sprintf("Domain '%s' not found. Creating new domain record...", $rootDomain));
            $domain = $this->createDomain($website, $rootDomain);
            $this->associateDomain($website, $domain);
        }

        // Update provisioning step with success info
        $website->updateProvisioningStep(
            $this->stepKey,
            sprintf("Domain '%s' resolved and associated (ID: %d)", $rootDomain, $domain->id),
            'done'
        );
    }

    /**
     * Extract the root/registrable domain from a full domain name.
     *
     * This handles multi-level TLDs like .co.uk, .com.au, as well as
     * IP-based domains like 10.157.14.98.traefik.me.
     *
     * @param  string  $fullDomain  The full domain (may include subdomains)
     * @return string The root domain (e.g., "example.com", "traefik.me")
     */
    private function extractRootDomain(string $fullDomain): string
    {
        // Remove protocol if present
        $domain = preg_replace('#^https?://#', '', $fullDomain);

        // Remove www. prefix
        $domain = preg_replace('#^www\.#i', '', (string) $domain);

        // Remove trailing slashes/paths
        $domain = explode('/', (string) $domain)[0];

        // Remove port if present
        $domain = explode(':', $domain)[0];

        // Split by dots
        $parts = explode('.', $domain);
        $partsCount = count($parts);

        // If 2 or fewer parts, it's already a root domain
        if ($partsCount <= 2) {
            return strtolower($domain);
        }

        // Check for known multi-level TLDs (this is a simplified list)
        // For production, consider using a comprehensive public suffix list
        $multiLevelTlds = [
            'co.uk', 'org.uk', 'me.uk', 'ac.uk',
            'com.au', 'net.au', 'org.au',
            'co.nz', 'net.nz', 'org.nz',
            'co.za', 'org.za', 'net.za',
            'com.br', 'net.br', 'org.br',
            'co.in', 'net.in', 'org.in',
            'co.jp', 'ne.jp', 'or.jp',
        ];

        // Check if the last two parts form a multi-level TLD
        $lastTwo = $parts[$partsCount - 2].'.'.$parts[$partsCount - 1];
        if (in_array(strtolower($lastTwo), $multiLevelTlds)) {
            // Return last 3 parts for multi-level TLDs
            return strtolower(implode('.', array_slice($parts, -3)));
        }

        // Standard case: return last 2 parts
        return strtolower(implode('.', array_slice($parts, -2)));
    }

    /**
     * Associate the domain with the website.
     *
     * @param  Website  $website  The website instance
     * @param  Domain  $domain  The domain to associate
     */
    private function associateDomain(Website $website, Domain $domain): void
    {
        $website->domain_id = $domain->id;
        $website->save();

        $this->info(sprintf('Associated website #%d with domain #%d (%s)', $website->id, $domain->id, $domain->domain_name));
    }

    /**
     * Create a new domain record for the website.
     *
     * @param  Website  $website  The website instance (for owner info)
     * @param  string  $rootDomain  The root domain name
     * @return Domain The created domain
     */
    private function createDomain(Website $website, string $rootDomain): Domain
    {
        // Get the "Other" domain registrar provider
        $otherRegistrar = Provider::query()->where('vendor', 'other')
            ->where('type', Provider::TYPE_DOMAIN_REGISTRAR)
            ->first();

        // Try to get WHOIS data for the domain
        $whoisData = $this->fetchWhoisData($rootDomain);

        // Create the domain record
        /** @var Domain $domain */
        $domain = Domain::query()->create([
            'name' => $rootDomain,
            'status' => 'active',
            'registrar_name' => $whoisData['registrar'] ?? null,
            'registered_date' => $whoisData['registered_date'] ?? null,
            'expiry_date' => $whoisData['expiry_date'] ?? null,
            'updated_date' => $whoisData['updated_date'] ?? null,
            'name_server_1' => $whoisData['name_servers'][0] ?? null,
            'name_server_2' => $whoisData['name_servers'][1] ?? null,
            'name_server_3' => $whoisData['name_servers'][2] ?? null,
            'name_server_4' => $whoisData['name_servers'][3] ?? null,
            'metadata' => [
                'source' => 'website_provisioning',
                'website_id' => $website->id,
                'auto_created' => true,
                'created_at' => now()->toIso8601String(),
            ],
        ]);

        // Assign the registrar provider via polymorphic relationship
        if ($otherRegistrar) {
            $domain->assignProvider($otherRegistrar->id, true);
        }

        $this->info(sprintf("Created domain record #%d for '%s'", $domain->id, $rootDomain));

        // Dispatch WHOIS sync job
        $this->dispatchWhoisSync($domain);

        return $domain;
    }

    /**
     * Dispatch WHOIS sync job for a domain.
     *
     * @param  Domain  $domain  The domain to sync
     */
    private function dispatchWhoisSync(Domain $domain): void
    {
        // Dispatch background job to sync WHOIS data (non-blocking)
        // This ensures provisioning continues even if WHOIS lookup fails
        try {
            dispatch(new DomainSyncWhois($domain));
            $this->line('  WHOIS sync job dispatched for background processing');
        } catch (Throwable $throwable) {
            // Log but don't fail provisioning if job dispatch fails
            $this->warn('  Could not dispatch WHOIS sync job: '.$throwable->getMessage());
        }
    }

    /**
     * Fetch WHOIS data for a domain.
     *
     * @param  string  $domain  The domain to look up
     * @return array WHOIS data (may be empty if lookup fails)
     */
    private function fetchWhoisData(string $domain): array
    {
        try {
            $whoisService = resolve(WhoisService::class);
            $result = $whoisService->getWhoisData($domain, Domain::class);

            if ($result['success'] ?? false) {
                return [
                    'registrar' => $result['registrar'] ?? null,
                    'registered_date' => $result['created'] ?? null,
                    'expiry_date' => $result['expiry'] ?? null,
                    'updated_date' => $result['update'] ?? null,
                    'name_servers' => $result['name_servers'] ?? [],
                ];
            }
        } catch (Throwable $throwable) {
            $this->warn(sprintf('WHOIS lookup failed for %s: %s', $domain, $throwable->getMessage()));
        }

        return [];
    }
}
