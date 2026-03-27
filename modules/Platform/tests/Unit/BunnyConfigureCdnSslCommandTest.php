<?php

namespace Modules\Platform\Tests\Unit;

use Carbon\Carbon;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Modules\Platform\Console\BunnyConfigureCdnSslCommand;
use Modules\Platform\Exceptions\WaitingException;
use Modules\Platform\Models\Domain;
use Modules\Platform\Models\Provider;
use Modules\Platform\Models\Secret;
use Modules\Platform\Models\Server;
use Modules\Platform\Models\Website;
use Modules\Platform\Services\BunnyPullZoneService;
use Modules\Platform\Services\DomainService;
use Tests\TestCase;

class BunnyConfigureCdnSslCommandTest extends TestCase
{
    public function test_command_marks_step_waiting_until_bunny_hostname_certificate_is_active(): void
    {
        $provider = new Provider([
            'vendor' => 'bunny',
            'credentials' => ['api_key' => 'bunny-api-key'],
        ]);

        $domain = new Domain(['name' => 'astero.in']);
        $certificate = $this->makeCertificateSecret();

        $website = new class extends Website
        {
            public array $stepUpdates = [];

            public ?Provider $provider = null;

            protected function getCdnProviderAttribute(): ?Provider
            {
                return $this->provider;
            }

            public function updateProvisioningStep(string $stepKey, string $message, string $status): void
            {
                $this->stepUpdates[] = [
                    'step' => $stepKey,
                    'message' => $message,
                    'status' => $status,
                ];
            }

            public function save(array $options = []): bool
            {
                return true;
            }
        };
        $website->provider = $provider;

        $website->id = 10;
        $website->domain = 'astero.in';
        $website->metadata = [
            'cdn' => [
                'Id' => 123,
                'Hostnames' => [
                    ['Value' => 'asteroin.b-cdn.net'],
                    ['Value' => 'astero.in'],
                ],
            ],
        ];
        $website->setRelation('domainRecord', $domain);
        $website->setRelation('server', new Server([
            'ip' => '89.167.78.200',
        ]));

        $domainService = $this->mock(DomainService::class);
        $domainService->shouldReceive('getBestSslCertificate')
            ->once()
            ->with($domain)
            ->andReturn($certificate);

        $pullZoneChecks = 0;
        Http::fake(function (Request $request) use (&$pullZoneChecks) {
            if ($request->method() === 'POST' && $request->url() === 'https://api.bunny.net/pullzone/123') {
                return Http::response([], 200);
            }

            if ($request->method() === 'GET' && $request->url() === 'https://api.bunny.net/pullzone/123') {
                $pullZoneChecks++;

                return Http::response([
                    'Hostnames' => [
                        [
                            'Value' => 'asteroin.b-cdn.net',
                            'HasCertificate' => true,
                            'ForceSSL' => true,
                        ],
                        [
                            'Value' => 'astero.in',
                            'HasCertificate' => false,
                            'ForceSSL' => true,
                        ],
                    ],
                ], 200);
            }

            if ($request->method() === 'GET' && str_starts_with($request->url(), 'https://api.bunny.net/pullzone/loadFreeCertificate')) {
                return Http::response([], 200);
            }

            if ($request->method() === 'POST' && $request->url() === 'https://api.bunny.net/pullzone/setForceSSL') {
                return Http::response([], 200);
            }

            return Http::response([], 404);
        });

        $command = new class extends BunnyConfigureCdnSslCommand
        {
            public function __construct()
            {
                parent::__construct(app(BunnyPullZoneService::class));
            }

            public function runHandleCommand(Website $website): void
            {
                $this->handleCommand($website);
            }

            public function info($string, $verbosity = null): void {}

            public function line($string, $style = null, $verbosity = null): void {}

            public function warn($string, $verbosity = null): void {}
        };

        try {
            $command->runHandleCommand($website);
            $this->fail('Expected Bunny configure CDN SSL command to enter waiting state.');
        } catch (WaitingException $e) {
            $this->assertStringContainsString('Waiting for Bunny custom hostname SSL', $e->getMessage());
        }

        $this->assertSame('waiting', $website->stepUpdates[0]['status']);
        $this->assertSame('configure_cdn_ssl', $website->stepUpdates[0]['step']);
        $this->assertSame(['astero.in'], $website->getMetadata('cdn_ssl.pending_hostnames'));
        $this->assertFalse($website->getMetadata('cdn_ssl.force_ssl'));
        $this->assertSame(2, $pullZoneChecks);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && str_starts_with($request->url(), 'https://api.bunny.net/pullzone/loadFreeCertificate'));

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://api.bunny.net/pullzone/123'
            && ($request['OriginHostHeader'] ?? null) === 'astero.in'
            && ($request['AddHostHeader'] ?? null) === false
            && ($request['FollowRedirects'] ?? null) === false
            && ($request['DisableCookies'] ?? null) === false);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://api.bunny.net/pullzone/setForceSSL'
            && $request['ForceSSL'] === false);
    }

    public function test_command_marks_step_done_after_bunny_hostname_certificate_is_active(): void
    {
        $provider = new Provider([
            'vendor' => 'bunny',
            'credentials' => ['api_key' => 'bunny-api-key'],
        ]);

        $domain = new Domain(['name' => 'astero.in']);
        $certificate = $this->makeCertificateSecret();

        $website = new class extends Website
        {
            public array $stepUpdates = [];

            public ?Provider $provider = null;

            protected function getCdnProviderAttribute(): ?Provider
            {
                return $this->provider;
            }

            public function updateProvisioningStep(string $stepKey, string $message, string $status): void
            {
                $this->stepUpdates[] = [
                    'step' => $stepKey,
                    'message' => $message,
                    'status' => $status,
                ];
            }

            public function save(array $options = []): bool
            {
                return true;
            }
        };
        $website->provider = $provider;

        $website->id = 11;
        $website->domain = 'astero.in';
        $website->metadata = [
            'cdn' => [
                'Id' => 123,
                'Hostnames' => [
                    ['Value' => 'asteroin.b-cdn.net'],
                    ['Value' => 'astero.in'],
                ],
            ],
        ];
        $website->setRelation('domainRecord', $domain);
        $website->setRelation('server', new Server([
            'ip' => '89.167.78.200',
        ]));

        $domainService = $this->mock(DomainService::class);
        $domainService->shouldReceive('getBestSslCertificate')
            ->once()
            ->with($domain)
            ->andReturn($certificate);

        Http::fake(function (Request $request) {
            if ($request->method() === 'POST' && $request->url() === 'https://api.bunny.net/pullzone/123') {
                return Http::response([], 200);
            }

            if ($request->method() === 'GET' && $request->url() === 'https://api.bunny.net/pullzone/123') {
                return Http::response([
                    'Hostnames' => [
                        [
                            'Value' => 'asteroin.b-cdn.net',
                            'HasCertificate' => true,
                            'ForceSSL' => true,
                        ],
                        [
                            'Value' => 'astero.in',
                            'HasCertificate' => true,
                            'ForceSSL' => false,
                        ],
                    ],
                ], 200);
            }

            if ($request->method() === 'POST' && $request->url() === 'https://api.bunny.net/pullzone/setForceSSL') {
                return Http::response([], 200);
            }

            return Http::response([], 404);
        });

        $command = new class extends BunnyConfigureCdnSslCommand
        {
            public function __construct()
            {
                parent::__construct(app(BunnyPullZoneService::class));
            }

            public function runHandleCommand(Website $website): void
            {
                $this->handleCommand($website);
            }

            public function info($string, $verbosity = null): void {}

            public function line($string, $style = null, $verbosity = null): void {}

            public function warn($string, $verbosity = null): void {}
        };

        $command->runHandleCommand($website);

        $this->assertSame('done', $website->stepUpdates[0]['status']);
        $this->assertSame('configure_cdn_ssl', $website->stepUpdates[0]['step']);
        $this->assertTrue($website->getMetadata('cdn_ssl.force_ssl'));
        $this->assertSame([], $website->getMetadata('cdn_ssl.pending_hostnames'));

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://api.bunny.net/pullzone/setForceSSL'
            && $request['ForceSSL'] === true);
    }

    private function makeCertificateSecret(): Secret
    {
        $secret = new Secret([
            'metadata' => ['certificate' => '-----BEGIN CERTIFICATE-----demo'],
            'value' => encrypt('-----BEGIN PRIVATE KEY-----demo'),
            'expires_at' => Carbon::parse('2026-04-30 00:00:00'),
        ]);
        $secret->id = 77;

        return $secret;
    }
}
