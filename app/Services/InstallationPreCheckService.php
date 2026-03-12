<?php

namespace App\Services;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Service class to handle pre-installation checks for the application installation process.
 *
 * This service centralizes all validation logic required before running the installation,
 * providing a clean separation of concerns and improved maintainability.
 */
class InstallationPreCheckService
{
    /**
     * Console command instance for output
     */
    private ?Command $command = null;

    /**
     * Set the command instance for output
     */
    public function setCommand(Command $command): self
    {
        $this->command = $command;

        return $this;
    }

    /**
     * Run comprehensive pre-installation checks
     */
    public function runPreChecks(bool $force = false): bool
    {
        $checks = [
            'displayEnvironmentVariables' => 'Key Environment Variables',
            'checkInstallationStatus' => 'Installation status',
            'checkPhpVersion' => 'PHP version compatibility',
            'checkDatabaseConnection' => 'Database connectivity',
            'checkRequiredExtensions' => 'Required PHP extensions',
            'checkFilePermissions' => 'File system permissions',
            'checkEnvironmentFile' => 'Environment configuration',
            'checkDiskSpace' => 'Available disk space',
            'checkMemoryLimit' => 'PHP memory limit',
        ];

        $allPassed = true;
        foreach ($checks as $method => $description) {
            try {
                $result = $this->{$method}();
                if (! $result) {
                    $this->output(sprintf('❌ %s: FAILED', $description), 'error');
                    $allPassed = false;
                }
            } catch (Exception $e) {
                $this->output(sprintf('❌ %s: %s', $description, $e->getMessage()), 'error');
                $allPassed = false;
            }
        }

        if (! $allPassed && ! $force) {
            $this->output('💡 Use --force to proceed anyway (not recommended)', 'error');
        }

        return $allPassed || $force;
    }

    /**
     * Display key environment variables for debugging purposes.
     */
    public function displayEnvironmentVariables(): bool
    {
        return true; // This check is for display purposes only.
    }

    /**
     * Check if the application is already installed in a production environment.
     */
    public function checkInstallationStatus(): bool
    {
        if (app()->environment('production') && ! empty(config('app.key'))) {
            $this->output('Application is already installed in production. To reinstall, please clear the APP_KEY from your .env file first.', 'error');

            return false;
        }

        return true;
    }

    /**
     * Check PHP version compatibility
     */
    public function checkPhpVersion(): bool
    {
        /** @phpstan-ignore greaterOrEqual.alwaysTrue */
        return PHP_VERSION_ID >= 80300;
    }

    /**
     * Check database connection
     */
    public function checkDatabaseConnection(): bool
    {
        try {
            $connection = DB::connection();
            $driver = $connection->getDriverName();
            $dbName = $connection->getDatabaseName();

            // For SQLite, just check if the file is writable
            if ($driver === 'sqlite') {
                return is_writable($dbName);
            }

            // For other drivers, perform a test write operation
            $tableName = 'installation_test_'.Str::random(10);
            Schema::create($tableName, function ($table): void {
                $table->increments('id');
            });
            Schema::drop($tableName);

            return true;
        } catch (Exception $exception) {
            throw new Exception('Database connectivity failed: '.$exception->getMessage(), $exception->getCode(), $exception);
        }
    }

    /**
     * Check and repair SQLite database if corrupted
     */
    public function checkAndRepairSqliteDatabase(): bool
    {
        $databasePath = database_path('database.sqlite');

        // Create database file if it doesn't exist
        if (! file_exists($databasePath)) {
            touch($databasePath);
        }

        // Check if database file is corrupted (empty or invalid)
        if (filesize($databasePath) === 0) {
            // On Windows, we might have file locking issues, so be more gentle
            try {
                unlink($databasePath);
            } catch (Exception) {
                // If we can't delete the file, try to clear its content
                file_put_contents($databasePath, '');
            }

            touch($databasePath);
        }

        try {
            // Clear any existing connections to avoid file locks
            DB::purge('sqlite');

            // Test database connectivity
            DB::connection()->getPdo();

            // Try to run a simple query to ensure the database is working
            DB::select('SELECT 1');

            return true;
        } catch (Exception) {
            // If database is corrupted, try to repair it
            return $this->repairSqliteDatabase($databasePath);
        }
    }

    /**
     * Repair corrupted SQLite database
     */
    public function repairSqliteDatabase(string $databasePath): bool
    {
        try {
            // Clear any existing connections first
            DB::purge('sqlite');

            // Backup corrupted database if it exists and has content
            if (file_exists($databasePath) && filesize($databasePath) > 0) {
                $backupPath = $databasePath.'.corrupted.'.date('Y-m-d-H-i-s');
                try {
                    copy($databasePath, $backupPath);
                } catch (Exception) {
                    // Silent fail on backup
                }
            }

            // Remove corrupted database and create fresh one
            if (file_exists($databasePath)) {
                try {
                    unlink($databasePath);
                } catch (Exception) {
                    // If we can't delete, try to clear the file content
                    file_put_contents($databasePath, '');
                }
            }

            // Create fresh database file
            touch($databasePath);

            // Clear connections again and test the new database
            DB::purge('sqlite');
            $pdo = DB::connection()->getPdo();

            // Test with a simple query
            $pdo->exec('SELECT 1');

            return true;
        } catch (Exception $exception) {
            throw new Exception('Failed to repair SQLite database: '.$exception->getMessage(), $exception->getCode(), $exception);
        }
    }

    /**
     * Check required PHP extensions
     */
    public function checkRequiredExtensions(): bool
    {
        $required = ['pdo', 'mbstring', 'tokenizer', 'xml', 'ctype', 'json', 'bcmath'];
        foreach ($required as $ext) {
            if (! extension_loaded($ext)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check file permissions
     */
    public function checkFilePermissions(): bool
    {
        $paths = [
            storage_path(),
            storage_path('app'),
            storage_path('framework'),
            storage_path('logs'),
            public_path(),
        ];

        foreach ($paths as $path) {
            // If storage path does not exist (new install), skip check for it
            if (! file_exists($path)) {
                continue;
            }

            if (! is_writable($path)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check environment file exists
     */
    public function checkEnvironmentFile(): bool
    {
        return file_exists(app()->environmentFilePath());
    }

    /**
     * Check available disk space
     */
    public function checkDiskSpace(): bool
    {
        $freeBytes = disk_free_space(base_path());
        $requiredBytes = 100 * 1024 * 1024; // 100MB

        return $freeBytes > $requiredBytes;
    }

    /**
     * Check PHP memory limit
     */
    public function checkMemoryLimit(): bool
    {
        $memoryLimit = ini_get('memory_limit');
        if ($memoryLimit === '-1') {
            return true; // No limit
        }

        $bytes = $this->convertToBytes($memoryLimit);

        return $bytes >= (128 * 1024 * 1024); // 128MB minimum
    }

    /**
     * Convert memory size string to bytes
     */
    private function convertToBytes(string $value): int
    {
        $value = trim($value);
        $last = strtolower($value[strlen($value) - 1]);
        $value = (int) $value;

        switch ($last) {
            case 'g':
                $value *= 1024;
                // no break
            case 'm':
                $value *= 1024;
                // no break
            case 'k':
                $value *= 1024;
        }

        return $value;
    }

    /**
     * Output helper method
     */
    private function output(string $message, string $type = 'info'): void
    {
        if ($this->command instanceof Command) {
            match ($type) {
                'error' => $this->command->error($message),
                'warn' => $this->command->warn($message),
                'line' => $this->command->line($message),
                default => $this->command->info($message)
            };
        }
    }
}
