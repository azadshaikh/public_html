<?php

namespace Modules\Platform\Tests\Unit;

use App\Enums\ActivityAction;
use Modules\Platform\Models\Server;
use Modules\Platform\Services\ServerAcmeSetupService;
use Modules\Platform\Services\ServerSSHService;
use Tests\TestCase;

class TestAcmeSetupServer extends Server
{
    public bool $saved = false;

    public function hasSshCredentials(): bool
    {
        return true;
    }

    public function save(array $options = []): bool
    {
        $this->saved = true;

        return true;
    }
}

class TestAcmeSetupServerSSHService extends ServerSSHService
{
    /**
     * @var array<int, array{command: string, timeout: int|null}>
     */
    public array $commands = [];

    public function executeCommand(Server $server, string $command, ?int $timeout = null): array
    {
        $this->commands[] = [
            'command' => $command,
            'timeout' => $timeout,
        ];

        return [
            'success' => true,
            'message' => 'ok',
            'data' => [
                'output' => '',
            ],
        ];
    }
}

class TestServerAcmeSetupService extends ServerAcmeSetupService
{
    /**
     * @var array<int, string>
     */
    public array $activityMessages = [];

    public function logActivity(
        $model,
        ActivityAction $action,
        string $message,
        array $extraProperties = [],
        bool $queue = false
    ): void {
        $this->activityMessages[] = $message;
    }
}

class ServerAcmeSetupServiceTest extends TestCase
{
    public function test_setup_installs_acme_and_marks_server_configured(): void
    {
        $sshService = new TestAcmeSetupServerSSHService;
        $service = new TestServerAcmeSetupService($sshService);
        $server = new TestAcmeSetupServer;
        $server->id = 7;
        $server->name = 'Hestia SG1';

        $result = $service->setup($server);
        $commands = implode("\n", array_column($sshService->commands, 'command'));

        $this->assertTrue($server->saved);
        $this->assertTrue($server->acme_configured);
        $this->assertSame('hestia-sg1@astero.net.in', $server->acme_email);
        $this->assertSame('hestia-sg1@astero.net.in', $result['acme_email']);
        $this->assertCount(9, $sshService->commands);
        $this->assertStringContainsString('useradd --system', $commands);
        $this->assertStringContainsString("email='hestia-sg1@astero.net.in'", $commands);
        $this->assertStringContainsString("-m 'hestia-sg1@astero.net.in'", $commands);
        $this->assertStringContainsString('/usr/local/hestia/data/astero/bin/a-issue-wildcard-ssl', $commands);
        $this->assertStringContainsString('ACME setup completed on server "Hestia SG1"', $result['summary']);
    }

    public function test_force_setup_only_reuploads_scripts_when_server_is_already_configured(): void
    {
        $sshService = new TestAcmeSetupServerSSHService;
        $service = new TestServerAcmeSetupService($sshService);
        $server = new TestAcmeSetupServer;
        $server->id = 8;
        $server->name = 'Ready Server';
        $server->acme_configured = true;
        $server->acme_email = 'ready-server@astero.net.in';

        $result = $service->setup($server, true);
        $commands = implode("\n", array_column($sshService->commands, 'command'));

        $this->assertFalse($server->saved);
        $this->assertCount(4, $sshService->commands);
        $this->assertArrayHasKey('reuploaded_scripts', $result);
        $this->assertStringNotContainsString('useradd --system', $commands);
        $this->assertStringNotContainsString('--register-account --server letsencrypt', $commands);
        $this->assertStringContainsString('/usr/local/hestia/data/astero/bin/a-renew-wildcard-ssl', $commands);
    }
}
