<?php

namespace Tests\Support;

use Illuminate\Contracts\Console\Kernel;
use Symfony\Component\Console\Input\ArgvInput;
use Throwable;

final class TestEnvironmentRestorer
{
    public static function register(): void
    {
        \register_shutdown_function(static function (): void {
            self::restore();
        });
    }

    /**
     * @param  array<string, string|null>|null  $environment
     */
    public static function shouldRestore(?array $environment = null): bool
    {
        $environment ??= self::environment();

        $restoreFlag = \strtolower(\trim((string) ($environment['RESTORE_APP_AFTER_TESTING'] ?? 'true')));

        if (\in_array($restoreFlag, ['0', 'false', 'no', 'off'], true)) {
            return false;
        }

        $dbConnection = \strtolower(\trim((string) ($environment['DB_CONNECTION'] ?? '')));
        $dbDatabase = \trim((string) ($environment['DB_DATABASE'] ?? ''));

        if ($dbConnection === '') {
            return false;
        }

        return ! ($dbConnection === 'sqlite' && ($dbDatabase === '' || $dbDatabase === ':memory:'));
    }

    public static function restore(): void
    {
        if (! self::shouldRestore()) {
            return;
        }

        try {
            self::restoreInProcess();
        } catch (Throwable $throwable) {
            \file_put_contents('php://stderr', \implode(PHP_EOL, [
                'Failed to restore the application database after testing.',
                $throwable->getMessage(),
                '',
            ]));
        }
    }

    public static function restoreInProcess(): void
    {
        self::setLocalEnvironment();

        $app = require \dirname(__DIR__, 2).'/bootstrap/app.php';

        /** @var Kernel $kernel */
        $kernel = $app->make(Kernel::class);

        $kernel->call('migrate:fresh', ['--force' => true, '--no-interaction' => true]);
        $kernel->call('db:seed', ['--force' => true, '--no-interaction' => true]);
        $kernel->call('optimize:clear', ['--no-interaction' => true]);
        $kernel->terminate(new ArgvInput, 0);
    }

    public static function command(?string $phpBinary = null, ?string $artisanPath = null): string
    {
        $phpBinary ??= PHP_BINARY;
        $artisanPath ??= \dirname(__DIR__, 2).'/artisan';

        $php = \escapeshellarg($phpBinary);
        $artisan = \escapeshellarg($artisanPath);

        return \implode(' && ', [
            sprintf('APP_ENV=local %s %s migrate:fresh --force --no-interaction', $php, $artisan),
            sprintf('APP_ENV=local %s %s db:seed --force --no-interaction', $php, $artisan),
            sprintf('APP_ENV=local %s %s optimize:clear --no-interaction', $php, $artisan),
        ]);
    }

    /**
     * @return array<string, string|null>
     */
    private static function environment(): array
    {
        return [
            'RESTORE_APP_AFTER_TESTING' => \getenv('RESTORE_APP_AFTER_TESTING') ?: null,
            'DB_CONNECTION' => \getenv('DB_CONNECTION') ?: null,
            'DB_DATABASE' => \getenv('DB_DATABASE') ?: null,
        ];
    }

    private static function setLocalEnvironment(): void
    {
        \putenv('APP_ENV=local');
        $_ENV['APP_ENV'] = 'local';
        $_SERVER['APP_ENV'] = 'local';
    }
}
