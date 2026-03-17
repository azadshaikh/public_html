<?php

namespace Modules\Platform\Console;

use App\Enums\ActivityAction;
use App\Traits\ActivityTrait;
use Exception;
use Modules\Platform\Exceptions\WaitingException;
use Modules\Platform\Libs\BunnyApi;
use Modules\Platform\Libs\BunnyApiException;
use Modules\Platform\Libs\DnsResolver;
use Modules\Platform\Models\Provider;
use Modules\Platform\Models\Website;

/**
 * Verifies DNS propagation for a website domain before CDN/SSL steps can proceed.
 *
 * - SUBDOMAIN: auto-skipped by the orchestrator (never reaches this command)
 * - MANAGED (NS delegation): creates Bunny DNS zone on first run, then checks NS propagation
 * - EXTERNAL (CNAME delegation): generates challenge alias, checks A + CNAME + _acme-challenge records
 *
 * Returns exit code 2 (WAITING) when DNS is not yet propagated, causing the orchestrator
 * to set website status to WaitingForDns and pause the pipeline.
 */
class DnsVerifyStepCommand extends BaseCommand
{
    use ActivityTrait;

    protected $signature = 'platform:dns:verify-step {website_id : The ID of the website}';

    protected $description = 'Verify DNS propagation for website domain.';

    protected ?string $stepKey = 'verify_dns';

    protected function handleCommand(Website $website): void
    {
        $dnsMode = $website->dns_mode;

        throw_unless(
            in_array($dnsMode, ['managed', 'external']),
            Exception::class,
            sprintf('verify_dns should not run for dns_mode=%s (orchestrator should skip).', $dnsMode ?? 'null')
        );

        $domainRecord = $website->domainRecord;
        throw_unless($domainRecord, Exception::class, 'Domain record not found for website.');

        $rootDomain = $domainRecord->name;

        if ($dnsMode === 'managed') {
            $this->handleManagedDns($website, $domainRecord, $rootDomain);
        } else {
            $this->handleExternalDns($website, $domainRecord, $rootDomain);
        }
    }

    /**
     * Handle NS delegation (managed) DNS verification.
     *
     * On first run: create Bunny DNS zone, store zone_id + NS records.
     * On subsequent runs: check if NS records have propagated.
     */
    private function handleManagedDns(Website $website, $domainRecord, string $rootDomain): void
    {
        // Step 1: Create Bunny DNS zone if not already done (idempotent)
        if (! $domainRecord->dns_zone_id) {
            $this->info(sprintf('Creating Bunny DNS zone for %s...', $rootDomain));

            $dnsProvider = $website->getProvider(Provider::TYPE_DNS);
            throw_unless(
                $dnsProvider && $dnsProvider->vendor === 'bunny',
                Exception::class,
                'No Bunny DNS provider is associated with this website.'
            );

            $zoneData = $this->createOrFindDnsZone($dnsProvider, $rootDomain);
            $zoneId = $zoneData['Id'] ?? null;
            throw_unless($zoneId, Exception::class, 'Bunny DNS zone created but no zone ID returned.');

            // Extract nameservers from the zone response
            $nameservers = $this->extractNameservers($zoneData);

            // Store zone info on domain record
            $domainRecord->dns_zone_id = $zoneId;
            $domainRecord->dns_mode = 'managed';
            $domainRecord->dns_status = 'pending_ns';
            $domainRecord->save();

            // Store NS instructions in metadata for the Agency provisioning UI
            $domainRecord->setMetadata('dns_instructions', [
                'mode' => 'managed',
                'nameservers' => $nameservers,
            ]);
            $domainRecord->save();

            $this->info(sprintf(
                'DNS zone created (ID: %d). Nameservers: %s',
                $zoneId,
                implode(', ', $nameservers)
            ));

            $this->logActivity($website, ActivityAction::UPDATE, sprintf(
                'DNS zone created for %s. Waiting for NS delegation.',
                $rootDomain
            ));
        }

        // Step 2: Check NS propagation using public DNS resolvers (Cloudflare + Google)
        $expectedNameservers = $domainRecord->getMetadata('dns_instructions.nameservers') ?? ['ns1.bunny.net', 'ns2.bunny.net'];
        $nsVerified = DnsResolver::verifyNsDelegation($rootDomain, $expectedNameservers);

        if (! $nsVerified) {
            $resolverResults = DnsResolver::queryNsFromAllResolvers($rootDomain);
            $foundSummary = collect($resolverResults)
                ->map(fn ($records, $resolver) => sprintf('%s: [%s]', $resolver, implode(', ', $records)))
                ->implode('; ');

            $this->warn(sprintf(
                'NS records not matching. Expected: [%s], Found: %s',
                implode(', ', $expectedNameservers),
                $foundSummary
            ));
            $website->markProvisioningStepWaiting('verify_dns', 'Waiting for NS delegation to propagate.');
            $this->exitWithWaiting();
        }

        // DNS confirmed
        $domainRecord->dns_status = 'active';
        $domainRecord->dns_verified_at = now();
        $domainRecord->save();

        $message = sprintf('NS delegation confirmed for %s.', $rootDomain);
        $this->logActivity($website, ActivityAction::UPDATE, $message);
        $website->markProvisioningStepDone('verify_dns', $message);
    }

    /**
     * Handle CNAME delegation (external) DNS verification.
     *
     * On first run: generate challenge alias, store required records.
     * On subsequent runs: check if A + CNAME + _acme-challenge records are set.
     */
    private function handleExternalDns(Website $website, $domainRecord, string $rootDomain): void
    {
        $serverIp = $website->server?->ip;
        throw_unless($serverIp, Exception::class, 'Server IP not available for external DNS verification.');

        // Get CDN hostname — setup_bunny_cdn runs before verify_dns in the pipeline,
        // so the pull zone hostname should always be present unless CDN is explicitly disabled.
        $cdnHostnames = $website->getMetadata('cdn.Hostnames') ?? [];
        $pullzoneHostname = null;
        foreach ($cdnHostnames as $hostname) {
            $hostValue = $hostname['Value'] ?? '';
            if (str_ends_with($hostValue, '.b-cdn.net')) {
                $pullzoneHostname = $hostValue;
                break;
            }
        }

        // Fail fast if CDN should be active but pull zone was never created
        if (! $pullzoneHostname && ! $website->skip_cdn) {
            throw new Exception('Pull zone hostname not found in CDN metadata. Ensure setup_bunny_cdn ran before verify_dns.');
        }

        // Step 1: Generate challenge alias and store instructions (idempotent)
        if (! $domainRecord->getMetadata('dns_instructions')) {
            $challengeAlias = sprintf('_acme-challenge.%s.ssl-validation.astero.in', $rootDomain);

            if ($pullzoneHostname) {
                // CDN active: customer must use CNAME records pointing to the CDN edge
                $records = [
                    ['type' => 'CNAME', 'name' => $rootDomain, 'value' => $pullzoneHostname],
                    ['type' => 'CNAME', 'name' => 'www', 'value' => $pullzoneHostname],
                ];
            } else {
                // CDN disabled (skip_cdn=true): direct A records to origin server
                $records = [
                    ['type' => 'A', 'name' => $rootDomain, 'value' => $serverIp],
                    ['type' => 'A', 'name' => 'www.'.$rootDomain, 'value' => $serverIp],
                ];
            }

            $records[] = ['type' => 'CNAME', 'name' => '_acme-challenge', 'value' => $challengeAlias];

            $domainRecord->dns_mode = 'external';
            $domainRecord->dns_status = 'pending_records';
            $domainRecord->setMetadata('dns_instructions', [
                'mode' => 'external',
                'records' => $records,
            ]);
            $domainRecord->setMetadata('challenge_alias', $challengeAlias);
            $domainRecord->save();

            $this->info(sprintf('External DNS instructions generated for %s.', $rootDomain));
            $this->logActivity($website, ActivityAction::UPDATE, sprintf(
                'External DNS instructions generated for %s. Waiting for customer records.',
                $rootDomain
            ));
        }

        // Step 2: Verify required records
        $instructions = $domainRecord->getMetadata('dns_instructions');
        $requiredRecords = $instructions['records'] ?? [];
        $allVerified = true;

        foreach ($requiredRecords as $required) {
            $verified = $this->verifyDnsRecord($required['type'], $required['name'], $required['value'], $rootDomain);
            if (! $verified) {
                $this->warn(sprintf('DNS record not found: %s %s → %s', $required['type'], $required['name'], $required['value']));
                $allVerified = false;
            }
        }

        if (! $allVerified) {
            $website->markProvisioningStepWaiting('verify_dns', 'Waiting for customer to add DNS records.');
            $this->exitWithWaiting();
        }

        // DNS confirmed
        $domainRecord->dns_status = 'active';
        $domainRecord->dns_verified_at = now();
        $domainRecord->save();

        $message = sprintf('External DNS records verified for %s.', $rootDomain);
        $this->logActivity($website, ActivityAction::UPDATE, $message);
        $website->markProvisioningStepDone('verify_dns', $message);
    }

    /**
     * Verify a single DNS record has propagated using public DNS resolvers.
     */
    private function verifyDnsRecord(string $type, string $name, string $expectedValue, string $rootDomain): bool
    {
        // For short names like "www" or "_acme-challenge", fully qualify with root domain
        $queryName = str_contains($name, '.') ? $name : $name.'.'.$rootDomain;

        return DnsResolver::verifyRecord($queryName, $type, $expectedValue);
    }

    /**
     * Exit the command with the WAITING exit code.
     *
     * BaseCommand::handle() wraps handleCommand() and returns SUCCESS on normal completion.
     * We throw a special exception that BaseCommand can't catch as a normal failure,
     * so instead we use a custom exception that carries the exit code.
     *
     * @throws WaitingException
     */
    private function exitWithWaiting(): void
    {
        throw new WaitingException('DNS verification pending — returning exit code 2 (WAITING).');
    }

    /**
     * Create a new DNS zone or find the existing one if it was already created.
     *
     * Handles the 409 Conflict case when retrying a step where the zone was
     * already created on Bunny but the zone_id wasn't stored locally.
     *
     * @return array The zone data from Bunny API
     */
    private function createOrFindDnsZone(Provider $dnsProvider, string $rootDomain): array
    {
        try {
            $zoneResult = BunnyApi::createDnsZone($dnsProvider, $rootDomain);

            throw_unless(
                ($zoneResult['status'] ?? '') === 'success',
                Exception::class,
                'Failed to create Bunny DNS zone: '.($zoneResult['message'] ?? 'Unknown error')
            );

            return $zoneResult['data'] ?? [];
        } catch (BunnyApiException $e) {
            // Zone already exists (previous run created it but zone_id wasn't saved)
            // Bunny may return 409 Conflict or 400 Bad Request with "already taken" message
            $isAlreadyExists = $e->getCode() === 409
                || str_contains(strtolower($e->getMessage()), 'already taken')
                || str_contains(strtolower($e->getMessage()), 'already exists');

            if ($isAlreadyExists) {
                $this->warn('DNS zone already exists on Bunny — looking up existing zone...');

                $existingZone = BunnyApi::findDnsZoneByDomain($dnsProvider, $rootDomain);
                throw_unless(
                    $existingZone,
                    Exception::class,
                    sprintf('DNS zone for %s exists on Bunny but could not be retrieved. Delete it manually and retry.', $rootDomain)
                );

                $this->info(sprintf('Found existing zone ID: %d', $existingZone['Id'] ?? 0));

                return $existingZone;
            }

            throw $e;
        }
    }

    /**
     * Extract nameservers from a Bunny DNS zone response.
     *
     * Bunny returns Nameserver1/Nameserver2 fields in the zone object.
     *
     * @return string[]
     */
    private function extractNameservers(array $zoneData): array
    {
        $nameservers = [];

        if (! empty($zoneData['Nameserver1'])) {
            $nameservers[] = $zoneData['Nameserver1'];
        }

        if (! empty($zoneData['Nameserver2'])) {
            $nameservers[] = $zoneData['Nameserver2'];
        }

        // Fallback to default Bunny nameservers
        if (empty($nameservers)) {
            $nameservers = ['ns1.bunny.net', 'ns2.bunny.net'];
        }

        return $nameservers;
    }
}
