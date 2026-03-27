<?php

namespace Modules\Platform\Tests\Unit;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Modules\Platform\Console\BunnySetupCdnCommand;
use Modules\Platform\Models\Provider;
use Modules\Platform\Models\Server;
use Modules\Platform\Models\Website;
use Modules\Platform\Services\BunnyPullZoneService;
use Tests\TestCase;

class BunnySetupCdnCommandTest extends TestCase
{
    public function test_command_persists_ip_origin_with_fixed_host_header(): void
    {
        $provider = new Provider([
            'vendor' => 'bunny',
            'credentials' => ['api_key' => 'bunny-api-key'],
        ]);

        $server = new Server([
            'ip' => '89.167.78.200',
        ]);

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
        $website->uid = 'asteroin';
        $website->domain = 'astero.in';
        $website->is_www = true;
        $website->setRelation('server', $server);

        Http::fake(function (Request $request) {
            if ($request->method() === 'POST' && $request->url() === 'https://api.bunny.net/pullzone') {
                return Http::response([
                    'Id' => 123,
                ], 200);
            }

            if ($request->method() === 'POST' && $request->url() === 'https://api.bunny.net/pullzone/123') {
                return Http::response([], 200);
            }

            if ($request->method() === 'POST' && $request->url() === 'https://api.bunny.net/pullzone/addHostname') {
                return Http::response([], 200);
            }

            if ($request->method() === 'GET' && $request->url() === 'https://api.bunny.net/pullzone/123') {
                return Http::response([
                    'Id' => 123,
                    'OriginUrl' => 'https://89.167.78.200',
                    'OriginHostHeader' => 'astero.in',
                    'AddHostHeader' => false,
                    'FollowRedirects' => false,
                    'Hostnames' => [
                        ['Value' => 'astero.in'],
                        ['Value' => 'www.astero.in'],
                    ],
                ], 200);
            }

            return Http::response([], 404);
        });

        $command = new class extends BunnySetupCdnCommand
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

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://api.bunny.net/pullzone'
            && $request['OriginUrl'] === 'https://89.167.78.200'
            && $request['OriginHostHeader'] === 'astero.in'
            && $request['AddHostHeader'] === false
            && $request['FollowRedirects'] === false
            && $request['EnableAutoSSL'] === true);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://api.bunny.net/pullzone/123'
            && $request['OriginUrl'] === 'https://89.167.78.200'
            && $request['OriginHostHeader'] === 'astero.in'
            && $request['AddHostHeader'] === false
            && $request['FollowRedirects'] === false
            && $request['EnableAutoSSL'] === true);

        $this->assertSame('done', $website->stepUpdates[0]['status']);
        $this->assertSame('setup_bunny_cdn', $website->stepUpdates[0]['step']);
        $this->assertSame('astero.in', $website->getMetadata('cdn.OriginHostHeader'));
        $this->assertFalse($website->getMetadata('cdn.AddHostHeader'));
    }
}
