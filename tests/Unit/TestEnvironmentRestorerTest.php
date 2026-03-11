<?php

namespace Tests\Unit;

use Tests\Support\TestEnvironmentRestorer;
use Tests\TestCase;

class TestEnvironmentRestorerTest extends TestCase
{
    public function test_it_skips_restoring_for_in_memory_sqlite(): void
    {
        $this->assertFalse(TestEnvironmentRestorer::shouldRestore([
            'RESTORE_APP_AFTER_TESTING' => 'true',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
        ]));
    }

    public function test_it_skips_restoring_when_explicitly_disabled(): void
    {
        $this->assertFalse(TestEnvironmentRestorer::shouldRestore([
            'RESTORE_APP_AFTER_TESTING' => 'false',
            'DB_CONNECTION' => 'pgsql',
            'DB_DATABASE' => 'app',
        ]));
    }

    public function test_it_restores_for_non_sqlite_databases(): void
    {
        $this->assertTrue(TestEnvironmentRestorer::shouldRestore([
            'RESTORE_APP_AFTER_TESTING' => 'true',
            'DB_CONNECTION' => 'pgsql',
            'DB_DATABASE' => 'app',
        ]));
    }

    public function test_it_builds_the_restore_command(): void
    {
        $command = TestEnvironmentRestorer::command('/usr/bin/php', '/home/example-app/artisan');

        $this->assertStringContainsString("APP_ENV=local '/usr/bin/php' '/home/example-app/artisan' migrate --force --no-interaction", $command);
        $this->assertStringContainsString("APP_ENV=local '/usr/bin/php' '/home/example-app/artisan' db:seed --force --no-interaction", $command);
        $this->assertStringContainsString("APP_ENV=local '/usr/bin/php' '/home/example-app/artisan' optimize:clear --no-interaction", $command);
    }
}
