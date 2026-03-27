<?php

namespace Modules\Platform\Console;

use App\Enums\ActivityAction;
use App\Traits\ActivityTrait;
use Exception;
use Modules\Platform\Libs\BunnyApi;
use Modules\Platform\Models\Provider;
use Modules\Platform\Models\Website;

/**
 * Creates DNS records in the Bunny DNS zone routing the website domain through CDN.
 *
 * For subdomains and managed (NS delegation) domains: creates FLATTEN CNAME at apex,
 * CNAME www, and A record for origin bypass.
 * For external (CNAME delegation) domains: skips (customer already added records).
 */
class BunnySetupDnsCommand extends BaseCommand
{
    use ActivityTrait;

    protected $signature = 'platform:bunny:setup-dns {website_id : The ID of the website}';

    protected $description = 'Create DNS CNAME records routing domain through Bunny CDN.';

    protected ?string $stepKey = 'setup_bunny_dns';

    protected function handleCommand(Website $website): void
    {
        $dnsMode = $website->dns_mode ?? 'subdomain';

        // External DNS: customer already set CNAME records — verified in verify_dns step
        if ($dnsMode === 'external') {
            $this->info('External DNS mode — skipping Bunny DNS record creation (customer manages DNS).');
            $website->markProvisioningStepDone('setup_bunny_dns', 'Skipped — external DNS managed by customer.');

            return;
        }

        // Resolve Bunny DNS provider
        $dnsProvider = $website->getProvider(Provider::TYPE_DNS);
        throw_unless(
            $dnsProvider && $dnsProvider->vendor === 'bunny',
            Exception::class,
            'No Bunny DNS provider is associated with this website.'
        );

        // Get pull zone hostname from CDN metadata (set by setup_bunny_cdn step)
        $cdnHostnames = $website->getMetadata('cdn.Hostnames') ?? [];
        $pullzoneHostname = null;

        // Find the b-cdn.net hostname from the CDN metadata
        foreach ($cdnHostnames as $hostname) {
            $hostValue = $hostname['Value'] ?? '';
            if (str_ends_with($hostValue, '.b-cdn.net')) {
                $pullzoneHostname = $hostValue;
                break;
            }
        }

        throw_unless($pullzoneHostname, Exception::class, 'Pull zone hostname not found in website CDN metadata. Ensure setup_bunny_cdn ran first.');

        // Resolve the DNS zone ID from the domain record
        $domainRecord = $website->domainRecord;
        throw_unless($domainRecord, Exception::class, 'Domain record not found for website.');

        $zoneId = (int) $domainRecord->dns_zone_id;
        throw_unless($zoneId, Exception::class, 'DNS zone ID not found on domain record.');

        $serverIp = $website->server?->ip;
        throw_unless($serverIp, Exception::class, 'Server IP not available for origin A record.');

        // Fetch existing zone records for idempotency — skip records that already exist
        // Bunny record types: A=0, CNAME=2
        $zoneData = BunnyApi::getDnsZone($dnsProvider, $zoneId);
        $existingRecords = $zoneData['data']['Records'] ?? [];
        $hasRecord = function (int $typeCode, string $name) use ($existingRecords): bool {
            $nameLower = strtolower($name);
            foreach ($existingRecords as $record) {
                if (($record['Type'] ?? null) === $typeCode && strtolower($record['Name'] ?? '') === $nameLower) {
                    return true;
                }
            }

            return false;
        };

        $this->info(sprintf('Creating DNS records in zone %d → CDN target: %s', $zoneId, $pullzoneHostname));

        // Determine the record name for the website domain within the zone
        // For subdomains (e.g. myshop.agency.com in zone agency.com), name = "myshop"
        // For managed domains (e.g. plumberbob.com in zone plumberbob.com), name = "" (apex)
        $zoneDomain = $domainRecord->name;
        $websiteDomain = $website->domain;

        if ($websiteDomain === $zoneDomain || $websiteDomain === 'www.'.$zoneDomain) {
            // Apex domain
            $recordName = '';
        } else {
            // Subdomain: extract the prefix
            $recordName = str_replace('.'.$zoneDomain, '', $websiteDomain);
        }

        // 1. Add CNAME at apex (or subdomain prefix) → pullzone hostname
        // Bunny DNS automatically handles CNAME flattening at the apex (type 2 = CNAME)
        if (! $hasRecord(2, $recordName)) {
            $this->line(sprintf('Adding CNAME record: %s → %s', $recordName ?: '@', $pullzoneHostname));
            BunnyApi::addDnsRecord($dnsProvider, $zoneId, 'CNAME', $recordName, $pullzoneHostname, 300);
        } else {
            $this->line(sprintf('CNAME record %s already exists — skipping.', $recordName ?: '@'));
        }

        // 2. Add www CNAME only for apex/managed domains (recordName === '')
        // Subdomains (e.g. myshop in zone agency.com) must NOT get a www.myshop record.
        if ($recordName === '' && $website->supportsWwwFeature()) {
            if (! $hasRecord(2, 'www')) {
                $this->line(sprintf('Adding CNAME record: www → %s', $pullzoneHostname));
                BunnyApi::addDnsRecord($dnsProvider, $zoneId, 'CNAME', 'www', $pullzoneHostname, 300);
            } else {
                $this->line('CNAME record www already exists — skipping.');
            }
        }

        // 3. Add A record for origin direct bypass (origin.domain.com → server IP, type 0 = A)
        $originName = $recordName ? 'origin.'.$recordName : 'origin';
        if (! $hasRecord(0, $originName)) {
            $this->line(sprintf('Adding A record: %s → %s', $originName, $serverIp));
            BunnyApi::addDnsRecord($dnsProvider, $zoneId, 'A', $originName, $serverIp, 300);
        } else {
            $this->line(sprintf('A record %s already exists — skipping.', $originName));
        }

        // Update domain status
        $domainRecord->dns_status = 'active';
        $domainRecord->dns_verified_at = now();
        $domainRecord->save();

        $successMessage = sprintf(
            'DNS records created for %s (zone %d): CNAME→%s, origin A→%s',
            $websiteDomain,
            $zoneId,
            $pullzoneHostname,
            $serverIp
        );

        $this->logActivity($website, ActivityAction::UPDATE, $successMessage);
        $website->markProvisioningStepDone('setup_bunny_dns', $successMessage);
    }
}
