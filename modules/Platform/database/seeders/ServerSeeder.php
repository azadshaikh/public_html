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
            [
                'name' => 'Dev One - Local',
                'type' => Server::TYPE_LOCALHOST,
                'driver' => 'hestia',
                'ip' => '192.168.0.123',
                'port' => 8443,
                'fqdn' => 'devone.192.168.0.123.traefik.me',
                'access_key_id' => 'DqCG01O4uEfZtgGS0Jty',
                'access_key_secret' => 'xe55hG4kg7VkwKlrrT646M9YLR_HHLGr99Al0Q9M',
                'ssh_public_key' => 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIJuXEum2NTupUyTVUswMGKTf1nzve8zDdRwGYmACAmTJ astero-generated-key',
                'ssh_port' => 22,
                'ssh_user' => 'root',
                'current_domains' => 0,
                'max_domains' => 100,
                'monitor' => false,
                'status' => 'active',
                'provisioning_status' => Server::PROVISIONING_STATUS_READY,
                'metadata' => [
                    'server_os' => 'Ubuntu 24.04',
                    'server_cpu' => 'Intel(R) Core(TM) i3-10100 CPU @ 3.60GHz',
                    'server_ccore' => '2',
                    'server_ram' => 4793,
                    'server_storage' => 59,
                    'hestia_version' => '1.9.4',
                ],
                'secrets' => [
                    'ssh_private_key' => "-----BEGIN OPENSSH PRIVATE KEY-----\nb3BlbnNzaC1rZXktdjEAAAAABG5vbmUAAAAEbm9uZQAAAAAAAAABAAAAMwAAAAtzc2gtZW\nQyNTUxOQAAACCblxLptjU7qVMk1VLMDBik39Z873vMw3UcBmJgAgJkyQAAAJgIq9X9CKvV\n/QAAAAtzc2gtZWQyNTUxOQAAACCblxLptjU7qVMk1VLMDBik39Z873vMw3UcBmJgAgJkyQ\nAAAECHWDBxxSJ2Mt3h7hhUTKCCw5heiebYluNSVtGJ2bUTbpuXEum2NTupUyTVUswMGKTf\n1nzve8zDdRwGYmACAmTJAAAAFGFzdGVyby1nZW5lcmF0ZWQta2V5AQ==\n-----END OPENSSH PRIVATE KEY-----",
                    'release_api_key' => 'rJ7vQ9nM2xK8pW4tYcL6sF3uH1dN5bZ8eA0kV7qT2mP9wX4gC6yR1uJ8nD3fS5h',
                ],
            ],
            [
                'name' => 'Dev Two - Local',
                'type' => Server::TYPE_LOCALHOST,
                'driver' => 'hestia',
                'ip' => '192.168.0.150',
                'port' => 8443,
                'fqdn' => 'devtwo.192.168.0.150.traefik.me',
                'access_key_id' => 'KW6qTV8C1zwIK82N8sn8',
                'access_key_secret' => 'k-7=hHU3pLm7oqFTEejJeC6bRw1s59=cTM3wMrki',
                'ssh_public_key' => 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIPykncrL0fBdwi+RScoBIlARjlJQciv14r971RPwJRaw astero-generated-key',
                'ssh_port' => 22,
                'ssh_user' => 'root',
                'current_domains' => 0,
                'max_domains' => 100,
                'monitor' => false,
                'status' => 'active',
                'provisioning_status' => Server::PROVISIONING_STATUS_READY,
                'metadata' => [
                    'server_os' => 'Ubuntu 24.04',
                    'server_cpu' => 'Intel(R) Core(TM) i3-10100 CPU @ 3.60GHz',
                    'server_ccore' => '2',
                    'server_ram' => 4464,
                    'server_storage' => 19,
                    'hestia_version' => '1.9.4',
                ],
                'secrets' => [
                    'ssh_private_key' => "-----BEGIN OPENSSH PRIVATE KEY-----\nb3BlbnNzaC1rZXktdjEAAAAABG5vbmUAAAAEbm9uZQAAAAAAAAABAAAAMwAAAAtzc2gtZW\nQyNTUxOQAAACD8pJ3Ky9HwXcIvkUnKASJQEY5SUHIr9eK/e9UT8CUWsAAAAJj/bXfq/213\n6gAAAAtzc2gtZWQyNTUxOQAAACD8pJ3Ky9HwXcIvkUnKASJQEY5SUHIr9eK/e9UT8CUWsA\nAAAEBsmDPfJVWp0yxJqNE0wDIaiQ5QOWmyEcpBY59pY+4IffykncrL0fBdwi+RScoBIlAR\njlJQciv14r971RPwJRawAAAAFGFzdGVyby1nZW5lcmF0ZWQta2V5AQ==\n-----END OPENSSH PRIVATE KEY-----",
                    'release_api_key' => 'rJ7vQ9nM2xK8pW4tYcL6sF3uH1dN5bZ8eA0kV7qT2mP9wX4gC6yR1uJ8nD3fS5h',
                ],
            ],
        ];
    }
}
