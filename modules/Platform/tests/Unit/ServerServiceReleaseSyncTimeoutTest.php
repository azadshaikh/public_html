<?php

namespace Modules\Platform\Tests\Unit;

use Modules\Platform\Models\Server;
use Modules\Platform\Services\ServerService;
use Tests\TestCase;

class FakeServerServiceForReleaseSyncTimeout extends ServerService
{
    public array $calls = [];

    protected function executeHestiaCommand(string $command, Server $server, array $args = [], int $timeout = 60): array
    {
        $this->calls[] = [
            'command' => $command,
            'args' => $args,
            'timeout' => $timeout,
        ];

        return [
            'success' => true,
            'message' => 'ok',
            'data' => [],
        ];
    }
}

class ServerServiceReleaseSyncTimeoutTest extends TestCase
{
    public function test_update_local_releases_uses_extended_timeout_for_release_sync(): void
    {
        $server = new Server;
        $server->ip = '192.168.0.100';
        $server->access_key_id = 'access-key';
        $server->access_key_secret = 'secret-key';

        $service = new FakeServerServiceForReleaseSyncTimeout;

        $result = $service->updateLocalReleases($server);

        $this->assertTrue($result['success']);
        $this->assertCount(1, $service->calls);
        $this->assertSame('a-sync-releases', $service->calls[0]['command']);
        $this->assertSame(['application', 'main', '--set-active'], $service->calls[0]['args']);
        $this->assertSame(600, $service->calls[0]['timeout']);
    }
}
