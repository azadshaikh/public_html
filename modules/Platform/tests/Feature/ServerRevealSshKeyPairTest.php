<?php

namespace Modules\Platform\Tests\Feature;

use App\Enums\Status;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Modules\Platform\Models\Secret;
use Modules\Platform\Models\Server;
use Tests\TestCase;

class ServerRevealSshKeyPairTest extends TestCase
{
    public function test_super_user_can_reveal_ssh_key_pair_with_correct_password(): void
    {
        $this->withoutMiddleware();

        $this->ensureSuperUserRoleExists();

        $superUser = User::factory()->create([
            'password' => Hash::make('secret-pass-123'),
        ]);
        $superUser->assignRole('super_user');

        /** @var Server $server */
        $server = Server::query()->create([
            'name' => 'Reveal Test Server',
            'ip' => '10.0.0.5',
            'ssh_user' => 'root',
            'ssh_port' => 22,
            'ssh_public_key' => 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIMockPublicKey',
            'status' => 'active',
        ]);

        $server->setSecret('ssh_private_key', "-----BEGIN OPENSSH PRIVATE KEY-----\nMockPrivateKey\n-----END OPENSSH PRIVATE KEY-----", 'ssh_key');

        $response = $this->actingAs($superUser)->postJson(
            route('platform.servers.ssh-key-pair.reveal', ['server' => $server->id]),
            ['password' => 'secret-pass-123']
        );

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
                'public_key' => 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIMockPublicKey',
            ]);
        $this->assertStringContainsString('MockPrivateKey', (string) $response->json('private_key'));
        $this->assertNotEmpty((string) $response->json('authorize_command'));
    }

    public function test_super_user_cannot_reveal_ssh_key_pair_with_wrong_password(): void
    {
        $this->withoutMiddleware();

        $this->ensureSuperUserRoleExists();

        $superUser = User::factory()->create([
            'password' => Hash::make('right-pass-123'),
        ]);
        $superUser->assignRole('super_user');

        /** @var Server $server */
        $server = Server::query()->create([
            'name' => 'Reveal Test Server',
            'ip' => '10.0.0.8',
            'ssh_user' => 'root',
            'ssh_port' => 22,
            'ssh_public_key' => 'ssh-ed25519 AAAAC3NzaWrongPublicKey',
            'status' => 'active',
        ]);

        $server->setSecret('ssh_private_key', "-----BEGIN OPENSSH PRIVATE KEY-----\nMockPrivateKey\n-----END OPENSSH PRIVATE KEY-----", 'ssh_key');

        $response = $this->actingAs($superUser)->postJson(
            route('platform.servers.ssh-key-pair.reveal', ['server' => $server->id]),
            ['password' => 'wrong-pass-123']
        );

        $response->assertStatus(422)->assertJsonValidationErrors(['password']);
    }

    public function test_non_super_user_cannot_reveal_ssh_key_pair_even_with_correct_password(): void
    {
        $this->withoutMiddleware();

        $normalUser = User::factory()->create([
            'password' => Hash::make('normal-pass-123'),
        ]);

        /** @var Server $server */
        $server = Server::query()->create([
            'name' => 'Reveal Test Server',
            'ip' => '10.0.0.10',
            'ssh_user' => 'root',
            'ssh_port' => 22,
            'ssh_public_key' => 'ssh-ed25519 AAAAC3NzaNonSuperPublicKey',
            'status' => 'active',
        ]);

        $server->setSecret('ssh_private_key', "-----BEGIN OPENSSH PRIVATE KEY-----\nMockPrivateKey\n-----END OPENSSH PRIVATE KEY-----", 'ssh_key');

        $response = $this->actingAs($normalUser)->postJson(
            route('platform.servers.ssh-key-pair.reveal', ['server' => $server->id]),
            ['password' => 'normal-pass-123']
        );

        $response->assertForbidden();
    }

    public function test_server_secret_reveal_requires_current_password(): void
    {
        $this->withoutMiddleware();

        $user = User::factory()->create([
            'password' => Hash::make('secret-pass-123'),
        ]);

        /** @var Server $server */
        $server = Server::query()->create([
            'name' => 'Secret Reveal Server',
            'ip' => '10.0.0.15',
            'ssh_user' => 'root',
            'ssh_port' => 22,
            'status' => 'active',
        ]);
        /** @var Secret $secret */
        $secret = $server->setSecret('hestiacp_password', 'admin-pass-xyz', 'password', 'adminxastero');

        $response = $this->actingAs($user)->postJson(
            route('platform.servers.secrets.reveal', ['server' => $server->id, 'secret' => $secret->id]),
            []
        );

        $response->assertStatus(422)->assertJsonValidationErrors(['password']);
    }

    public function test_server_secret_reveal_succeeds_with_current_password(): void
    {
        $this->withoutMiddleware();

        $user = User::factory()->create([
            'password' => Hash::make('secret-pass-123'),
        ]);

        /** @var Server $server */
        $server = Server::query()->create([
            'name' => 'Secret Reveal Server',
            'ip' => '10.0.0.16',
            'ssh_user' => 'root',
            'ssh_port' => 22,
            'status' => 'active',
        ]);
        /** @var Secret $secret */
        $secret = $server->setSecret('hestiacp_password', 'admin-pass-xyz', 'password', 'adminxastero');

        $response = $this->actingAs($user)->postJson(
            route('platform.servers.secrets.reveal', ['server' => $server->id, 'secret' => $secret->id]),
            ['password' => 'secret-pass-123']
        );

        $response->assertOk()->assertJson([
            'success' => true,
            'value' => 'admin-pass-xyz',
        ]);
    }

    public function test_generic_secret_reveal_blocks_ssh_private_key_secret(): void
    {
        $this->withoutMiddleware();

        $this->ensureSuperUserRoleExists();

        $superUser = User::factory()->create([
            'password' => Hash::make('secret-pass-123'),
        ]);
        $superUser->assignRole('super_user');

        /** @var Server $server */
        $server = Server::query()->create([
            'name' => 'Reveal Test Server',
            'ip' => '10.0.0.11',
            'ssh_user' => 'root',
            'ssh_port' => 22,
            'ssh_public_key' => 'ssh-ed25519 AAAAC3NzaBlockedRevealKey',
            'status' => 'active',
        ]);

        /** @var Secret $secret */
        $secret = $server->setSecret('ssh_private_key', "-----BEGIN OPENSSH PRIVATE KEY-----\nMockPrivateKey\n-----END OPENSSH PRIVATE KEY-----", 'ssh_key');

        $response = $this->actingAs($superUser)->postJson(
            route('platform.servers.secrets.reveal', ['server' => $server->id, 'secret' => $secret->id]),
            ['password' => 'secret-pass-123']
        );

        $response->assertForbidden();
    }

    private function ensureSuperUserRoleExists(): void
    {
        Role::query()->firstOrCreate(
            ['name' => 'super_user', 'guard_name' => 'web'],
            ['display_name' => 'Super User', 'status' => Status::ACTIVE]
        );
    }
}
