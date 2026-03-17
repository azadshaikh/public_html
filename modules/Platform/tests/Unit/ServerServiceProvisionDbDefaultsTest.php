<?php

namespace Modules\Platform\Tests\Unit;

use Modules\Platform\Services\ServerService;
use ReflectionClass;
use Tests\TestCase;

class ServerServiceProvisionDbDefaultsTest extends TestCase
{
    public function test_provision_creation_defaults_to_postgresql_when_db_fields_are_absent(): void
    {
        $service = new ServerService;

        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('prepareCreateData');

        /** @var array<string, mixed> $prepared */
        $prepared = $method->invoke($service, [
            'creation_mode' => 'provision',
            'name' => 'Provision Server',
            'ip' => '192.0.2.10',
            'fqdn' => 'server.example.test',
            'ssh_port' => 22,
            'ssh_user' => 'root',
            'ssh_private_key' => '-----BEGIN OPENSSH PRIVATE KEY-----test-----END OPENSSH PRIVATE KEY-----',
        ]);

        $installOptions = $prepared['metadata']['install_options'] ?? null;
        $this->assertIsArray($installOptions);

        $this->assertFalse($installOptions['mysql'] ?? true);
        $this->assertTrue($installOptions['postgresql'] ?? false);
        $this->assertFalse($installOptions['mysql8'] ?? true);
        $this->assertFalse($installOptions['multiphp'] ?? true);
        $this->assertSame('8.4', $installOptions['multiphp_versions'] ?? null);
        $this->assertFalse($installOptions['force'] ?? true);
    }

    public function test_provision_creation_honors_multiphp_when_explicitly_enabled(): void
    {
        $service = new ServerService;

        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('prepareCreateData');

        /** @var array<string, mixed> $prepared */
        $prepared = $method->invoke($service, [
            'creation_mode' => 'provision',
            'name' => 'Provision Server',
            'ip' => '192.0.2.11',
            'fqdn' => 'server-multiphp.example.test',
            'ssh_port' => 22,
            'ssh_user' => 'root',
            'ssh_private_key' => '-----BEGIN OPENSSH PRIVATE KEY-----test-----END OPENSSH PRIVATE KEY-----',
            'install_multiphp' => '1',
            'install_multiphp_versions' => '8.3,8.4',
        ]);

        $installOptions = $prepared['metadata']['install_options'] ?? null;
        $this->assertIsArray($installOptions);
        $this->assertTrue($installOptions['multiphp'] ?? false);
        $this->assertSame('8.3,8.4', $installOptions['multiphp_versions'] ?? null);
        $this->assertFalse($installOptions['force'] ?? true);
    }

    public function test_provision_creation_honors_force_when_explicitly_enabled(): void
    {
        $service = new ServerService;

        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('prepareCreateData');

        /** @var array<string, mixed> $prepared */
        $prepared = $method->invoke($service, [
            'creation_mode' => 'provision',
            'name' => 'Provision Server',
            'ip' => '192.0.2.12',
            'fqdn' => 'server-force.example.test',
            'ssh_port' => 22,
            'ssh_user' => 'root',
            'ssh_private_key' => '-----BEGIN OPENSSH PRIVATE KEY-----test-----END OPENSSH PRIVATE KEY-----',
            'install_force' => '1',
        ]);

        $installOptions = $prepared['metadata']['install_options'] ?? null;
        $this->assertIsArray($installOptions);
        $this->assertTrue($installOptions['force'] ?? false);
    }
}
