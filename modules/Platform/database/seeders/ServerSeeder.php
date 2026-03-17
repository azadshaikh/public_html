<?php

namespace Modules\Platform\Database\Seeders;

use Modules\Platform\Models\Provider;
use Modules\Platform\Models\Server;

class ServerSeeder extends PlatformSeeder
{
    public function run(): void
    {
        $auditColumns = $this->auditColumns();

        /** @var Provider $localProvider */
        $localProvider = Provider::query()->firstOrCreate(
            [
                'type' => Provider::TYPE_SERVER,
                'vendor' => 'local',
            ],
            [
                'name' => 'Local Server',
                'status' => 'active',
                ...$auditColumns,
            ],
        );

        foreach ($this->serverDefinitions() as $definition) {
            $secretValues = $definition['secrets'];
            unset($definition['secrets']);

            /** @var Server $server */
            $server = Server::query()->updateOrCreate(
                ['ip' => $definition['ip']],
                [
                    ...$definition,
                    ...$auditColumns,
                ],
            );

            if (! $server->uid) {
                $server->forceFill([
                    'uid' => Server::generateServerCodeFromId((int) $server->getKey()),
                ])->save();
            }

            $server->assignProvider($localProvider, true);
            $server->setSecret('ssh_private_key', $secretValues['ssh_private_key'], 'ssh_key');
            $server->setSecret('release_api_key', $secretValues['release_api_key'], 'api_key');

            $this->writeInfo('Seeded Platform server: '.$server->name);
        }
    }

    /**
     * @return array<int, array<string, array<string, string>|bool|int|string|null>>
     */
    private function serverDefinitions(): array
    {
        return [
            [
                'name' => 'Platform Demo Server One',
                'type' => Server::TYPE_LOCALHOST,
                'driver' => 'hestia',
                'ip' => '192.0.2.10',
                'port' => 8443,
                'fqdn' => 'platform-demo-1.example.test',
                'access_key_id' => 'demo-access-key-1',
                'access_key_secret' => 'demo-access-secret-1',
                'ssh_public_key' => 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIDemoServerOneKey platform-demo',
                'ssh_port' => 22,
                'ssh_user' => 'root',
                'current_domains' => 0,
                'max_domains' => 100,
                'monitor' => false,
                'status' => 'active',
                'provisioning_status' => Server::PROVISIONING_STATUS_READY,
                'metadata' => [
                    'server_os' => 'Ubuntu 24.04',
                    'server_cpu' => '4 vCPU',
                    'server_ccore' => '4',
                    'server_ram' => 8192,
                    'server_storage' => 160,
                    'hestia_version' => '1.9.4',
                ],
                'secrets' => [
                    'ssh_private_key' => 'platform-demo-private-key-1',
                    'release_api_key' => 'platform-demo-release-key-1',
                ],
            ],
            [
                'name' => 'Platform Demo Server Two',
                'type' => Server::TYPE_LOCALHOST,
                'driver' => 'hestia',
                'ip' => '198.51.100.20',
                'port' => 8443,
                'fqdn' => 'platform-demo-2.example.test',
                'access_key_id' => 'demo-access-key-2',
                'access_key_secret' => 'demo-access-secret-2',
                'ssh_public_key' => 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIDemoServerTwoKey platform-demo',
                'ssh_port' => 22,
                'ssh_user' => 'root',
                'current_domains' => 0,
                'max_domains' => 100,
                'monitor' => false,
                'status' => 'active',
                'provisioning_status' => Server::PROVISIONING_STATUS_READY,
                'metadata' => [
                    'server_os' => 'Ubuntu 24.04',
                    'server_cpu' => '8 vCPU',
                    'server_ccore' => '8',
                    'server_ram' => 16384,
                    'server_storage' => 320,
                    'hestia_version' => '1.9.4',
                ],
                'secrets' => [
                    'ssh_private_key' => 'platform-demo-private-key-2',
                    'release_api_key' => 'platform-demo-release-key-2',
                ],
            ],
        ];
    }
}
