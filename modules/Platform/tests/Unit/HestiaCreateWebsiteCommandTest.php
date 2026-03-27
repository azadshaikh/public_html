<?php

namespace Modules\Platform\Tests\Unit;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Modules\Platform\Console\HestiaCreateWebsiteCommand;
use Modules\Platform\Models\Server;
use Modules\Platform\Models\Website;
use Tests\TestCase;

class HestiaCreateWebsiteCommandTest extends TestCase
{
    public function test_command_redirects_apex_domain_to_www_when_www_primary_is_enabled(): void
    {
        $website = $this->makeWebsite('astero.in', true);
        $commands = [];

        Http::fake(function (Request $request) use (&$commands) {
            $commands[] = [
                'command' => $request['arg2'],
                'args' => [
                    $request['arg3'] ?? null,
                    $request['arg4'] ?? null,
                    $request['arg5'] ?? null,
                    $request['arg6'] ?? null,
                    $request['arg7'] ?? null,
                ],
            ];

            return Http::response([
                'status' => 'success',
                'message' => 'ok',
                'data' => [],
            ], 200);
        });

        $command = new class extends HestiaCreateWebsiteCommand
        {
            public function runHandleCommand(Website $website): void
            {
                $this->handleCommand($website);
            }

            public function info($string, $verbosity = null): void {}
        };

        $command->runHandleCommand($website);

        $this->assertCount(3, $commands);
        $this->assertSame('a-create-web-domain', $commands[0]['command']);
        $this->assertSame('v-delete-web-domain-redirect', $commands[1]['command']);
        $this->assertSame('v-add-web-domain-redirect', $commands[2]['command']);
        $this->assertSame('www.astero.in', $commands[2]['args'][2]);
        $this->assertSame('301', $commands[2]['args'][3]);
        $this->assertSame('yes', $commands[2]['args'][4]);
    }

    public function test_command_redirects_apex_domain_to_naked_domain_when_www_primary_is_disabled(): void
    {
        $website = $this->makeWebsite('astero.in', false);
        $commands = [];

        Http::fake(function (Request $request) use (&$commands) {
            $commands[] = $request['arg2'];

            return Http::response([
                'status' => 'success',
                'message' => 'ok',
                'data' => [],
            ], 200);
        });

        $command = new class extends HestiaCreateWebsiteCommand
        {
            public function runHandleCommand(Website $website): void
            {
                $this->handleCommand($website);
            }

            public function info($string, $verbosity = null): void {}
        };

        $command->runHandleCommand($website);

        Http::assertSent(fn (Request $request): bool => $request['arg2'] === 'v-add-web-domain-redirect'
            && ($request['arg5'] ?? null) === 'astero.in');

        $this->assertSame(['a-create-web-domain', 'v-delete-web-domain-redirect', 'v-add-web-domain-redirect'], $commands);
    }

    public function test_command_skips_redirect_for_subdomains(): void
    {
        $website = $this->makeWebsite('app.astero.in', true);
        $commands = [];

        Http::fake(function (Request $request) use (&$commands) {
            $commands[] = $request['arg2'];

            return Http::response([
                'status' => 'success',
                'message' => 'ok',
                'data' => [],
            ], 200);
        });

        $command = new class extends HestiaCreateWebsiteCommand
        {
            public function runHandleCommand(Website $website): void
            {
                $this->handleCommand($website);
            }

            public function info($string, $verbosity = null): void {}
        };

        $command->runHandleCommand($website);

        $this->assertSame(['a-create-web-domain', 'v-delete-web-domain-redirect'], $commands);
        Http::assertSentCount(2);
    }

    public function test_command_reconciles_existing_web_domain_before_syncing_redirect(): void
    {
        $website = $this->makeWebsite('astero.in', true);
        $commands = [];

        Http::fake(function (Request $request) use (&$commands) {
            $commands[] = $request['arg2'];

            if ($request['arg2'] === 'a-create-web-domain') {
                return Http::response([
                    'status' => 'error',
                    'code' => 4,
                    'message' => 'Object already exists',
                    'data' => [],
                ], 200);
            }

            return Http::response([
                'status' => 'success',
                'message' => 'ok',
                'data' => [],
            ], 200);
        });

        $command = new class extends HestiaCreateWebsiteCommand
        {
            public function runHandleCommand(Website $website): void
            {
                $this->handleCommand($website);
            }

            public function info($string, $verbosity = null): void {}
        };

        $command->runHandleCommand($website);

        $this->assertSame([
            'a-create-web-domain',
            'v-change-web-domain-tpl',
            'v-change-web-domain-backend-tpl',
            'v-delete-web-domain-redirect',
            'v-add-web-domain-redirect',
        ], $commands);
    }

    private function makeWebsite(string $domain, bool $isWww): Website
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

            public function save(array $options = []): bool
            {
                return true;
            }
        };

        $website->uid = 'asteroin';
        $website->domain = $domain;
        $website->plan_tier = 'basic';
        $website->is_www = $isWww;
        $website->setRelation('server', new Server([
            'ip' => '89.167.78.200',
            'access_key_id' => 'access-key',
            'access_key_secret' => 'secret-key',
        ]));

        return $website;
    }
}
