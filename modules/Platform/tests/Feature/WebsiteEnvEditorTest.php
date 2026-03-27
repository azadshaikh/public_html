<?php

namespace Modules\Platform\Tests\Feature;

use Mockery;
use Mockery\MockInterface;
use Modules\Platform\Models\Server;
use Modules\Platform\Models\Website;
use Modules\Platform\Services\ServerSSHService;
use Tests\TestCase;

class WebsiteEnvEditorTest extends TestCase
{
    public function test_it_returns_remote_website_env_details(): void
    {
        $this->withoutMiddleware();

        /** @var Server $server */
        $server = Server::query()->create([
            'name' => 'Website Env Server',
            'ip' => '10.0.0.40',
            'ssh_user' => 'root',
            'ssh_port' => 22,
            'status' => 'active',
        ]);

        /** @var Website $website */
        $website = Website::query()->create([
            'uid' => 'WS00010',
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
                    && str_contains($command, '/home/${USERNAME}/web/${DOMAIN}/public_html/shared/.env')
                    && $timeout === 30;
            })
            ->andReturn([
                'success' => true,
                'data' => [
                    'output' => "__ASTERO_EXISTS__=1\n__ASTERO_PATH__=/home/WS00010/web/astero.in/public_html/shared/.env\n__ASTERO_SIZE__=42\n__ASTERO_MTIME__=1774576800\n__ASTERO_CONTENT_START__\nAPP_NAME=Astero\nAPP_ENV=production\n",
                ],
            ]);

        $this->app->instance(ServerSSHService::class, $sshService);

        $this->getJson(route('platform.websites.env.show', ['website' => $website->id]))
            ->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'path' => '/home/WS00010/web/astero.in/public_html/shared/.env',
                    'exists' => true,
                    'size_bytes' => 42,
                    'line_count' => 2,
                    'content' => "APP_NAME=Astero\nAPP_ENV=production",
                ],
            ]);
    }

    public function test_it_can_update_remote_website_env_file(): void
    {
        $this->withoutMiddleware();

        /** @var Server $server */
        $server = Server::query()->create([
            'name' => 'Website Env Write Server',
            'ip' => '10.0.0.41',
            'ssh_user' => 'root',
            'ssh_port' => 22,
            'status' => 'active',
        ]);

        /** @var Website $website */
        $website = Website::query()->create([
            'uid' => 'WS00011',
            'name' => 'Astero',
            'domain' => 'astero.in',
            'server_id' => $server->id,
            'status' => 'active',
        ]);

        $payload = "APP_NAME=Astero\nAPP_ENV=production\n";

        /** @var ServerSSHService&MockInterface $sshService */
        $sshService = Mockery::mock(ServerSSHService::class);
        $sshService->shouldReceive('executeCommand')
            ->once()
            ->withArgs(function (Server $targetServer, string $command, int $timeout) use ($payload, $server): bool {
                return $targetServer->is($server)
                    && str_contains($command, 'SHARED_DIR="/home/${USERNAME}/web/${DOMAIN}/public_html/shared"')
                    && str_contains($command, 'BACKUP_DIR="$SHARED_DIR/backups/env"')
                    && str_contains($command, escapeshellarg(base64_encode($payload)))
                    && $timeout === 30;
            })
            ->andReturn([
                'success' => true,
                'message' => 'Command executed successfully',
            ]);

        $this->app->instance(ServerSSHService::class, $sshService);

        $this->putJson(route('platform.websites.env.update', ['website' => $website->id]), [
            'content' => $payload,
        ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Website environment file updated successfully. Run recache if the application is using cached configuration.',
            ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}
