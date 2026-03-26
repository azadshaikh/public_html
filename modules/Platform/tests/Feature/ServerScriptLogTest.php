<?php

namespace Modules\Platform\Tests\Feature;

use Mockery;
use Mockery\MockInterface;
use Modules\Platform\Models\Server;
use Modules\Platform\Services\ServerSSHService;
use Tests\TestCase;

class ServerScriptLogTest extends TestCase
{
    public function test_it_returns_remote_astero_script_log_details(): void
    {
        $this->withoutMiddleware();

        /** @var Server $server */
        $server = Server::query()->create([
            'name' => 'Log Test Server',
            'ip' => '10.0.0.20',
            'ssh_user' => 'root',
            'ssh_port' => 22,
            'status' => 'active',
        ]);

        /** @var ServerSSHService&MockInterface $sshService */
        $sshService = Mockery::mock(ServerSSHService::class);
        $sshService->shouldReceive('executeCommand')
            ->once()
            ->withArgs(function (Server $targetServer, string $command, int $timeout) use ($server): bool {
                return $targetServer->is($server)
                    && str_contains($command, 'tail -n 400')
                    && $timeout === 30;
            })
            ->andReturn([
                'success' => true,
                'data' => [
                    'output' => "__ASTERO_EXISTS__=1\n__ASTERO_SIZE__=42\n__ASTERO_MTIME__=1774576800\n__ASTERO_CONTENT_START__\nline one\nline two\n",
                ],
            ]);

        $this->app->instance(ServerSSHService::class, $sshService);

        $this->getJson(route('platform.servers.script-log.show', ['server' => $server->id]))
            ->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'path' => '/usr/local/hestia/data/astero/logs/astero-scripts.log',
                    'exists' => true,
                    'size_bytes' => 42,
                    'tail_lines' => 400,
                    'content' => "line one\nline two",
                ],
            ]);
    }

    public function test_it_can_clear_remote_astero_script_log(): void
    {
        $this->withoutMiddleware();

        /** @var Server $server */
        $server = Server::query()->create([
            'name' => 'Log Clear Server',
            'ip' => '10.0.0.21',
            'ssh_user' => 'root',
            'ssh_port' => 22,
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

        $this->deleteJson(route('platform.servers.script-log.clear', ['server' => $server->id]))
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Astero scripts log cleared successfully.',
            ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}
