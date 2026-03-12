<?php

namespace App\Jobs;

use App\Traits\IsMonitored;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Job to run astero:recache command asynchronously.
 *
 * This job is dispatched after settings updates to rebuild caches
 * without blocking the HTTP response.
 */
class RecacheApplication implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use IsMonitored;
    use Queueable;
    use SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 120;

    /**
     * Indicate if the job should be marked as failed on timeout.
     */
    public bool $failOnTimeout = false;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ?string $reason = null,
        public array $envUpdates = [],
        public array $envRemovals = [],
        public bool $clearOnly = false
    ) {
        // Use the 'default' queue for cache operations
        $this->onQueue('default');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('RecacheApplication job started', [
                'reason' => $this->reason,
                'env_updates' => array_keys($this->envUpdates),
                'env_removals' => $this->envRemovals,
                'clear_only' => $this->clearOnly,
            ]);

            $this->applyEnvOverrides();
            $this->runRecacheProcess();

            Log::info('RecacheApplication job completed successfully', [
                'reason' => $this->reason,
                'env_updates' => array_keys($this->envUpdates),
                'env_removals' => $this->envRemovals,
                'clear_only' => $this->clearOnly,
            ]);
        } catch (Exception $exception) {
            Log::error('RecacheApplication job failed', [
                'reason' => $this->reason,
                'env_updates' => array_keys($this->envUpdates),
                'env_removals' => $this->envRemovals,
                'error' => $exception->getMessage(),
            ]);

            // Don't rethrow - we don't want to retry cache operations
        }
    }

    private function runRecacheProcess(): void
    {
        $command = $this->buildRecacheCommand();
        $env = $this->buildCleanEnvironment();
        $process = new Process($command, base_path(), $env, null, $this->timeout);
        $exitCode = $process->run();

        if ($exitCode !== 0 || ! $process->isSuccessful()) {
            $errorOutput = trim($process->getErrorOutput());
            $output = trim($process->getOutput());

            throw new RuntimeException(
                $errorOutput !== '' ? $errorOutput : ($output !== '' ? $output : 'Unknown recache failure')
            );
        }
    }

    private function buildRecacheCommand(): array
    {
        $phpBinary = $this->resolvePhpBinary();
        $command = [
            $phpBinary,
            base_path('artisan'),
            'astero:recache',
        ];

        if ($this->clearOnly) {
            $command[] = '--clear';
        }

        return $command;
    }

    /**
     * Build a clean environment for the subprocess.
     *
     * Strip phpunit.xml <env> overrides (APP_ENV=testing, SESSION_DRIVER=array, …)
     * so the subprocess reads values from .env instead of inheriting test values.
     */
    private function buildCleanEnvironment(): ?array
    {
        $env = getenv();

        // Keys that phpunit.xml commonly overrides — remove them so the
        // subprocess falls back to .env values via Dotenv.
        $testKeys = [
            'APP_ENV',
            'SESSION_DRIVER',
            'QUEUE_CONNECTION',
            'CACHE_STORE',
            'MAIL_MAILER',
            'DB_CONNECTION',
            'DB_DATABASE',
            'BCRYPT_ROUNDS',
        ];

        foreach ($testKeys as $key) {
            unset($env[$key]);
        }

        return $env ?: null;
    }

    private function resolvePhpBinary(): string
    {
        $finder = new PhpExecutableFinder;
        $binary = $finder->find(false);

        if (is_string($binary) && $binary !== '') {
            return $binary;
        }

        $bindirBinary = PHP_BINDIR.DIRECTORY_SEPARATOR.'php';
        if (is_executable($bindirBinary)) {
            return $bindirBinary;
        }

        return 'php';
    }

    private function applyEnvOverrides(): void
    {
        foreach ($this->envUpdates as $key => $value) {
            $this->setRuntimeEnvValue($key, (string) $value);
        }

        foreach ($this->envRemovals as $key) {
            $this->unsetRuntimeEnvValue($key);
        }
    }

    private function setRuntimeEnvValue(string $key, string $value): void
    {
        $normalizedValue = $this->normalizeEnvValue($value);

        // Avoid process-level env mutation in long-lived workers.
        $_ENV[$key] = $normalizedValue;
        $_SERVER[$key] = $normalizedValue;
    }

    private function unsetRuntimeEnvValue(string $key): void
    {
        unset($_ENV[$key], $_SERVER[$key]);
    }

    private function normalizeEnvValue(string $value): string
    {
        $trimmed = trim($value);

        if (strlen($trimmed) >= 2) {
            $first = $trimmed[0];
            $last = $trimmed[strlen($trimmed) - 1];

            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                return substr($trimmed, 1, -1);
            }
        }

        return $trimmed;
    }
}
