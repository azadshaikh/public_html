<?php

namespace Modules\Platform\Tests\Feature;

use Mockery;
use Modules\Platform\Models\Server;
use Modules\Platform\Services\ServerSSHService;
use Tests\TestCase;

class ServerTestConnectionDraftCredentialsTest extends TestCase
{
    public function test_edit_test_connection_uses_unsaved_draft_credentials(): void
    {
        $this->withoutMiddleware();

        /** @var Server $persistedServer */
        $persistedServer = Server::query()->create([
            'name' => 'Persisted Server',
            'ip' => '10.0.0.10',
            'ssh_user' => 'root',
            'ssh_port' => 22,
            'status' => 'active',
        ]);
        $persistedServer->setSecret('ssh_private_key', 'persisted-private-key', 'ssh_key');

        $mock = Mockery::mock(ServerSSHService::class);
        $mock->shouldReceive('testConnection')
            ->andReturn([
                'success' => true,
                'data' => ['os_info' => 'Ubuntu 24.04'],
            ]);

        $this->app->instance(ServerSSHService::class, $mock);

        $response = $this->postJson(route('platform.servers.test-connection', ['server' => $persistedServer->id]), [
            'ip' => '203.0.113.20',
            'ssh_port' => 2222,
            'ssh_user' => 'root',
            'ssh_private_key' => 'draft-private-key',
        ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'message' => 'SSH connection successful!',
            ]);
    }

    public function test_edit_test_connection_requires_private_key_when_using_draft_credentials(): void
    {
        $this->withoutMiddleware();

        /** @var Server $persistedServer */
        $persistedServer = Server::query()->create([
            'name' => 'Persisted Server',
            'ip' => '10.0.0.10',
            'ssh_user' => 'root',
            'ssh_port' => 22,
            'status' => 'active',
        ]);
        $persistedServer->setSecret('ssh_private_key', 'persisted-private-key', 'ssh_key');

        $response = $this->postJson(route('platform.servers.test-connection', ['server' => $persistedServer->id]), [
            'ip' => '203.0.113.20',
            'ssh_port' => 2222,
            'ssh_user' => 'root',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['ssh_private_key']);
    }
}
