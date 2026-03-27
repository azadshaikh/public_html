<?php

namespace Modules\Platform\Tests\Feature;

use Mockery;
use Mockery\MockInterface;
use Modules\Platform\Models\Server;
use Modules\Platform\Models\Website;
use Modules\Platform\Services\ServerSSHService;
use Tests\TestCase;

class WebsiteLaravelLogTest extends TestCase
{
    public function test_it_returns_remote_website_laravel_log_details(): void
    {
        $this->withoutMiddleware();

        /** @var Server $server */
        $server = Server::query()->create([
            'name' => 'Website Log Server',
            'ip' => '10.0.0.30',
            'ssh_user' => 'root',
            'ssh_port' => 22,
            'status' => 'active',
        ]);

        /** @var Website $website */
        $website = Website::query()->create([
            'uid' => 'WS00001',
            'name' => 'Astero',
            'domain' => 'astero.in',
            'server_id' => $server->id,
            'status' => 'active',
        ]);

        /** @var ServerSSHService&MockInterface $sshService */
        $sshService = Mockery::mock(ServerSSHService::class);
        $sshService->shouldReceive('executeCommand')
            ->once()
            ->withArgs(function (Server $targetServer, string $command, int $timeout) use ($server): bool {
                return $targetServer->is($server)
                    && str_contains($command, '/home/${USERNAME}/web/${DOMAIN}/public_html/current/storage/logs/laravel.log')
                    && str_contains($command, 'tail -n 400')
                    && $timeout === 30;
            })
            ->andReturn([
                'success' => true,
                'data' => [
                    'output' => "__ASTERO_EXISTS__=1\n__ASTERO_PATH__=/home/WS00001/web/astero.in/public_html/current/storage/logs/laravel.log\n__ASTERO_SIZE__=64\n__ASTERO_MTIME__=1774576800\n__ASTERO_CONTENT_START__\n[2026-03-27] local.INFO: first line\n[2026-03-27] local.ERROR: second line\n",
                ],
            ]);

        $this->app->instance(ServerSSHService::class, $sshService);

        $this->getJson(route('platform.websites.laravel-log.show', ['website' => $website->id]))
            ->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'path' => '/home/WS00001/web/astero.in/public_html/current/storage/logs/laravel.log',
                    'exists' => true,
                    'size_bytes' => 64,
                    'tail_lines' => 400,
                    'content' => "[2026-03-27] local.INFO: first line\n[2026-03-27] local.ERROR: second line",
                ],
            ]);
    }

    public function test_it_can_clear_remote_website_laravel_log(): void
    {
        $this->withoutMiddleware();

        /** @var Server $server */
        $server = Server::query()->create([
            'name' => 'Website Log Clear Server',
            'ip' => '10.0.0.31',
            'ssh_user' => 'root',
            'ssh_port' => 22,
            'status' => 'active',
        ]);

        /** @var Website $website */
        $website = Website::query()->create([
            'uid' => 'WS00002',
            'name' => 'Astero',
            'domain' => 'astero.in',
            'server_id' => $server->id,
            'status' => 'active',
        ]);

        /** @var ServerSSHService&MockInterface $sshService */
        $sshService = Mockery::mock(ServerSSHService::class);
        $sshService->shouldReceive('executeCommand')
            ->once()
            ->withArgs(function (Server $targetServer, string $command, int $timeout) use ($server): bool {
                return $targetServer->is($server)
                    && str_contains($command, ': > "$LOG"')
                    && $timeout === 30;
            })
            ->andReturn([
                'success' => true,
                'message' => 'Command executed successfully',
            ]);

        $this->app->instance(ServerSSHService::class, $sshService);

        $this->deleteJson(route('platform.websites.laravel-log.clear', ['website' => $website->id]))
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Website Laravel log cleared successfully.',
            ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}
