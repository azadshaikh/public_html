<?php

declare(strict_types=1);

namespace Modules\Platform\Console;

use Illuminate\Console\Command;
use Modules\Platform\Enums\WebsiteStatus;
use Modules\Platform\Libs\DnsResolver;
use Modules\Platform\Models\Website;
use Modules\Platform\Services\AcmeChallengeAliasService;

class DnsDebugCommand extends Command
{
    protected $signature = 'platform:dns:debug
        {website : Website ID, website domain, or root domain}
        {--json : Output the debug payload as JSON}';

    protected $description = 'Debug DNS validation for a website using the same public resolvers as the provisioning flow.';

    public function handle(): int
    {
        $website = $this->resolveWebsite((string) $this->argument('website'));
        $domainRecord = $website->domainRecord;

        if (! $domainRecord) {
            $this->error(sprintf('Website #%d has no linked domain record.', $website->id));

            return self::FAILURE;
        }

        $rootDomain = (string) $domainRecord->name;
        $dnsMode = (string) ($website->dns_mode ?? $domainRecord->dns_mode ?? 'unknown');
        $instructions = $domainRecord->getMetadata('dns_instructions') ?? [];
        $requiredRecords = $instructions['records'] ?? [];
        $verifyDnsStep = $website->getProvisioningStep('verify_dns') ?? [];
        $dnsConfirmedByUser = (bool) $website->getMetadata('dns_confirmed_by_user');

        $recordChecks = collect($requiredRecords)
            ->map(fn (array $record): array => $this->buildRecordCheck($record, $rootDomain))
            ->values()
            ->all();

        $allRecordsPassing = $recordChecks !== []
            && collect($recordChecks)->every(fn (array $record): bool => $record['passes']);

        $pollGate = $this->buildPollGateSummary($website, $requiredRecords !== [], $allRecordsPassing);

        $payload = [
            'website' => [
                'id' => $website->id,
                'name' => $website->name,
                'domain' => $website->domain,
                'status' => $website->status instanceof WebsiteStatus ? $website->status->value : (string) $website->status,
                'dns_mode' => $dnsMode,
                'server_ip' => $website->server?->ip,
            ],
            'domain' => [
                'id' => $domainRecord->id,
                'name' => $rootDomain,
                'dns_status' => $domainRecord->dns_status,
                'dns_verified_at' => optional($domainRecord->dns_verified_at)?->toIso8601String(),
                'challenge_alias' => $domainRecord->getMetadata('challenge_alias'),
                'dns_instructions' => $instructions,
            ],
            'dns_validation' => [
                'confirmed_by_user' => $dnsConfirmedByUser,
                'confirmed_at' => $website->getMetadata('dns_confirmed_at'),
                'check_count' => (int) ($website->getMetadata('dns_check_count') ?? 0),
                'last_checked_at' => $website->getMetadata('dns_last_checked_at'),
                'poll_gate' => $pollGate,
            ],
            'verify_step' => [
                'status' => $verifyDnsStep['status'] ?? null,
                'message' => $verifyDnsStep['message'] ?? null,
                'updated_at' => $verifyDnsStep['updated_at'] ?? null,
            ],
            'acme' => $this->buildAcmeSummary($rootDomain),
            'record_checks' => $recordChecks,
            'overall' => [
                'records_pass' => $allRecordsPassing,
                'would_resume_provisioning' => $pollGate['eligible'] && $allRecordsPassing,
                'resolver_quorum' => 'any',
            ],
        ];

        if ((bool) $this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return $payload['overall']['would_resume_provisioning'] ? self::SUCCESS : self::FAILURE;
        }

        $this->renderDebugOutput($payload);

        return $payload['overall']['would_resume_provisioning'] ? self::SUCCESS : self::FAILURE;
    }

    private function resolveWebsite(string $identifier): Website
    {
        $normalizedIdentifier = strtolower(trim($identifier));

        $query = Website::query()
            ->with(['domainRecord', 'server'])
            ->withTrashed();

        if (ctype_digit($normalizedIdentifier)) {
            return $query->findOrFail((int) $normalizedIdentifier);
        }

        $matches = $query
            ->whereRaw('LOWER(domain) = ?', [$normalizedIdentifier])
            ->orWhereHas('domainRecord', fn ($domainQuery) => $domainQuery->whereRaw('LOWER(name) = ?', [$normalizedIdentifier]))
            ->get();

        if ($matches->count() === 1) {
            /** @var Website $website */
            $website = $matches->first();

            return $website;
        }

        if ($matches->isEmpty()) {
            $this->fail(sprintf('No website found for identifier "%s".', $identifier));
        }

        $matchSummary = $matches
            ->map(fn (Website $website): string => sprintf('#%d (%s)', $website->id, $website->domain ?: $website->name ?: 'unknown'))
            ->implode(', ');

        $this->fail(sprintf(
            'Identifier "%s" matched multiple websites: %s. Re-run with a website ID.',
            $identifier,
            $matchSummary
        ));
    }

    /**
     * @param  array{name: string, type: string, value: string}  $record
     * @return array<string, mixed>
     */
    private function buildRecordCheck(array $record, string $rootDomain): array
    {
        $type = strtoupper((string) $record['type']);
        $queryName = str_contains((string) $record['name'], '.')
            ? (string) $record['name']
            : (string) $record['name'].'.'.$rootDomain;
        $expectedValue = (string) $record['value'];
        $isApexCname = $type === 'CNAME' && $queryName === $rootDomain;
        $passes = $isApexCname
            ? DnsResolver::verifyCnameTarget($queryName, $expectedValue, true)
            : DnsResolver::verifyRecord($queryName, $type, $expectedValue);

        $result = [
            'name' => (string) $record['name'],
            'query_name' => $queryName,
            'type' => $type,
            'expected_value' => $expectedValue,
            'passes' => $passes,
            'is_apex_cname' => $isApexCname,
            'resolver_results' => [],
            'trace' => DnsResolver::traceRecord($queryName, $type),
        ];

        foreach (DnsResolver::resolvers() as $resolver) {
            $resolverResult = [
                'resolver' => $resolver,
                'records' => DnsResolver::queryRecords($queryName, $type, $resolver),
            ];

            if ($isApexCname) {
                $resolverResult['resolved_addresses'] = array_values(array_unique(array_merge(
                    DnsResolver::queryRecords($queryName, 'A', $resolver),
                    DnsResolver::queryRecords($queryName, 'AAAA', $resolver),
                )));
                $resolverResult['target_addresses'] = array_values(array_unique(array_merge(
                    DnsResolver::queryRecords($expectedValue, 'A', $resolver),
                    DnsResolver::queryRecords($expectedValue, 'AAAA', $resolver),
                )));
            }

            $result['resolver_results'][] = $resolverResult;
        }

        return $result;
    }

    /**
     * @return array{eligible: bool, blockers: string[]}
     */
    private function buildPollGateSummary(Website $website, bool $hasInstructions, bool $allRecordsPassing): array
    {
        $blockers = [];

        if (($website->status instanceof WebsiteStatus ? $website->status : WebsiteStatus::tryFrom((string) $website->status)) !== WebsiteStatus::WaitingForDns) {
            $blockers[] = 'website status is not waiting_for_dns';
        }

        if (! (bool) $website->getMetadata('dns_confirmed_by_user')) {
            $blockers[] = 'dns_confirmed_by_user is false';
        }

        if (! $website->getMetadata('dns_confirmed_at')) {
            $blockers[] = 'dns_confirmed_at is missing';
        }

        if (! $hasInstructions) {
            $blockers[] = 'dns_instructions.records is empty';
        }

        if (! $allRecordsPassing) {
            $blockers[] = 'one or more required DNS records are still failing';
        }

        return [
            'eligible' => $blockers === [],
            'blockers' => $blockers,
        ];
    }

    /**
     * @return array{alias_domain: string|null, challenge_alias: string|null, bunny_api_key_configured: bool, error: string|null}
     */
    private function buildAcmeSummary(string $rootDomain): array
    {
        try {
            $service = resolve(AcmeChallengeAliasService::class);

            return [
                'alias_domain' => $service->aliasDomain(),
                'challenge_alias' => $service->buildChallengeAlias($rootDomain),
                'bunny_api_key_configured' => trim((string) config('platform.acme_challenge.bunny_api_key')) !== '',
                'error' => null,
            ];
        } catch (\Throwable $throwable) {
            return [
                'alias_domain' => null,
                'challenge_alias' => null,
                'bunny_api_key_configured' => false,
                'error' => $throwable->getMessage(),
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function renderDebugOutput(array $payload): void
    {
        $this->info(sprintf(
            'Website #%d %s',
            $payload['website']['id'],
            $payload['website']['domain'] ?: ($payload['website']['name'] ?: '')
        ));

        $this->table(
            ['Field', 'Value'],
            [
                ['Website status', $payload['website']['status'] ?: 'null'],
                ['DNS mode', $payload['website']['dns_mode'] ?: 'null'],
                ['Root domain', $payload['domain']['name'] ?: 'null'],
                ['Domain DNS status', $payload['domain']['dns_status'] ?: 'null'],
                ['Verify step status', $payload['verify_step']['status'] ?: 'null'],
                ['Verify step message', $payload['verify_step']['message'] ?: 'null'],
                ['DNS confirmed by user', $payload['dns_validation']['confirmed_by_user'] ? 'yes' : 'no'],
                ['DNS confirmed at', $payload['dns_validation']['confirmed_at'] ?: 'null'],
                ['DNS check count', (string) $payload['dns_validation']['check_count']],
                ['DNS last checked at', $payload['dns_validation']['last_checked_at'] ?: 'null'],
                ['ACME alias domain', $payload['acme']['alias_domain'] ?? 'null'],
                ['ACME challenge alias', $payload['domain']['challenge_alias'] ?: ($payload['acme']['challenge_alias'] ?? 'null')],
                ['ACME Bunny key configured', $payload['acme']['bunny_api_key_configured'] ? 'yes' : 'no'],
                ['Resolver quorum', $payload['overall']['resolver_quorum']],
                ['Poll eligible now', $payload['dns_validation']['poll_gate']['eligible'] ? 'yes' : 'no'],
                ['Would resume provisioning now', $payload['overall']['would_resume_provisioning'] ? 'yes' : 'no'],
            ]
        );

        if ($payload['acme']['error']) {
            $this->warn('ACME config error: '.$payload['acme']['error']);
        }

        $blockers = $payload['dns_validation']['poll_gate']['blockers'];
        if ($blockers !== []) {
            $this->warn('Current blockers:');
            foreach ($blockers as $blocker) {
                $this->line(' - '.$blocker);
            }
        }

        if ($payload['record_checks'] === []) {
            $this->warn('No dns_instructions.records metadata found on the linked domain.');

            return;
        }

        foreach ($payload['record_checks'] as $recordCheck) {
            $this->newLine();
            $this->line(sprintf(
                '[%s] %s -> %s [%s]',
                $recordCheck['type'],
                $recordCheck['query_name'],
                $recordCheck['expected_value'],
                $recordCheck['passes'] ? 'PASS' : 'FAIL'
            ));

            foreach ($recordCheck['resolver_results'] as $resolverResult) {
                $records = $resolverResult['records'] !== []
                    ? implode(', ', $resolverResult['records'])
                    : '(none)';

                $this->line(sprintf(
                    '  %s %s: %s',
                    $resolverResult['resolver'],
                    $recordCheck['type'],
                    $records
                ));

                if ($recordCheck['is_apex_cname']) {
                    $resolvedAddresses = $resolverResult['resolved_addresses'] !== []
                        ? implode(', ', $resolverResult['resolved_addresses'])
                        : '(none)';
                    $targetAddresses = $resolverResult['target_addresses'] !== []
                        ? implode(', ', $resolverResult['target_addresses'])
                        : '(none)';

                    $this->line(sprintf('  %s resolved A/AAAA: %s', $resolverResult['resolver'], $resolvedAddresses));
                    $this->line(sprintf('  %s target A/AAAA: %s', $resolverResult['resolver'], $targetAddresses));
                }
            }

            $traceRecords = $recordCheck['trace']['records'] !== []
                ? implode(', ', $recordCheck['trace']['records'])
                : '(none)';
            $traceNameservers = $recordCheck['trace']['nameservers'] !== []
                ? implode(', ', $recordCheck['trace']['nameservers'])
                : '(none)';

            $this->line(sprintf('  trace %s: %s', $recordCheck['type'], $traceRecords));
            $this->line(sprintf('  trace NS: %s', $traceNameservers));
        }
    }
}
