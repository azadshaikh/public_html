<?php

namespace Tests\Unit;

use App\Console\Commands\AsteroInstallCommand;
use App\Modules\ModuleManager;
use App\Services\InstallationPreCheckService;
use App\Services\UserService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Console\OutputStyle;
use ReflectionMethod;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

class AsteroInstallCommandTest extends TestCase
{
    public function test_run_seeders_executes_core_and_enabled_module_seeders(): void
    {
        $databaseSeeder = new DatabaseSeeder;
        $expectedSeeders = array_merge(
            $databaseSeeder->getSeeders(),
            $databaseSeeder->getModuleSeeders(),
        );

        $command = new class(resolve(InstallationPreCheckService::class), resolve(UserService::class)) extends AsteroInstallCommand
        {
            /**
             * @var array<int, array{command: string, arguments: array<string, mixed>}>
             */
            public array $seedCalls = [];

            public function callSilent($command, array $arguments = []): int
            {
                $this->seedCalls[] = [
                    'command' => $command,
                    'arguments' => $arguments,
                ];

                return 0;
            }
        };

        $command->setOutput(new OutputStyle(new ArrayInput([]), new BufferedOutput));

        $this->callPrivateMethod($command, 'runSeeders');

        $this->assertSame(
            array_map(static fn (string $seeder): array => [
                'command' => 'db:seed',
                'arguments' => [
                    '--class' => $seeder,
                    '--no-interaction' => true,
                    '--force' => true,
                ],
            ], $expectedSeeders),
            $command->seedCalls,
        );
    }

    public function test_database_seeder_returns_only_enabled_module_seeders(): void
    {
        $this->refreshModuleManager();

        $moduleSeeders = (new DatabaseSeeder)->getModuleSeeders();

        $this->assertContains('Modules\\CMS\\Database\\Seeders\\DatabaseSeeder', $moduleSeeders);
        $this->assertContains('Modules\\ChatBot\\Database\\Seeders\\DatabaseSeeder', $moduleSeeders);
        $this->assertContains('Modules\\Platform\\Database\\Seeders\\DatabaseSeeder', $moduleSeeders);
        $this->assertContains('Modules\\ReleaseManager\\Database\\Seeders\\DatabaseSeeder', $moduleSeeders);
        $this->assertContains('Modules\\Todos\\Database\\Seeders\\DatabaseSeeder', $moduleSeeders);
    }

    public function test_database_seeder_does_not_include_local_only_seeders(): void
    {
        $seeders = (new DatabaseSeeder)->getSeeders();

        $this->assertNotContains('Database\\Seeders\\LocalUserSeeder', $seeders);
        $this->assertNotContains('Database\\Seeders\\LocalDatagridUsersSeeder', $seeders);
    }

    private function callPrivateMethod(object $instance, string $method, array $arguments = []): mixed
    {
        $reflectionMethod = new ReflectionMethod($instance, $method);

        return $reflectionMethod->invokeArgs($instance, $arguments);
    }

    private function refreshModuleManager(): void
    {
        app()->forgetInstance(ModuleManager::class);
        app()->singleton(ModuleManager::class, fn ($app): ModuleManager => new ModuleManager(
            files: $app['files'],
            config: $app['config'],
        ));
    }
}
