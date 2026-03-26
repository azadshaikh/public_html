<?php

namespace Modules\Platform\Tests\Unit;

use Illuminate\Support\Facades\Http;
use Mockery;
use Modules\Platform\Console\HestiaPublishDomainVerificationCommand;
use Modules\Platform\Models\Domain;
use Modules\Platform\Models\Server;
use Modules\Platform\Models\Website;
use Modules\Platform\Services\ServerSSHService;
use Modules\Platform\Services\WebsiteDomainVerificationService;
use Tests\TestCase;

class WebsiteDomainVerificationTest extends TestCase
{
    public function test_domain_verification_service_matches_all_expected_hosts(): void
    {
        config()->set('platform.domain_verification.path', '/.well-known/astero-domain-verification.txt');

        $domain = new Domain;
        $domain->name = 'astero.in';
        $domain->metadata = [
            'dns_instructions' => [
                'records' => [
                    ['type' => 'CNAME', 'name' => 'astero.in', 'value' => 'asteroin.b-cdn.net'],
                    ['type' => 'CNAME', 'name' => 'www', 'value' => 'asteroin.b-cdn.net'],
                    ['type' => 'CNAME', 'name' => '_acme-challenge', 'value' => '_acme-challenge.astero.in.acme-challenge.in'],
                ],
            ],
        ];

        $website = Mockery::mock(Website::class)->makePartial();
        $website->domain = 'astero.in';
        $website->metadata = [
            'domain_verification' => [
                'token' => 'token-123',
            ],
        ];
        $website->setRelation('domainRecord', $domain);
        $website->shouldReceive('save')->zeroOrMoreTimes()->andReturnTrue();

        Http::fake([
            'http://astero.in/.well-known/astero-domain-verification.txt' => Http::response("token-123\n", 200),
            'http://www.astero.in/.well-known/astero-domain-verification.txt' => Http::response("token-123\n", 200),
        ]);

        $result = (new WebsiteDomainVerificationService)->verifyWebsite($website);

        $this->assertTrue($result['passes']);
        $this->assertCount(2, $result['checks']);
        $this->assertSame('http://astero.in/.well-known/astero-domain-verification.txt', $result['urls'][0]);
    }

    public function test_domain_verification_service_publishes_file_to_expected_remote_path(): void
    {
        config()->set('platform.domain_verification.path', '/.well-known/astero-domain-verification.txt');

        $server = new Server;
        $server->id = 1;
        $server->ip = '203.0.113.10';

        $domain = new Domain;
        $domain->name = 'astero.in';

        $website = Mockery::mock(Website::class)->makePartial();
        $website->id = 10;
        $website->uid = 'WS00010';
        $website->domain = 'astero.in';
        $website->metadata = [];
        $website->setRelation('server', $server);
        $website->setRelation('domainRecord', $domain);
        $website->shouldReceive('save')->twice()->andReturnTrue();

        $sshService = Mockery::mock(ServerSSHService::class);
        $sshService->shouldReceive('executeCommand')
            ->once()
            ->with(
                $server,
                Mockery::on(function (string $command): bool {
                    return str_contains($command, '/home/WS00010/web/astero.in/public_html/.well-known/astero-domain-verification.txt')
                        && str_contains($command, "chown 'WS00010':'WS00010'");
                }),
                30
            )
            ->andReturn(['success' => true]);

        $this->app->instance(ServerSSHService::class, $sshService);

        $service = new WebsiteDomainVerificationService;
        $service->publishVerificationFile($website);

        $this->assertSame('/.well-known/astero-domain-verification.txt', $website->getMetadata('domain_verification.path'));
        $this->assertNotSame('', (string) $website->getMetadata('domain_verification.token'));
        $this->assertNotNull($website->getMetadata('domain_verification.published_at'));
    }

    public function test_publish_domain_verification_command_marks_step_done(): void
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

        $service = Mockery::mock(WebsiteDomainVerificationService::class);
        $service->shouldReceive('publishVerificationFile')->once()->with($website);
        $service->shouldReceive('verificationPath')->andReturn('/.well-known/astero-domain-verification.txt');
        $this->app->instance(WebsiteDomainVerificationService::class, $service);

        $command = new class extends HestiaPublishDomainVerificationCommand
        {
            public function runHandleCommand(Website $website): void
            {
                $this->handleCommand($website);
            }

            public function info($string, $verbosity = null): void {}

            public function logActivity(
                $model,
                $action,
                string $message,
                array $extraProperties = [],
                bool $queue = false
            ): void {}
        };

        $command->runHandleCommand($website);

        $this->assertSame('publish_domain_verification', $website->stepUpdates[0]['step']);
        $this->assertSame('done', $website->stepUpdates[0]['status']);
        $this->assertStringContainsString('/.well-known/astero-domain-verification.txt', $website->stepUpdates[0]['message']);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}
