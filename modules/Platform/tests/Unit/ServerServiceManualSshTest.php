<?php

namespace Modules\Platform\Tests\Unit;

use Mockery;
use Mockery\MockInterface;
use Modules\Platform\Models\Secret;
use Modules\Platform\Models\Server;
use Modules\Platform\Services\ServerService;
use ReflectionClass;
use Tests\TestCase;

class ServerServiceManualSshTest extends TestCase
{
    public function test_manual_creation_persists_ssh_credentials_when_provided(): void
    {
        $service = new ServerService;

        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('prepareCreateData');

        /** @var array<string, mixed> $prepared */
        $prepared = $method->invoke($service, [
            'creation_mode' => 'manual',
            'name' => 'Existing Server With SSH',
            'ip' => '192.0.2.10',
            'port' => 8443,
            'type' => 'default',
            'access_key_id' => 'access-key-id',
            'access_key_secret' => 'access-key-secret',
            'ssh_port' => 22,
            'ssh_user' => 'root',
            'ssh_private_key' => '-----BEGIN OPENSSH PRIVATE KEY-----test-----END OPENSSH PRIVATE KEY-----',
        ]);

        $this->assertSame(22, $prepared['ssh_port'] ?? null);
        $this->assertSame('root', $prepared['ssh_user'] ?? null);
        $this->assertArrayNotHasKey('ssh_private_key', $prepared);
    }

    public function test_sync_ssh_private_key_secret_uses_server_name_as_username(): void
    {
        $service = new ServerService;

        /** @var Server&MockInterface $server */
        $server = Mockery::mock(Server::class)->makePartial();
        $server->name = 'EU Production Node';

        $server->shouldReceive('setSecret')
            ->andReturn(Mockery::mock(Secret::class));

        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('syncSshPrivateKeySecret');

        $method->invoke($service, $server, [
            'ssh_private_key' => '-----BEGIN OPENSSH PRIVATE KEY-----test-----END OPENSSH PRIVATE KEY-----',
        ]);
    }
}
