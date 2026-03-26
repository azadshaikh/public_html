<?php

namespace Modules\Platform\Console;

use App\Enums\ActivityAction;
use App\Traits\ActivityTrait;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Modules\Platform\Enums\WebsiteStatus;
use Modules\Platform\Events\DnsVerificationTimeoutEvent;
use Modules\Platform\Jobs\SendAgencyWebhook;
use Modules\Platform\Jobs\WebsiteProvision;
use Modules\Platform\Libs\DnsResolver;
use Modules\Platform\Models\Website;

/**
 * Polls all websites stuck in WaitingForDns status and checks DNS propagation.
 *
 * Scheduled every 60 seconds. Only polls websites where the user has confirmed
 * they updated their nameservers (dns_confirmed_by_user metadata = true).
 * Tracks check count and times out after 24 hours from confirmation.
 */
class DnsPollPendingCommand extends Command
{
    use ActivityTrait;

    protected $signature = 'platform:dns:poll-pending';

    protected $description = 'Poll DNS propagation for websites waiting for DNS verification.';

    /**
     * Maximum hours to wait for DNS propagation before timing out (from confirmation time).
     */
    private const TIMEOUT_HOURS = 24;

    public function handle(): int
    {
        $websites = Website::query()
            ->where('status', WebsiteStatus::WaitingForDns)
            ->with(['domainRecord', 'server'])
            ->get();

        if ($websites->isEmpty()) {
            $this->line('No websites waiting for DNS verification.');

            return self::SUCCESS;
        }

        // Only poll websites where user has confirmed they updated DNS
        $confirmedWebsites = $websites->filter(
            fn (Website $w) => (bool) $w->getMetadata('dns_confirmed_by_user')
        );

        $pendingConfirmation = $websites->count() - $confirmedWebsites->count();
        if ($pendingConfirmation > 0) {
            $this->line(sprintf('%d website(s) awaiting user DNS confirmation — skipping.', $pendingConfirmation));
        }

        if ($confirmedWebsites->isEmpty()) {
            $this->line('No confirmed websites to poll.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Checking DNS for %d confirmed website(s)...', $confirmedWebsites->count()));

        foreach ($confirmedWebsites as $website) {
            try {
                $this->checkWebsiteDns($website);
            } catch (\Throwable $e) {
                Log::error(sprintf('DNS poll error for website #%d: %s', $website->id, $e->getMessage()));
                $this->error(sprintf('Error checking website #%d: %s', $website->id, $e->getMessage()));
            }
        }

        return self::SUCCESS;
    }

    private function checkWebsiteDns(Website $website): void
    {
        $domainRecord = $website->domainRecord;

        if (! $domainRecord) {
            $this->warn(sprintf('Website #%d has no domain record — skipping.', $website->id));

            return;
        }

        $dnsMode = $website->dns_mode;
        $rootDomain = $domainRecord->name;

        // Increment check count
        $checkCount = (int) ($website->getMetadata('dns_check_count') ?? 0) + 1;
        $website->setMetadata('dns_check_count', $checkCount);
        $website->setMetadata('dns_last_checked_at', now()->toIso8601String());
        $website->save();

        // Check timeout from the moment the user confirmed DNS update.
        // If dns_confirmed_at is missing, we cannot measure elapsed time — skip the timeout check.
        $confirmedAt = $website->getMetadata('dns_confirmed_at');
        if (! $confirmedAt) {
            $this->warn(sprintf('Website #%d has dns_confirmed_by_user but no dns_confirmed_at — timeout check skipped.', $website->id));

            return;
        }

        $timeoutRef = Carbon::parse($confirmedAt);

        if ($timeoutRef->diffInHours(now()) > self::TIMEOUT_HOURS) {
            $this->warn(sprintf('Website #%d (%s) timed out after %dh waiting for DNS (check #%d).', $website->id, $rootDomain, self::TIMEOUT_HOURS, $checkCount));

            $website->status = WebsiteStatus::Failed;
            $website->save();

            $website->updateProvisioningStep(
                'verify_dns',
                sprintf('DNS verification timed out after %d hours (%d checks).', self::TIMEOUT_HOURS, $checkCount),
                'failed'
            );

            $this->logActivity($website, ActivityAction::UPDATE, sprintf(
                'DNS verification timed out for %s after %d hours (%d checks).',
                $rootDomain,
                self::TIMEOUT_HOURS,
                $checkCount
            ));

            // Fire platform-side event so admin listeners can notify the team
            event(new DnsVerificationTimeoutEvent($website, $rootDomain, $checkCount));

            // Notify agency of the timeout/failure
            SendAgencyWebhook::dispatchForWebsite($website, 'website.status_changed', [
                'status' => WebsiteStatus::Failed->value,
                'message' => sprintf('DNS verification timed out after %d hours.', self::TIMEOUT_HOURS),
            ]);

            return;
        }

        if ($dnsMode === 'managed') {
            $checkResult = $this->checkManagedDns($domainRecord, $rootDomain);
            $dnsVerified = $checkResult['verified'];

            // Persist observed NS and not-registered flag for frontend display
            $website->setMetadata('dns_check_result', [
                'observed_ns' => $checkResult['observed_ns'],
                'not_registered' => $checkResult['not_registered'],
                'checked_at' => now()->toIso8601String(),
            ]);
            $website->setMetadata('dns_domain_not_registered', $checkResult['not_registered']);
            $website->save();

            if ($checkResult['not_registered']) {
                $this->warn(sprintf(
                    'Website #%d (%s): domain is not yet registered in DNS (NXDOMAIN) — check #%d.',
                    $website->id, $rootDomain, $checkCount
                ));
                $website->updateProvisioningStep(
                    'verify_dns',
                    sprintf('Domain %s is not registered yet. (Check %d)', $rootDomain, $checkCount),
                    'waiting'
                );

                return;
            }
        } else {
            $dnsVerified = match ($dnsMode) {
                'external' => $this->checkExternalDns($domainRecord, $rootDomain),
                default => false,
            };
        }

        if ($dnsVerified) {
            $this->info(sprintf('DNS verified for website #%d (%s) on check #%d — re-dispatching provisioning.', $website->id, $rootDomain, $checkCount));

            // Mark verify_dns step as done
            $website->updateProvisioningStep('verify_dns', sprintf('DNS verified for %s (after %d checks).', $rootDomain, $checkCount), 'done');

            // Reset to Provisioning so the Agency provisioning UI shows active progress
            // (status was WaitingForDns; set back before dispatching so the UI transitions correctly)
            $website->status = WebsiteStatus::Provisioning;
            $website->save();

            // Update domain record
            $domainRecord->dns_status = 'active';
            $domainRecord->dns_verified_at = now();
            $domainRecord->save();

            $this->logActivity($website, ActivityAction::UPDATE, sprintf(
                'DNS propagation confirmed for %s after %d checks. Resuming provisioning.',
                $rootDomain,
                $checkCount
            ));

            // Re-dispatch provisioning job — existing "skip already-done steps" logic handles resumption
            WebsiteProvision::dispatch($website);
        } else {
            $waitingMessage = match ($dnsMode) {
                'external' => sprintf('Waiting for customer DNS records to propagate. (Check %d)', $checkCount),
                default => sprintf('Waiting for NS delegation to propagate. (Check %d)', $checkCount),
            };

            // Update step message with check count for visibility
            $website->updateProvisioningStep(
                'verify_dns',
                $waitingMessage,
                'waiting'
            );

            $this->line(sprintf(
                'Website #%d (%s): DNS not ready yet — check #%d (confirmed %s).',
                $website->id,
                $rootDomain,
                $checkCount,
                $confirmedAt ? Carbon::parse($confirmedAt)->diffForHumans() : 'unknown'
            ));
        }
    }

    /**
     * Check NS delegation for managed domains using public DNS resolvers.
     *
     * @return array{verified: bool, observed_ns: string[], not_registered: bool}
     */
    private function checkManagedDns($domainRecord, string $rootDomain): array
    {
        $expectedNs = $domainRecord->getMetadata('dns_instructions.nameservers') ?? ['ns1.bunny.net', 'ns2.bunny.net'];
        $expectedLower = array_map(fn (string $ns) => rtrim(strtolower($ns), '.'), $expectedNs);

        $observed = DnsResolver::queryNsObserved($rootDomain);

        if ($observed['not_registered']) {
            return ['verified' => false, 'observed_ns' => [], 'not_registered' => true];
        }

        $missing = array_diff($expectedLower, $observed['nameservers']);

        return [
            'verified' => empty($missing),
            'observed_ns' => $observed['nameservers'],
            'not_registered' => false,
        ];
    }

    /**
     * Check required records for external DNS domains using public DNS resolvers.
     */
    private function checkExternalDns($domainRecord, string $rootDomain): bool
    {
        $instructions = $domainRecord->getMetadata('dns_instructions');
        $requiredRecords = $instructions['records'] ?? [];

        foreach ($requiredRecords as $required) {
            $queryName = str_contains($required['name'], '.') ? $required['name'] : $required['name'].'.'.$rootDomain;

            $isApexCname = strtoupper((string) $required['type']) === 'CNAME'
                && $queryName === $rootDomain;

            $verified = $isApexCname
                ? DnsResolver::verifyCnameTarget($queryName, $required['value'], true)
                : DnsResolver::verifyRecord($queryName, $required['type'], $required['value']);

            if (! $verified) {
                return false;
            }
        }

        return ! empty($requiredRecords);
    }
}
