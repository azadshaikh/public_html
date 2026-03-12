<?php

namespace App\Console\Commands;

use App\Enums\Status;
use App\Models\Role;
use App\Models\User;
use App\Services\InstallationPreCheckService;
use App\Services\UserService;
use Database\Seeders\DatabaseSeeder;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Command to run a fresh installation of the application.
 *
 * This command is the main entry point for setting up a new instance of the application.
 * It orchestrates a series of tasks including application key generation, database setup,
 * environment validation, and background job dispatching with comprehensive error handling
 * and rollback mechanisms.
 *
 * Features:
 * - Comprehensive pre-installation validation
 * - Step-by-step progress tracking
 * - Automatic rollback on failure
 * - Detailed logging for debugging
 * - Graceful error handling
 * - Verbosity control (-v for verbose, -q for quiet)
 * - SQLite force deletion for local environment (use scripts/install-with-force.php --force)
 */
class AsteroInstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'astero:install
                          {firstName? : First name for the admin user}
                          {lastName? : Last name for the admin user}
                          {email? : Email for the admin user}
                          {userpassword? : Password for the admin user}
                          {superuseremail? : Email for the super user}
                          {superuserpassword? : Password for the super user}
                          {--skipemail= : Skip sending welcome emails (true or false)}
                          {--dry-run : Run pre-checks without executing installation}
                          {--force : Force installation even if some checks fail}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run a fresh installation of the application with comprehensive error handling and rollback support.';

    /**
     * Completed installation steps for rollback purposes
     */
    private array $completedSteps = [];

    /**
     * Installation start time for performance tracking
     */
    private float $startTime;

    /**
     * Create a new command instance.
     */
    public function __construct(
        /**
         * Pre-check service instance
         */
        private readonly InstallationPreCheckService $preCheckService,
        /**
         * User service instance
         */
        private readonly UserService $userService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->startTime = microtime(true);
        $this->preCheckService->setCommand($this);

        try {
            if ($this->getOutput()->isVerbose()) {
                $this->info('Clearing configuration cache to ensure fresh settings are used...');
            }

            $this->call('config:clear');

            $this->displayHeader();

            // If using SQLite, handle database file operations
            // If creation fails, abort pre-checks to avoid confusing migration errors later.
            try {
                if (config('database.default') === 'sqlite') {
                    $dbPath = config('database.connections.sqlite.database');

                    // ':memory:' is valid and requires no file
                    if ($dbPath !== ':memory:') {
                        $resolvedPath = $dbPath;

                        // If the configured path is relative, resolve it against base path
                        if (! Str::startsWith($dbPath, ['/', '\\'])) {
                            $resolvedPath = base_path($dbPath);
                        }

                        if (! file_exists($resolvedPath)) {
                            $dir = dirname((string) $resolvedPath);
                            throw_if(! is_dir($dir) && (! @mkdir($dir, 0755, true) && ! is_dir($dir)), Exception::class, 'Failed to create directory for SQLite DB: '.$dir);

                            // Try to create an empty sqlite file
                            throw_if(@file_put_contents($resolvedPath, '') === false, Exception::class, 'Unable to create SQLite database file at '.$resolvedPath);

                            // Verify file exists and is writable
                            throw_if(! file_exists($resolvedPath) || ! is_writable($resolvedPath), Exception::class, 'SQLite database file exists but is not writable: '.$resolvedPath);

                            if ($this->getOutput()->isVerbose()) {
                                $this->line('    ℹ️  Created SQLite database file at: '.$resolvedPath);
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                // Surface a helpful pre-check failure message when sqlite file creation fails
                $this->error(__('install.error.pre_checks_failed').' - '.$e->getMessage());

                return self::FAILURE;
            }

            if ($this->option('dry-run')) {
                return $this->preCheckService->runPreChecks($this->option('force')) ? self::SUCCESS : self::FAILURE;
            }

            if (! $this->preCheckService->runPreChecks($this->option('force'))) {
                $this->error(__('install.error.pre_checks_failed'));

                return self::FAILURE;
            }

            return $this->executeInstallation();
        } catch (Exception $exception) {
            $this->handleInstallationFailure($exception);

            return self::FAILURE;
        }
    }

    /**
     * Execute the main installation process
     */
    private function executeInstallation(): int
    {
        try {
            $steps = [
                'generateAppKey' => 'Generate application key',
                'clearCacheStore' => 'Clear cache store',
                'clearSessions' => 'Clear existing sessions',
                'runMigrations' => 'Run database migrations',
                'runSeeders' => 'Seed initial data',
                'createStorageLink' => 'Create storage link',
                'createUsersIfNeeded' => 'Create initial users',
                'validateInstallation' => 'Validate installation',
                'rebuildCaches' => 'Rebuild application caches for performance',
                'restartQueueWorkers' => 'Restart queue workers',
            ];

            if (! $this->getOutput()->isQuiet()) {
                $this->info('🚀 Installing Astero...');
            }

            foreach ($steps as $method => $description) {
                $this->executeStep($method, $description);
            }

            $this->displaySuccessMessage();

            return self::SUCCESS;
        } catch (Exception $exception) {
            $this->rollbackCompletedSteps();
            throw $exception;
        }
    }

    /**
     * Execute a single installation step with error handling
     */
    private function executeStep(string $method, string $description): void
    {
        if ($this->getOutput()->isVerbose()) {
            $this->line(sprintf('  ├─ %s...', $description));
        }

        $stepStart = microtime(true);

        try {
            $this->{$method}();
            $this->completedSteps[] = $method;

            $duration = round(microtime(true) - $stepStart, 2);

            if ($this->getOutput()->isVerbose()) {
                $this->line(sprintf('  │  ✅ %s completed in %ss', $description, $duration));
            } elseif (! $this->getOutput()->isQuiet()) {
                // Simple checkmark for default mode
                $simpleDesc = $this->getSimpleDescription($method);
                $this->line('✓ '.$simpleDesc);
            }
        } catch (Exception $exception) {
            $duration = round(microtime(true) - $stepStart, 2);
            $this->line(sprintf('  │  ❌ %s failed after %ss', $description, $duration));

            Log::error(sprintf("Installation step '%s' failed", $method), [
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
                'duration' => $duration,
            ]);

            throw new Exception(sprintf("Failed at step '%s': %s", $description, $exception->getMessage()), 0, $exception);
        }
    }

    /**
     * Get a simplified description for default output mode
     */
    private function getSimpleDescription(string $method): string
    {
        return match ($method) {
            'generateAppKey' => 'Pre-checks passed',
            'clearCacheStore' => 'Cache cleared',
            'clearSessions' => 'Sessions cleared',
            'runMigrations' => 'Database migrated',
            'runSeeders' => 'Data seeded',
            'createStorageLink' => 'Storage linked',
            'createUsersIfNeeded' => 'Users created',
            'validateInstallation' => 'Installation validated',
            'rebuildCaches' => 'Caches optimized',
            'restartQueueWorkers' => 'Queue restarted',
            default => $method,
        };
    }

    /**
     * Generate application key with validation
     */
    private function generateAppKey(): void
    {
        if (empty(config('app.key'))) {
            Artisan::call('key:generate', ['--no-interaction' => true]);
            // When config is cached (production), env() returns null.
            // The key:generate command already sets the config value in memory.
            // We'll clear the config cache to ensure the new key is loaded for subsequent operations.
            Artisan::call('config:clear');
            throw_if(empty(config('app.key')), Exception::class, 'Application key generation failed');
        } elseif ($this->getOutput()->isVerbose()) {
            $this->line('     ℹ️  Application key already exists, skipping generation');
        }
    }

    private function clearCacheStore(): void
    {
        try {
            Artisan::call('cache:clear', ['--no-interaction' => true]);

            if ($this->getOutput()->isVerbose()) {
                $this->line('  │  ✅ Cache store cleared.');
            }
        } catch (Exception $exception) {
            Log::warning('Failed to clear cache during install', [
                'error' => $exception->getMessage(),
            ]);

            if (! $this->getOutput()->isQuiet()) {
                $this->warn('    ⚠️  Could not clear cache store. Continuing install...');
            }
        }
    }

    /**
     * Clear persisted session data from previous installs.
     */
    private function clearSessions(): void
    {
        $driver = (string) config('session.driver', 'file');

        if (in_array($driver, ['database', 'array', 'cookie'], true)) {
            if ($this->getOutput()->isVerbose()) {
                $this->line(sprintf("     ℹ️  Session driver is '%s', skipping explicit session clear", $driver));
            }

            return;
        }

        try {
            if ($driver === 'file') {
                $sessionPath = (string) config('session.files', storage_path('framework/sessions'));

                if (! File::isDirectory($sessionPath)) {
                    if ($this->getOutput()->isVerbose()) {
                        $this->line('     ℹ️  Session directory not found, skipping file session clear');
                    }

                    return;
                }

                foreach (File::files($sessionPath) as $file) {
                    File::delete($file->getPathname());
                }

                return;
            }

            $sessionStore = (string) config('session.store', '');

            if ($sessionStore === '') {
                if ($this->getOutput()->isVerbose()) {
                    $this->line(sprintf("     ℹ️  No cache store configured for '%s' sessions, skipping session clear", $driver));
                }

                return;
            }

            Cache::store($sessionStore)->clear();
        } catch (Throwable $throwable) {
            Log::warning('Failed to clear session storage during install', [
                'driver' => $driver,
                'error' => $throwable->getMessage(),
            ]);

            if (! $this->getOutput()->isQuiet()) {
                $this->warn('    ⚠️  Could not clear existing sessions. Continuing install...');
            }
        }
    }

    /**
     * Run database migrations
     */
    private function runMigrations(): void
    {
        try {
            // Check if migrations table exists
            if (! Schema::hasTable('migrations')) {
                $exitCode = Artisan::call('migrate:install');
                throw_if($exitCode !== 0, Exception::class, 'Failed to create migrations table');
            }

            $exitCode = Artisan::call('migrate:fresh', [
                '--no-interaction' => true,
                '--force' => true,
            ]);

            if ($exitCode !== 0) {
                $output = Artisan::output();
                throw new Exception('Migration execution failed. Output: '.$output);
            }
        } catch (Exception $exception) {
            throw new Exception('Database migrations failed: '.$exception->getMessage(), $exception->getCode(), $exception);
        }
    }

    /**
     * Run database seeders with error handling
     */
    private function runSeeders(): void
    {
        if ($this->getOutput()->isVerbose()) {
            $this->line('  │  Seeding main database...');
        }

        $databaseSeeder = new DatabaseSeeder;

        foreach ($databaseSeeder->getSeeders() as $seeder) {
            if ($this->getOutput()->isVerbose()) {
                $this->line('  │    - Seeding: '.class_basename($seeder));
            }

            $this->callSilent('db:seed', ['--class' => $seeder, '--no-interaction' => true, '--force' => true]);
        }

        if ($this->getOutput()->isVerbose()) {
            $this->line('  │  ✅ Main database seeded successfully.');
        }

        // Run module seeders
        try {
            if ($this->getOutput()->isVerbose()) {
                $this->line('  │  Seeding modules...');
            }

            foreach ($databaseSeeder->getModuleSeeders() as $seederClass) {
                if ($this->getOutput()->isVerbose()) {
                    $this->line('  │    - Seeding: '.class_basename(str_replace('\\Database\\Seeders\\DatabaseSeeder', '', $seederClass)));
                }

                $this->callSilent('db:seed', [
                    '--class' => $seederClass,
                    '--no-interaction' => true,
                    '--force' => true,
                ]);
            }

            if ($this->getOutput()->isVerbose()) {
                $this->line('  │  ✅ Modules seeded successfully.');
            }
        } catch (Exception $exception) {
            if (! $this->getOutput()->isQuiet()) {
                $this->warn('    ⚠️  Module seeding failed: '.$exception->getMessage());
            }
        }
    }

    /**
     * Create storage link with verification
     */
    private function createStorageLink(): void
    {
        $linkPath = public_path('storage');

        // Ensure all required storage directories exist
        $requiredDirs = [
            storage_path(),
            storage_path('app'),
            storage_path('app/public'),
            storage_path('framework'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
            storage_path('framework/cache'),
            storage_path('logs'),
        ];
        foreach ($requiredDirs as $dir) {
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }

        // Check if storage link already exists
        if (is_link($linkPath) || is_dir($linkPath)) {
            if ($this->getOutput()->isVerbose()) {
                $this->line('    ℹ️  Storage link already exists, skipping creation');
            }

            return;
        }

        $exitCode = Artisan::call('storage:link');

        throw_if($exitCode !== 0, Exception::class, 'Storage link creation failed');

        // Verify the link was created - on Windows, check if path exists
        throw_unless(file_exists($linkPath), Exception::class, 'Storage link verification failed - path does not exist');

        // Additional verification: check if we can read from the storage path
        $targetPath = storage_path('app/public');
        if (! is_dir($targetPath)) {
            if ($this->getOutput()->isVerbose()) {
                $this->warn('    ⚠️  Storage target directory does not exist, creating it...');
            }

            mkdir($targetPath, 0755, true);
        }
    }

    /**
     * Create initial users if credentials provided
     */
    private function createUsersIfNeeded(): void
    {
        $superUserEmail = $this->argument('superuseremail');
        $superUserPassword = $this->argument('superuserpassword');

        // Super user is created in UserSeeder. Here, we just update the password if provided.
        $emailToFind = $superUserEmail ?: 'su@astero.in';
        $superUser = $this->userService->findUserByEmail($emailToFind);

        if ($superUser && ! empty($superUserPassword)) {
            if ($this->getOutput()->isVerbose()) {
                $this->line('  │  Updating super user password and verifying email...');
            }

            $this->userService->updatePassword($superUser, $superUserPassword);

            // Ensure email is verified
            if (! $superUser->hasVerifiedEmail()) {
                $superUser->markEmailAsVerified();
            }

            if ($this->getOutput()->isVerbose()) {
                $this->line('  │  ✅ Super user password updated and email verified.');
            }
        } elseif ($superUser instanceof User) {
            if ($this->getOutput()->isVerbose()) {
                $this->line('     ℹ️  Super user already exists, password not provided for update.');
            }

            // Ensure email is verified even if password wasn't updated
            if (! $superUser->hasVerifiedEmail()) {
                $superUser->markEmailAsVerified();
                if ($this->getOutput()->isVerbose()) {
                    $this->line('  │  ✅ Super user email verified.');
                }
            }

            if ($this->getOutput()->isVerbose()) {
                $this->info('  │     Default Super User Credentials:');
                $this->info('  │     Email: su@astero.in');
                $this->info('  │     Password: PassWord@1234');
            }
        } elseif (! $this->getOutput()->isQuiet()) {
            $this->warn('  │  ⚠️  Default super user not found. Seeder may have failed.');
        }

        // Create Regular Admin if credentials are provided
        $firstName = $this->argument('firstName');
        $lastName = $this->argument('lastName');
        $email = $this->argument('email');
        $userPassword = $this->argument('userpassword');

        if (! empty($firstName) && ! empty($lastName) && ! empty($email)) {
            if ($this->getOutput()->isVerbose()) {
                $this->line('  │  Creating admin user...');
            }

            $password = $userPassword ?: Str::random(12);

            try {
                // Check if user already exists
                $existingUser = $this->userService->findUserByEmail($email);
                if ($existingUser instanceof User) {
                    $this->ensureAdministratorRoleAndVerifiedEmail($existingUser);

                    if (! $this->getOutput()->isQuiet()) {
                        $this->warn('  │  ⚠️  Admin user with this email already exists, updated role/email verification.');
                    }
                } else {
                    // Create user with active status (UserService handles password hashing)
                    $adminUser = $this->userService->createUser([
                        'firstName' => $firstName,
                        'lastName' => $lastName,
                        'email' => $email,
                        'password' => $password,
                        'status' => Status::ACTIVE,
                    ]);

                    $this->ensureAdministratorRoleAndVerifiedEmail($adminUser);

                    if ($this->getOutput()->isVerbose()) {
                        $this->line('  │  ✅ Admin user created successfully.');
                        $this->info('  │     Email: '.$email);
                        if (! $userPassword) {
                            $this->info('  │     Password: '.$password);
                        }
                    }
                }
            } catch (Exception $e) {
                if (! $this->getOutput()->isQuiet()) {
                    $this->warn('  │  ⚠️  Could not create admin user: '.$e->getMessage());
                }
            }
        } elseif ($this->getOutput()->isVerbose()) {
            $this->line('     ℹ️  No admin user credentials provided, skipping admin user creation.');
        }

        $this->activateLocalAdminUser();
    }

    private function ensureAdministratorRoleAndVerifiedEmail(User $user): void
    {
        $administratorRole = Role::query()->where('name', 'administrator')->first();

        if ($administratorRole && ! $user->hasRole($administratorRole->name)) {
            $user->assignRole($administratorRole->name);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }
    }

    private function activateLocalAdminUser(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        $adminUser = $this->userService->findUserByEmail('administrator@test.local');

        if (! $adminUser instanceof User) {
            if ($this->getOutput()->isVerbose()) {
                $this->line('     ℹ️  Default admin user not found, skipping local activation.');
            }

            return;
        }

        if ($adminUser->status !== Status::ACTIVE->value) {
            $adminUser->status = Status::ACTIVE->value;
            $adminUser->save();
        }

        if (! $adminUser->hasVerifiedEmail()) {
            $adminUser->markEmailAsVerified();
        }

        if ($this->getOutput()->isVerbose()) {
            $this->line('  │  ✅ Local admin user activated and email verified.');
        }
    }

    /**
     * Validate installation completed successfully
     */
    private function validateInstallation(): void
    {
        $validations = [
            'database' => Schema::hasTable('users'),
            'app_key' => ! empty(config('app.key')),
            'storage_link' => file_exists(public_path('storage')),
        ];

        foreach ($validations as $check => $result) {
            throw_unless($result, Exception::class, 'Installation validation failed: '.$check);
        }
    }

    /**
     * Clear and rebuild application caches.
     */
    private function rebuildCaches(): void
    {
        // Guard: skip cache rebuild in test environment to prevent poisoning
        if ($this->isRunningInTestProcess()) {
            if ($this->getOutput()->isVerbose()) {
                $this->warn('  │  Test environment detected — skipping cache rebuild to prevent poisoning.');
            }

            return;
        }

        if ($this->getOutput()->isVerbose()) {
            $this->line('  │  Clearing and rebuilding caches...');
        }

        // Force-load .env values into the process environment so that
        // config:cache (called by optimize) picks up real values, not
        // inherited phpunit.xml overrides.
        $this->forceLoadDotenv();

        $failures = [];

        foreach (['optimize:clear', 'optimize'] as $command) {
            try {
                $exitCode = Artisan::call($command);
                $output = trim(Artisan::output());

                if ($exitCode !== 0) {
                    $failures[] = [
                        'type' => 'artisan',
                        'command' => $command,
                        'exit_code' => $exitCode,
                        'output' => $output,
                    ];
                }
            } catch (Throwable $t) {
                $failures[] = [
                    'type' => 'artisan',
                    'command' => $command,
                    'error' => $t->getMessage(),
                ];
            }
        }

        try {
            Cache::flush();
        } catch (Throwable $throwable) {
            $failures[] = [
                'type' => 'cache',
                'command' => 'Cache::flush',
                'error' => $throwable->getMessage(),
            ];
        }

        if ($failures === []) {
            // Validate the config cache is not poisoned with test values
            $this->validateConfigCache();

            if ($this->getOutput()->isVerbose()) {
                $this->line('  │  ✅ Caches rebuilt successfully.');
            }

            return;
        }

        // Note: LOG_LEVEL may be set to error in .env, so rely on console output for diagnostics.
        if ($this->getOutput()->isVerbose()) {
            foreach ($failures as $failure) {
                $command = $failure['command'];

                if ($failure['type'] === 'artisan') {
                    if (array_key_exists('exit_code', $failure)) {
                        $this->warn(sprintf('  │  ⚠️  Cache step failed: %s (exit %s)', $command, $failure['exit_code']));
                    } else {
                        $this->warn(sprintf('  │  ⚠️  Cache step errored: %s - %s', $command, $failure['error']));
                    }

                    if (! in_array($failure['output'] ?? null, [null, '', '0'], true)) {
                        $this->line('  │     Output: '.Str::limit($failure['output'], 300));
                    }
                } else {
                    $this->warn(sprintf('  │  ⚠️  Cache step errored: %s - %s', $command, $failure['error']));
                }
            }
        } elseif (! $this->getOutput()->isQuiet()) {
            $failedCommands = array_values(array_unique(array_map(
                static fn (array $failure): string => $failure['command'],
                $failures
            )));

            $this->warn('    ⚠️  Could not rebuild all application caches. Failed: '.implode(', ', $failedCommands));
        }
    }

    /**
     * Restart queue workers so daemons load fresh code after installation.
     */
    private function restartQueueWorkers(): void
    {
        try {
            $exitCode = Artisan::call('queue:restart', [
                '--no-interaction' => true,
            ]);
            $output = trim(Artisan::output());

            throw_if($exitCode !== 0, Exception::class, 'Queue worker restart returned a non-zero exit code.'.
            ($output === '' || $output === '0' ? '' : ' Output: '.$output));
        } catch (Exception $exception) {
            throw new Exception('Queue worker restart failed: '.$exception->getMessage(), 0, $exception);
        }
    }

    /**
     * Handle installation failure with detailed logging
     */
    private function handleInstallationFailure(Throwable $e): void
    {
        $duration = round(microtime(true) - $this->startTime, 2);

        Log::error('Application installation failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'duration' => $duration,
            'completed_steps' => $this->completedSteps,
        ]);

        $this->error('');
        $this->error('❌ Installation failed!');
        $this->error(sprintf('⏱️  Failed after %s seconds', $duration));
        $this->error('📝 Error: '.$e->getMessage());
        $this->error('');
        $this->error('🔧 Troubleshooting:');
        $this->error('  • Check the logs for detailed error information');
        $this->error('  • Verify database configuration and connectivity');
        $this->error('  • Ensure all required PHP extensions are installed');
        $this->error('  • Check file system permissions');
        $this->error('');

        $this->rollbackCompletedSteps();
    }

    /**
     * Rollback completed steps in reverse order
     */
    private function rollbackCompletedSteps(): void
    {
        if ($this->completedSteps === []) {
            return;
        }

        $this->warn('🔄 Rolling back completed steps...');

        $rollbackMethods = [
            'rebuildCaches' => 'rollbackRebuildCaches',
            'createStorageLink' => 'rollbackStorageLink',
            'generateAppKey' => 'rollbackAppKey',
        ];

        foreach (array_reverse($this->completedSteps) as $step) {
            if (isset($rollbackMethods[$step])) {
                try {
                    $this->{$rollbackMethods[$step]}();
                    $this->warn('  ✅ Rolled back: '.$step);
                } catch (Exception $e) {
                    $this->error(sprintf('  ❌ Failed to rollback: %s - %s', $step, $e->getMessage()));
                }
            }
        }
    }

    /**
     * Rollback storage link creation
     */
    private function rollbackStorageLink(): void
    {
        $linkPath = public_path('storage');
        if (is_link($linkPath)) {
            unlink($linkPath);
        }
    }

    /**
     * Rollback app key generation (optional, usually we keep the key)
     */
    private function rollbackAppKey(): void
    {
        // Usually we don't rollback the app key as it may be needed
        // This is here for completeness but could be made configurable
    }

    /**
     * Rollback cache rebuilding (clear caches).
     */
    private function rollbackRebuildCaches(): void
    {
        try {
            Artisan::call('optimize:clear');
            Artisan::call('optimize');
        } catch (Exception) {
            // Ignore errors during rollback cache operations
        }
    }

    /**
     * Detect whether this process was spawned by PHPUnit / Pest.
     */
    private function isRunningInTestProcess(): bool
    {
        if (app()->environment('testing')) {
            return true;
        }

        $envVal = Request::server('APP_ENV') ?? Env::get('APP_ENV', getenv('APP_ENV'));

        return $envVal === 'testing';
    }

    /**
     * Force-load the .env file, overwriting any inherited process env vars.
     *
     * Prevents phpunit.xml <env> directives that leaked into a subprocess
     * from poisoning the rebuilt config cache.
     */
    private function forceLoadDotenv(): void
    {
        try {
            $envPath = base_path('.env');
            if (! File::exists($envPath)) {
                return;
            }

            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }

                if (str_starts_with($line, '#')) {
                    continue;
                }

                if (! str_contains($line, '=')) {
                    continue;
                }

                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // Strip surrounding quotes
                if (strlen($value) >= 2) {
                    $first = $value[0];
                    $last = $value[strlen($value) - 1];
                    if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                        $value = substr($value, 1, -1);
                    }
                }

                if (strtolower($value) === 'null') {
                    $value = '';
                }

                putenv(sprintf('%s=%s', $key, $value));
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        } catch (Throwable $throwable) {
            Log::warning('Failed to force-reload .env during install', ['error' => $throwable->getMessage()]);
        }
    }

    /**
     * Validate the rebuilt config cache doesn't contain test-environment values.
     *
     * If poisoned, the cache file is deleted so the app falls back to
     * reading .env directly until the next successful cache rebuild.
     */
    private function validateConfigCache(): void
    {
        try {
            $cachePath = app()->getCachedConfigPath();
            if (! File::exists($cachePath)) {
                return;
            }

            $config = require $cachePath;
            $appEnv = $config['app']['env'] ?? null;
            $sessionDriver = $config['session']['driver'] ?? null;

            if ($appEnv === 'testing' || $sessionDriver === 'array') {
                File::delete($cachePath);

                $message = sprintf('Deleted poisoned config cache (APP_ENV=%s, SESSION_DRIVER=%s).', $appEnv, $sessionDriver);
                if (! $this->getOutput()->isQuiet()) {
                    $this->warn('    ⚠️  '.$message);
                }

                Log::warning('Deleted poisoned config cache during install', [
                    'app_env' => $appEnv,
                    'session_driver' => $sessionDriver,
                ]);
            }
        } catch (Throwable $throwable) {
            Log::warning('Config cache validation failed during install', ['error' => $throwable->getMessage()]);
        }
    }

    /**
     * Display installation header with system information
     */
    private function displayHeader(): void
    {
        if ($this->getOutput()->isQuiet()) {
            return;
        }

        if ($this->getOutput()->isVerbose()) {
            $this->info('');
            $this->info('╔══════════════════════════════════════════════════════════════╗');
            $this->info('║                     ASTERO INSTALLATION                     ║');
            $this->info('╚══════════════════════════════════════════════════════════════╝');
            $this->info('');
            $this->info('🚀 Starting application installation...');
            $this->info('📅 Started at: '.now()->format('Y-m-d H:i:s'));
            $this->info('🖥️  PHP Version: '.PHP_VERSION);
            $this->info('📦 Laravel Version: '.app()->version());
            $this->info('');
        }
    }

    /**
     * Display success message with summary
     */
    private function displaySuccessMessage(): void
    {
        $duration = round(microtime(true) - $this->startTime, 2);

        if ($this->getOutput()->isQuiet()) {
            $this->info(sprintf('✅ Installation completed successfully (%ss)', $duration));

            return;
        }

        if ($this->getOutput()->isVerbose()) {
            $this->info('');
            $this->info('🎉 Installation completed successfully!');
            $this->info(sprintf('⏱️  Total time: %s seconds', $duration));
            $this->info('');
            $this->info('📋 Next steps:');
            $this->info('  • Check application logs for any warnings');
            $this->info('  • Verify application is accessible via web browser');
            $this->info('');
        } else {
            $this->info('');
            $this->info(sprintf('✅ Installation completed in %ss', $duration));

            // Show default credentials only if super user exists
            $superUser = $this->userService->findUserByEmail('su@astero.in');
            if ($superUser instanceof User) {
                $this->info('   Default credentials: su@astero.in | PassWord@1234');
            }

            $this->info('');
        }
    }
}
