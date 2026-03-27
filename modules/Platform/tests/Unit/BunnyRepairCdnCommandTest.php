<?php

namespace Modules\Platform\Tests\Unit;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Modules\Platform\Console\BunnyRepairCdnCommand;
use Modules\Platform\Models\Provider;
use Modules\Platform\Models\Server;
use Modules\Platform\Models\Website;
use Modules\Platform\Services\BunnyPullZoneService;
use Tests\TestCase;

class BunnyRepairCdnCommandTest extends TestCase
{
    public function test_command_repairs_origin_settings_and_purges_cache(): void
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
            public ?Provider $provider = null;

            protected function getCdnProviderAttribute(): ?Provider
            {
                return $this->provider;
            }

            public function save(array $options = []): bool
            {
                return true;
            }
        };

        $website->provider = $provider;
        $website->domain = 'astero.in';
        $website->metadata = [
            'cdn' => [
                'Id' => 123,
            ],
        ];
        $website->setRelation('server', $server);

        Http::fake(function (Request $request) {
            if ($request->method() === 'POST' && $request->url() === 'https://api.bunny.net/pullzone/123') {
                return Http::response([], 200);
            }

            if ($request->method() === 'POST' && $request->url() === 'https://api.bunny.net/pullzone/123/purgeCache') {
                return Http::response([], 200);
            }

            if ($request->method() === 'GET' && $request->url() === 'https://api.bunny.net/pullzone/123') {
                return Http::response([
                    'Id' => 123,
                    'OriginUrl' => 'https://89.167.78.200',
                    'OriginHostHeader' => 'astero.in',
                    'AddHostHeader' => false,
                    'FollowRedirects' => false,
                    'DisableCookies' => false,
                    'EnableAutoSSL' => true,
                    'Hostnames' => [
                        [
                            'Value' => 'astero.in',
                            'HasCertificate' => true,
                            'ForceSSL' => true,
                        ],
                    ],
                ], 200);
            }

            return Http::response([], 404);
        });

        $command = new class extends BunnyRepairCdnCommand
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

            public function option($key = null): mixed
            {
                return $key === 'purge' ? true : parent::option($key);
            }
        };

        $command->runHandleCommand($website);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://api.bunny.net/pullzone/123'
            && $request['OriginHostHeader'] === 'astero.in'
            && $request['AddHostHeader'] === false
            && $request['FollowRedirects'] === false
            && $request['DisableCookies'] === false);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://api.bunny.net/pullzone/123/purgeCache');

        $this->assertSame('astero.in', $website->getMetadata('cdn.OriginHostHeader'));
        $this->assertFalse($website->getMetadata('cdn.AddHostHeader'));
        $this->assertFalse($website->getMetadata('cdn.DisableCookies'));
    }
}
