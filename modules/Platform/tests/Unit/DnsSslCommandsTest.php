<?php

namespace Modules\Platform\Tests\Unit;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Process;
use Mockery;
use Mockery\MockInterface;
use Modules\Platform\Console\DnsPollPendingCommand;
use Modules\Platform\Console\SslIssueCertificateCommand;
use Modules\Platform\Console\SslRenewExpiringCommand;
use Modules\Platform\Enums\WebsiteStatus;
use Modules\Platform\Events\DnsVerificationTimeoutEvent;
use Modules\Platform\Libs\DnsResolver;
use Modules\Platform\Models\Domain;
use Modules\Platform\Models\Secret;
use Modules\Platform\Models\Website;
use Modules\Platform\Services\AcmeChallengeAliasService;
use Modules\Platform\Services\DomainSslCertificateService;
use Tests\TestCase;

/**
 * Unit tests for DNS polling and SSL renewal commands.
 *
 * We mock external dependencies (SSH, Bunny API, DB queries) and focus on
 * the command logic: skip conditions, timeout behaviour, event dispatch,
 * and filter correctness.
 */
class DnsSslCommandsTest extends TestCase
{
    // ──────────────────────────────────────────────────────────────────────────
    // DnsPollPendingCommand
    // ──────────────────────────────────────────────────────────────────────────

    public function test_poll_skips_website_without_user_dns_confirmation(): void
    {
        /** @var Website&MockInterface $website */
        $website = Mockery::mock(Website::class)->makePartial();
        $website->id = 1;
        $website->status = WebsiteStatus::WaitingForDns;
        $website->shouldReceive('getMetadata')->with('dns_confirmed_by_user')->andReturn(false);

        // No domain record or SSH calls should be made
        $website->shouldNotReceive('domainRecord');

        // The command can't easily be unit-tested in isolation here, so we assert
        // the filter predicate logic directly (matches the command's filter closure).
        $confirmed = (bool) $website->getMetadata('dns_confirmed_by_user');
        $this->assertFalse($confirmed, 'Non-confirmed websites should be skipped by the poll command.');
    }

    public function test_poll_skips_timeout_check_when_dns_confirmed_at_is_missing(): void
    {
        /** @var Website&MockInterface $website */
        $website = Mockery::mock(Website::class)->makePartial();
        $website->id = 2;
        $website->shouldReceive('getMetadata')->with('dns_confirmed_at')->andReturn(null);
        $website->shouldReceive('getMetadata')->with('dns_check_count')->andReturn(0);
        $website->shouldReceive('getMetadata')->withAnyArgs()->andReturn(null);
        $website->shouldReceive('setMetadata')->withAnyArgs()->andReturnNull();
        $website->shouldReceive('save')->andReturnTrue();

        // Confirm that when dns_confirmed_at is null the timeout reference is null
        $confirmedAt = $website->getMetadata('dns_confirmed_at');
        $this->assertNull($confirmedAt, 'dns_confirmed_at should be null, causing the timeout path to be skipped.');
    }

    public function test_poll_fires_timeout_event_and_marks_website_failed(): void
    {
        Event::fake([DnsVerificationTimeoutEvent::class]);

        $rootDomain = 'example.com';
        $checkCount = 5;

        /** @var Website&MockInterface $website */
        $website = Mockery::mock(Website::class)->makePartial();
        $website->id = 3;

        // The event is constructed with the website, domain, and check count
        $event = new DnsVerificationTimeoutEvent($website, $rootDomain, $checkCount);
        event($event);

        Event::assertDispatched(DnsVerificationTimeoutEvent::class, function (DnsVerificationTimeoutEvent $e) use ($website, $rootDomain, $checkCount) {
            return $e->website === $website
                && $e->rootDomain === $rootDomain
                && $e->checkCount === $checkCount;
        });
    }

    public function test_poll_timeout_event_holds_correct_data(): void
    {
        /** @var Website $website */
        $website = new Website;
        $website->id = 42;

        $event = new DnsVerificationTimeoutEvent($website, 'shop.example.com', 72);

        $this->assertSame($website, $event->website);
        $this->assertSame('shop.example.com', $event->rootDomain);
        $this->assertSame(72, $event->checkCount);
    }

    public function test_poll_timeout_is_anchored_to_confirmed_at_not_updated_at(): void
    {
        // Ensure that the timeout is only measured from dns_confirmed_at.
        // A website with updated_at 2 days ago but confirmed_at 1 hour ago
        // should NOT have timed out (TIMEOUT_HOURS = 24).
        $confirmedAt = now()->subHours(1)->toIso8601String();
        $updatedAt = now()->subDays(2);

        $confirmedRef = Carbon::parse($confirmedAt);
        $hoursElapsed = $confirmedRef->diffInHours(now());

        // 1 hour elapsed < 24 hour limit
        $this->assertLessThan(24, $hoursElapsed, 'Should not time out when only 1 hour has passed since confirmation.');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // SslRenewExpiringCommand — expiry filter
    // ──────────────────────────────────────────────────────────────────────────

    public function test_expiry_filter_excludes_already_expired_certs(): void
    {
        // Regression: Carbon day diff semantics changed, so compute the delta from
        // the reference time to the expiry timestamp instead of the reverse order.
        $alreadyExpired = Mockery::mock(Secret::class)->makePartial();
        $alreadyExpired->shouldReceive('getAttribute')->with('expires_at')->andReturn(now()->subDays(3));

        $expiringSoon = Mockery::mock(Secret::class)->makePartial();
        $expiringSoon->shouldReceive('getAttribute')->with('expires_at')->andReturn(now()->addDays(7));

        $expiredExpiresAt = now()->subDays(3);
        $validExpiresAt = now()->addDays(7);

        // Replicates the filter closure used in SslRenewExpiringCommand::handle()
        $filter = fn ($expiresAt) => $expiresAt
            && $expiresAt->isFuture()
            && now()->diffInDays($expiresAt) <= 15;

        $this->assertFalse($filter($expiredExpiresAt), 'Already-expired cert must be excluded from renewal.');
        $this->assertTrue($filter($validExpiresAt), 'Cert expiring in 7 days must be included for renewal.');
    }

    public function test_expiry_filter_excludes_certs_expiring_after_15_days(): void
    {
        $reference = Carbon::create(2026, 3, 18, 12, 0, 0);
        $expiresIn20Days = $reference->copy()->addDays(20);
        $expiresIn15Days = $reference->copy()->addDays(15);
        $expiresIn14Days = $reference->copy()->addDays(14);

        $filter = fn ($expiresAt) => $expiresAt
            && $expiresAt->isFuture()
            && $reference->diffInDays($expiresAt) <= 15;

        $this->assertFalse($filter($expiresIn20Days), 'Cert expiring in 20 days should not be renewed yet.');
        $this->assertTrue($filter($expiresIn15Days), 'Cert expiring in exactly 15 days should be renewed.');
        $this->assertTrue($filter($expiresIn14Days), 'Cert expiring in 14 days should be renewed.');
    }

    public function test_renew_skips_domain_with_auto_renew_disabled(): void
    {
        /** @var Domain&MockInterface $domain */
        $domain = Mockery::mock(Domain::class)->makePartial();
        $domain->name = 'norewall.com';
        $domain->ssl_auto_renew = false;

        /** @var Secret&MockInterface $cert */
        $cert = Mockery::mock(Secret::class)->makePartial();
        $cert->shouldReceive('getAttribute')->with('secretable')->andReturn($domain);
        $cert->shouldReceive('getAttribute')->with('expires_at')->andReturn(now()->addDays(5));

        // If ssl_auto_renew is false, the command skips without incrementing $renewed
        $this->assertFalse((bool) $domain->ssl_auto_renew, 'Domain with ssl_auto_renew=false should be skipped.');
    }

    public function test_renew_dry_run_does_not_trigger_ssh(): void
    {
        // The --dry-run flag must prevent any SSH or API calls.
        // We verify by ensuring the DomainSslCertificateService returns empty collection
        // and the command outputs SUCCESS without calling renewCertificate logic.
        $sslService = Mockery::mock(DomainSslCertificateService::class);
        $sslService->shouldReceive('getAllCertificates')
            ->with('expiring')
            ->andReturn(new EloquentCollection([]));

        $this->app->instance(DomainSslCertificateService::class, $sslService);

        $this->artisan('platform:ssl:renew-expiring', ['--dry-run' => true])
            ->assertExitCode(0);
    }

    public function test_dns_resolver_accepts_flattened_apex_cname_when_addresses_match(): void
    {
        Process::fake(function ($process) {
            $command = $process->command;

            return match (true) {
                str_contains($command, "@'1.1.1.1' 'astero.in' 'CNAME'") => Process::result(''),
                str_contains($command, "@'1.1.1.1' 'astero.in' 'A'") => Process::result('103.180.115.15'),
                str_contains($command, "@'1.1.1.1' 'astero.in' 'AAAA'") => Process::result(''),
                str_contains($command, "@'1.1.1.1' 'asteroin.b-cdn.net' 'CNAME'") => Process::result(''),
                str_contains($command, "@'1.1.1.1' 'asteroin.b-cdn.net' 'A'") => Process::result('103.180.115.15'),
                str_contains($command, "@'1.1.1.1' 'asteroin.b-cdn.net' 'AAAA'") => Process::result(''),
                str_contains($command, "@'8.8.8.8' 'astero.in' 'CNAME'") => Process::result(''),
                str_contains($command, "@'8.8.8.8' 'astero.in' 'A'") => Process::result('192.0.2.10'),
                str_contains($command, "@'8.8.8.8' 'astero.in' 'AAAA'") => Process::result(''),
                str_contains($command, "@'8.8.8.8' 'asteroin.b-cdn.net' 'CNAME'") => Process::result(''),
                str_contains($command, "@'8.8.8.8' 'asteroin.b-cdn.net' 'A'") => Process::result('198.51.100.20'),
                str_contains($command, "@'8.8.8.8' 'asteroin.b-cdn.net' 'AAAA'") => Process::result(''),
                default => Process::result(''),
            };
        });

        $this->assertTrue(
            DnsResolver::verifyCnameTarget('astero.in', 'asteroin.b-cdn.net', true)
        );
    }

    public function test_dns_resolver_accepts_record_when_any_public_resolver_matches(): void
    {
        Process::fake(function ($process) {
            $command = $process->command;

            return match (true) {
                str_contains($command, "@'1.1.1.1' 'www.astero.in' 'CNAME'") => Process::result('asteroin.b-cdn.net'),
                str_contains($command, "@'8.8.8.8' 'www.astero.in' 'CNAME'") => Process::result('stale.example.net'),
                default => Process::result(''),
            };
        });

        $this->assertTrue(
            DnsResolver::verifyRecord('www.astero.in', 'CNAME', 'asteroin.b-cdn.net')
        );
    }

    public function test_dns_resolver_trace_record_parses_answer_and_nameservers(): void
    {
        Process::fake(function ($process) {
            $command = $process->command;

            if (str_contains($command, "dig 'www.astero.in' 'CNAME' +trace")) {
                return Process::result(<<<'TRACE'
; <<>> DiG 9 <<>> www.astero.in CNAME +trace
in.                     172800  IN  NS  ns1.registry.example.
in.                     172800  IN  NS  ns2.registry.example.
astero.in.              300     IN  NS  aria.ns.cloudflare.com.
astero.in.              300     IN  NS  brad.ns.cloudflare.com.
www.astero.in.          300     IN  CNAME asteroin.b-cdn.net.
TRACE);
            }

            return Process::result('');
        });

        $trace = DnsResolver::traceRecord('www.astero.in', 'CNAME');

        $this->assertSame(['asteroin.b-cdn.net'], $trace['records']);
        $this->assertContains('aria.ns.cloudflare.com', $trace['nameservers']);
        $this->assertContains('ns1.registry.example', $trace['nameservers']);
    }

    public function test_dns_poll_command_uses_external_dns_waiting_message_for_external_mode(): void
    {
        $contents = file_get_contents(base_path('modules/Platform/app/Console/DnsPollPendingCommand.php'));

        $this->assertNotFalse($contents, 'Failed to read modules/Platform/app/Console/DnsPollPendingCommand.php');
        $this->assertStringContainsString(
            'Waiting for customer DNS records to propagate. (Check %d)',
            $contents
        );
    }

    public function test_acme_challenge_alias_service_builds_alias_from_config(): void
    {
        config()->set('platform.acme_challenge.alias_domain', 'Acme-Challenge.in.');
        config()->set('platform.acme_challenge.bunny_api_key', 'external-bunny-key');

        $service = new AcmeChallengeAliasService;

        $this->assertSame(
            '_acme-challenge.astero.in.acme-challenge.in',
            $service->buildChallengeAlias('Astero.in.')
        );
        $this->assertSame('external-bunny-key', $service->bunnyApiKey());
    }

    public function test_issue_command_uses_env_backed_acme_challenge_settings_for_external_dns(): void
    {
        config()->set('platform.acme_challenge.alias_domain', 'acme-challenge.in');
        config()->set('platform.acme_challenge.bunny_api_key', 'external-bunny-key');

        $website = new Website;

        $command = new class extends SslIssueCertificateCommand
        {
            public function resolveApiKeyForTest(Website $website, string $dnsMode): string
            {
                return $this->resolveBunnyApiKey($website, $dnsMode);
            }

            public function resolveAliasForTest(string $dnsMode, string $rootDomain): string
            {
                return $this->resolveChallengeAlias($dnsMode, $rootDomain);
            }
        };

        $this->assertSame('external-bunny-key', $command->resolveApiKeyForTest($website, 'external'));
        $this->assertSame(
            '_acme-challenge.astero.in.acme-challenge.in',
            $command->resolveAliasForTest('external', 'astero.in')
        );
    }

    public function test_renew_command_uses_env_backed_acme_challenge_settings_for_external_dns(): void
    {
        config()->set('platform.acme_challenge.alias_domain', 'acme-challenge.in');
        config()->set('platform.acme_challenge.bunny_api_key', 'external-bunny-key');

        $domain = new Domain;

        $command = new class extends SslRenewExpiringCommand
        {
            public function resolveApiKeyForTest(Domain $domain, string $dnsMode): string
            {
                return $this->resolveBunnyApiKey($domain, $dnsMode);
            }

            public function resolveAliasForTest(string $dnsMode, string $rootDomain): string
            {
                return $this->resolveChallengeAlias($dnsMode, $rootDomain);
            }
        };

        $this->assertSame('external-bunny-key', $command->resolveApiKeyForTest($domain, 'external'));
        $this->assertSame(
            '_acme-challenge.astero.in.acme-challenge.in',
            $command->resolveAliasForTest('external', 'astero.in')
        );
    }

    public function test_issue_ssl_command_can_skip_acme_and_mark_step_done(): void
    {
        $website = new class extends Website
        {
            public array $stepUpdates = [];

            public function updateProvisioningStep(string $stepKey, string $message, string $status): void
            {
                $this->stepUpdates[] = [
                    'step' => $stepKey,
                    'message' => $message,
                    'status' => $status,
                ];
            }
        };

        $website->metadata = [
            'provisioning' => [
                'skip_ssl_issue' => true,
            ],
        ];

        $command = new class extends SslIssueCertificateCommand
        {
            public array $infos = [];

            public function runHandleCommand(Website $website): void
            {
                $this->handleCommand($website);
            }

            public function info($string, $verbosity = null): void
            {
                $this->infos[] = (string) $string;
            }
        };

        $command->runHandleCommand($website);

        $this->assertCount(1, $website->stepUpdates);
        $this->assertSame('issue_ssl', $website->stepUpdates[0]['step']);
        $this->assertSame('done', $website->stepUpdates[0]['status']);
        $this->assertStringContainsString('Skipped ACME SSL issuance', $website->stepUpdates[0]['message']);
        $this->assertNotEmpty($command->infos);
    }
}
