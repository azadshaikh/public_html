<?php

declare(strict_types=1);

namespace Modules\Platform\Tests\Feature;

use App\Modules\ModuleManager;
use App\Modules\Support\ModuleAutoloader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Modules\Platform\Enums\WebsiteStatus;
use Modules\Platform\Models\Domain;
use Modules\Platform\Models\Server;
use Modules\Platform\Models\Website;
use Modules\Platform\Providers\PlatformServiceProvider;
use Tests\Support\InteractsWithModuleManifest;
use Tests\TestCase;

class DnsDebugCommandTest extends TestCase
{
    use InteractsWithModuleManifest;
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->setUpModuleManifest('platform-dns-debug.json', [
            'Platform' => 'enabled',
        ]);

        $this->ensurePlatformModuleBooted();
    }

    protected function tearDown(): void
    {
        $this->tearDownModuleManifest();

        parent::tearDown();
    }

    public function test_dns_debug_command_reports_when_website_would_resume_provisioning(): void
    {
        config()->set('platform.acme_challenge.alias_domain', 'acme-challenge.in');
        config()->set('platform.acme_challenge.bunny_api_key', 'external-bunny-key');

        $server = Server::query()->create([
            'name' => 'Primary',
            'ip' => '103.180.115.15',
        ]);

        $domain = Domain::query()->create([
            'name' => 'astero.in',
            'dns_mode' => 'external',
            'dns_status' => 'pending_records',
            'metadata' => [
                'challenge_alias' => '_acme-challenge.astero.in.acme-challenge.in',
                'dns_instructions' => [
                    'mode' => 'external',
                    'records' => [
                        ['type' => 'CNAME', 'name' => 'astero.in', 'value' => 'asteroin.b-cdn.net'],
                        ['type' => 'CNAME', 'name' => 'www', 'value' => 'asteroin.b-cdn.net'],
                        ['type' => 'CNAME', 'name' => '_acme-challenge', 'value' => '_acme-challenge.astero.in.acme-challenge.in'],
                    ],
                ],
            ],
        ]);

        $website = Website::query()->create([
            'name' => 'Astero',
            'domain' => 'astero.in',
            'domain_id' => $domain->id,
            'server_id' => $server->id,
            'dns_mode' => 'external',
            'status' => WebsiteStatus::WaitingForDns->value,
            'metadata' => [
                'dns_confirmed_by_user' => true,
                'dns_confirmed_at' => now()->subMinutes(20)->toIso8601String(),
                'dns_check_count' => 4,
                'dns_last_checked_at' => now()->subMinute()->toIso8601String(),
                'provisioning_steps' => [
                    'verify_dns' => [
                        'status' => 'waiting',
                        'message' => 'Waiting for customer DNS records to propagate. (Check 4)',
                        'updated_at' => now()->subMinute()->toIso8601String(),
                    ],
                ],
            ],
        ]);

        Process::fake(function ($process) {
            $command = $process->command;

            return match (true) {
                str_contains($command, "'astero.in' 'CNAME'") => Process::result(''),
                str_contains($command, "'astero.in' 'A'") => Process::result('103.180.115.15'),
                str_contains($command, "'astero.in' 'AAAA'") => Process::result(''),
                str_contains($command, "'www.astero.in' 'CNAME'") => Process::result('asteroin.b-cdn.net'),
                str_contains($command, "'_acme-challenge.astero.in' 'CNAME'") => Process::result('_acme-challenge.astero.in.acme-challenge.in'),
                str_contains($command, "'asteroin.b-cdn.net' 'A'") => Process::result('103.180.115.15'),
                str_contains($command, "'asteroin.b-cdn.net' 'AAAA'") => Process::result(''),
                default => Process::result(''),
            };
        });

        $this->artisan('platform:dns:debug', ['website' => (string) $website->id])
            ->expectsOutputToContain('Would resume provisioning now')
            ->expectsOutputToContain('[CNAME] astero.in -> asteroin.b-cdn.net [PASS]')
            ->expectsOutputToContain('[CNAME] _acme-challenge.astero.in -> _acme-challenge.astero.in.acme-challenge.in [PASS]')
            ->assertExitCode(0);
    }

    public function test_dns_debug_command_reports_poll_gate_blockers(): void
    {
        config()->set('platform.acme_challenge.alias_domain', 'acme-challenge.in');
        config()->set('platform.acme_challenge.bunny_api_key', 'external-bunny-key');

        $server = Server::query()->create([
            'name' => 'Primary',
            'ip' => '103.180.115.15',
        ]);

        $domain = Domain::query()->create([
            'name' => 'astero.in',
            'dns_mode' => 'external',
            'dns_status' => 'pending_records',
            'metadata' => [
                'challenge_alias' => '_acme-challenge.astero.in.acme-challenge.in',
                'dns_instructions' => [
                    'mode' => 'external',
                    'records' => [
                        ['type' => 'CNAME', 'name' => 'astero.in', 'value' => 'asteroin.b-cdn.net'],
                    ],
                ],
            ],
        ]);

        $website = Website::query()->create([
            'name' => 'Astero',
            'domain' => 'astero.in',
            'domain_id' => $domain->id,
            'server_id' => $server->id,
            'dns_mode' => 'external',
            'status' => WebsiteStatus::WaitingForDns->value,
            'metadata' => [
                'dns_confirmed_by_user' => false,
                'provisioning_steps' => [
                    'verify_dns' => [
                        'status' => 'waiting',
                        'message' => 'Waiting for customer DNS records to propagate.',
                        'updated_at' => now()->subMinute()->toIso8601String(),
                    ],
                ],
            ],
        ]);

        Process::fake(function ($process) {
            $command = $process->command;

            return match (true) {
                str_contains($command, "'astero.in' 'CNAME'") => Process::result(''),
                str_contains($command, "'astero.in' 'A'") => Process::result('103.180.115.15'),
                str_contains($command, "'astero.in' 'AAAA'") => Process::result(''),
                str_contains($command, "'asteroin.b-cdn.net' 'A'") => Process::result('103.180.115.15'),
                str_contains($command, "'asteroin.b-cdn.net' 'AAAA'") => Process::result(''),
                default => Process::result(''),
            };
        });

        $this->artisan('platform:dns:debug', ['website' => (string) $website->id])
            ->expectsOutputToContain('Current blockers:')
            ->expectsOutputToContain('dns_confirmed_by_user is false')
            ->expectsOutputToContain('dns_confirmed_at is missing')
            ->assertExitCode(1);
    }

    private function ensurePlatformModuleBooted(): void
    {
        ModuleAutoloader::register(app(ModuleManager::class)->all()->all());

        if (! Route::has('platform.agencies.create')) {
            app()->register(PlatformServiceProvider::class);
        }

        if (! Route::has('platform.agencies.create')) {
            Route::middleware('web')->group(base_path('modules/Platform/routes/web.php'));
            app('router')->getRoutes()->refreshNameLookups();
            app('router')->getRoutes()->refreshActionLookups();
        }

        if (! $this->platformTablesExist()) {
            Artisan::call('migrate', [
                '--path' => base_path('modules/Platform/database/migrations'),
                '--realpath' => true,
                '--force' => true,
            ]);
        }
    }

    private function platformTablesExist(): bool
    {
        return Schema::hasTable('platform_domains')
            && Schema::hasTable('platform_servers')
            && Schema::hasTable('platform_websites');
    }
}
