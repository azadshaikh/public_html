<?php

namespace Modules\Platform\Tests\Unit;

use Exception;
use Modules\Platform\Jobs\ServerProvision;
use Modules\Platform\Models\Server;
use Modules\Platform\Services\ServerSSHService;
use Tests\TestCase;

class FakeServerForReleaseApiKey extends Server
{
    public ?string $releaseApiKeySecret = null;

    public function getSecretValue(string $key): ?string
    {
        if ($key === 'release_api_key') {
            return $this->releaseApiKeySecret;
        }

        return null;
    }
}

class FakeServerSSHServiceForReleaseApiKey extends ServerSSHService
{
    /** @var array<int, string> */
    public array $commands = [];

    public function executeCommand(Server $server, string $command, ?int $timeout = null): array
    {
        $this->commands[] = $command;

        return [
            'success' => true,
            'message' => 'ok',
            'data' => [
                'output' => 'OK',
                'exit_code' => 0,
            ],
        ];
    }
}

class TestableServerProvisionReleaseApiKey extends ServerProvision
{
    public ?string $defaultReleaseApiKeyOverride = null;

    public function callConfigureReleaseApiKey(Server $server, ServerSSHService $sshService): void
    {
        $this->configureReleaseApiKey($server, $sshService);
    }

    protected function resolveDefaultReleaseApiKey(): string
    {
        if ($this->defaultReleaseApiKeyOverride !== null) {
            return $this->defaultReleaseApiKeyOverride;
        }

        return parent::resolveDefaultReleaseApiKey();
    }
}

class ServerReleaseApiKeyOptionalFlowTest extends TestCase
{
    public function test_provision_form_marks_release_api_key_as_optional_with_env_fallback_hint(): void
    {
        $path = base_path('modules/Platform/resources/js/pages/platform/servers/components/server-wizard-provision-step.tsx');
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, 'Failed to read modules/Platform/resources/js/pages/platform/servers/components/server-wizard-provision-step.tsx');
        $this->assertStringContainsString('Release API key', $contents);
        $this->assertStringContainsString('Leave blank to use the environment default from the provisioning', $contents);
        $this->assertStringNotContainsString('Required for secured release sync during provisioning.', $contents);
    }

    public function test_server_provision_fails_release_key_step_when_no_key_is_available(): void
    {
        $server = new FakeServerForReleaseApiKey;
        $server->id = 1;

        $ssh = new FakeServerSSHServiceForReleaseApiKey;
        $job = new TestableServerProvisionReleaseApiKey($server);
        $job->defaultReleaseApiKeyOverride = '';

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Release API key is missing');

        $job->callConfigureReleaseApiKey($server, $ssh);
    }

    public function test_server_provision_uses_default_release_key_when_form_value_is_blank(): void
    {
        $server = new FakeServerForReleaseApiKey;
        $server->id = 1;

        $ssh = new FakeServerSSHServiceForReleaseApiKey;
        $job = new TestableServerProvisionReleaseApiKey($server);
        $job->defaultReleaseApiKeyOverride = 'env-fallback-release-key';

        $job->callConfigureReleaseApiKey($server, $ssh);

        $this->assertCount(1, $ssh->commands);
        $this->assertStringContainsString('/usr/local/hestia/data/astero/release_api_key', $ssh->commands[0]);
        $this->assertStringContainsString("'env-fallback-release-key'", $ssh->commands[0]);
    }
}
