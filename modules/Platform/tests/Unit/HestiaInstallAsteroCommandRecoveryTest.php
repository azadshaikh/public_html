<?php

namespace Modules\Platform\Tests\Unit;

use App\Enums\ActivityAction;
use Exception;
use Modules\Platform\Console\HestiaInstallAsteroCommand;
use Modules\Platform\Models\Server;
use Modules\Platform\Models\Website;
use PHPUnit\Framework\TestCase;

class TestableHestiaInstallAsteroCommand extends HestiaInstallAsteroCommand
{
    public array $hestiaResponses = [];

    public array $hestiaCalls = [];

    public array $infos = [];

    public array $warnings = [];

    public function runHandleCommand(Website $website): void
    {
        $this->handleCommand($website);
    }

    protected function executeHestiaCommand(string $command, Website $website, array $args): array
    {
        $this->hestiaCalls[] = [
            'command' => $command,
            'args' => $args,
        ];

        return array_shift($this->hestiaResponses) ?? [
            'success' => true,
            'message' => 'Command executed successfully',
            'data' => [],
            'code' => 0,
        ];
    }

    protected function prepareInstallData(Website $website): array
    {
        return [
            'owner_first_name' => 'Owner',
            'owner_last_name' => 'User',
            'owner_email' => 'owner@example.test',
            'owner_password' => 'owner-pass',
            'super_user_email' => 'su@example.test',
            'super_user_password' => 'su-pass',
            'theme_id' => '1',
        ];
    }

    protected function pauseBeforeInstallRetry(): void
    {
        // No-op for tests.
    }

    public function info($string, $verbosity = null): void
    {
        $this->infos[] = (string) $string;
    }

    public function warn($string, $verbosity = null): void
    {
        $this->warnings[] = (string) $string;
    }

    public function logActivity(
        $model,
        ActivityAction $action,
        string $message,
        array $extraProperties = [],
        bool $queue = false
    ): void {}
}

class FakeWebsiteForInstallRecovery extends Website
{
    public ?array $databaseSecret = null;

    public array $stepUpdates = [];

    public bool $refreshCalled = false;

    public function refresh()
    {
        $this->refreshCalled = true;

        return $this;
    }

    public function getSecret(string $key): ?array
    {
        if ($key === 'database_password') {
            return $this->databaseSecret;
        }

        return null;
    }

    public function updateProvisioningStep(string $stepKey, string $message, string $status): void
    {
        $this->stepUpdates[] = [
            'step' => $stepKey,
            'message' => $message,
            'status' => $status,
        ];
    }
}

class HestiaInstallAsteroCommandRecoveryTest extends TestCase
{
    public function test_it_repairs_database_credentials_and_retries_install_on_auth_failure(): void
    {
        $server = new Server;
        $server->id = 1;

        $website = new FakeWebsiteForInstallRecovery;
        $website->domain = 'repair-auth.test';
        $website->website_username = 'BS0018';
        $website->db_name = 'bs0018_db';
        $website->databaseSecret = [
            'username' => 'bs0018_db_user',
            'value' => 'RepairPass123',
        ];
        $website->setRelation('server', $server);

        $command = new TestableHestiaInstallAsteroCommand;
        $command->hestiaResponses = [
            [
                'success' => false,
                'message' => 'Artisan install failed: SQLSTATE[08006] [7] FATAL: password authentication failed for user "bs0018_db_user"',
                'data' => [],
                'code' => 216,
            ],
            [
                'success' => true,
                'message' => 'Database password updated',
                'data' => [],
                'code' => 0,
            ],
            [
                'success' => true,
                'message' => 'Install completed',
                'data' => [],
                'code' => 0,
            ],
        ];

        $command->runHandleCommand($website);

        $this->assertCount(3, $command->hestiaCalls);
        $this->assertSame('a-install-astero', $command->hestiaCalls[0]['command']);
        $this->assertSame('v-change-database-password', $command->hestiaCalls[1]['command']);
        $this->assertSame('a-install-astero', $command->hestiaCalls[2]['command']);
        $this->assertSame('bs0018_db', $command->hestiaCalls[1]['args']['arg2']);
        $this->assertSame('RepairPass123', $command->hestiaCalls[1]['args']['arg3']);
        $this->assertTrue($website->refreshCalled);
        $this->assertCount(1, $website->stepUpdates);
        $this->assertSame('done', $website->stepUpdates[0]['status']);
    }

    public function test_it_does_not_attempt_database_repair_for_non_auth_install_failures(): void
    {
        $server = new Server;
        $server->id = 2;

        $website = new FakeWebsiteForInstallRecovery;
        $website->domain = 'non-auth-failure.test';
        $website->website_username = 'BS0019';
        $website->db_name = 'bs0019_db';
        $website->setRelation('server', $server);

        $command = new TestableHestiaInstallAsteroCommand;
        $command->hestiaResponses = [
            [
                'success' => false,
                'message' => 'Artisan install failed: Required dependency missing.',
                'data' => [],
                'code' => 216,
            ],
        ];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Required dependency missing');

        try {
            $command->runHandleCommand($website);
        } finally {
            $this->assertCount(1, $command->hestiaCalls);
            $this->assertSame('a-install-astero', $command->hestiaCalls[0]['command']);
            $this->assertFalse($website->refreshCalled);
            $this->assertCount(1, $website->stepUpdates);
            $this->assertSame('failed', $website->stepUpdates[0]['status']);
        }
    }
}
