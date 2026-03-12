<?php

namespace App\Services\LaravelTools;

use Exception;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\File;

class EnvService
{
    /**
     * Protected ENV keys that cannot be modified
     */
    protected array $protectedKeys = [
        'APP_KEY',
        'APP_ENV',
    ];

    /**
     * Get the .env file content
     */
    public function getContent(): string
    {
        $envPath = base_path('.env');

        return File::exists($envPath) ? File::get($envPath) : '';
    }

    /**
     * Get protected keys
     */
    public function getProtectedKeys(): array
    {
        return $this->protectedKeys;
    }

    /**
     * Update the .env file
     */
    public function update(string $content): array
    {
        $validation = $this->validateContent($content);
        if (! $validation['valid']) {
            return ['success' => false, 'errors' => $validation['errors']];
        }

        try {
            $this->createBackup();
            File::put(base_path('.env'), $content);

            return ['success' => true];
        } catch (Exception $exception) {
            return ['success' => false, 'error' => $exception->getMessage()];
        }
    }

    /**
     * Validate ENV content
     */
    public function validateContent(string $content): array
    {
        $lines = explode("\n", $content);
        $currentEnv = $this->parseEnvFile(base_path('.env'));
        $errors = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if ($line === '0') {
                continue;
            }

            if (str_starts_with($line, '#')) {
                continue;
            }

            if (str_contains($line, '=')) {
                [$key] = explode('=', $line, 2);
                $key = trim($key);

                if (in_array($key, $this->protectedKeys)) {
                    $newValue = $this->getEnvValue($content, $key);
                    $oldValue = $currentEnv[$key] ?? null;

                    if ($newValue !== $oldValue) {
                        $errors[] = __('Cannot modify protected key: '.$key);
                    }
                }
            }
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
        ];
    }

    /**
     * Parse ENV file into array
     */
    public function parseEnvFile(string $path): array
    {
        if (! File::exists($path)) {
            return [];
        }

        $content = File::get($path);
        $lines = explode("\n", $content);
        $env = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if ($line === '0') {
                continue;
            }

            if (str_starts_with($line, '#')) {
                continue;
            }

            if (str_contains($line, '=')) {
                [$key, $value] = explode('=', $line, 2);
                $env[trim($key)] = trim($value, '"\'');
            }
        }

        return $env;
    }

    /**
     * Create ENV backup
     */
    public function createBackup(): string
    {
        $backupDir = storage_path('backups/env');

        if (! File::isDirectory($backupDir)) {
            File::makeDirectory($backupDir, 0755, true);
        }

        $timezone = config('app.timezone', 'UTC');
        $timestamp = now()->setTimezone($timezone)->format('Y-m-d_H-i-s');
        $backupPath = $backupDir.'/.env.backup.'.$timestamp;

        File::copy(base_path('.env'), $backupPath);

        $this->cleanupOldBackups($backupDir, 6);

        return $backupPath;
    }

    /**
     * Get ENV backups
     */
    public function getBackups(): array
    {
        $backupDir = storage_path('backups/env');

        if (! File::isDirectory($backupDir)) {
            return [];
        }

        $pattern = $backupDir.'/.env.backup.*';
        $files = glob($pattern);
        $backups = [];

        $timezone = config('app.timezone', 'UTC');
        $dateFormat = 'Y-m-d'; // ISO format for backup filenames
        $timeFormat = 'H:i:s';

        foreach ($files as $filePath) {
            $filename = basename($filePath);
            $dateStr = str_replace('.env.backup.', '', $filename);
            $dateStr = str_replace(['_', '-'], [' ', ':'], $dateStr);
            $parts = explode(' ', $dateStr);

            if (count($parts) === 2) {
                $datePart = str_replace(':', '-', $parts[0]);
                $timePart = $parts[1];
                $dateStr = $datePart.' '.$timePart;
            }

            try {
                $carbon = Date::createFromFormat('Y-m-d H:i:s', $dateStr, $timezone);
                $formattedDate = $carbon->format($dateFormat.' '.$timeFormat);
            } catch (Exception) {
                $formattedDate = date($dateFormat.' '.$timeFormat, filemtime($filePath));
            }

            $backups[] = [
                'name' => $filename,
                'size' => filesize($filePath),
                'date' => $formattedDate,
            ];
        }

        usort($backups, fn (array $a, array $b): int => strcmp($b['name'], $a['name']));

        return $backups;
    }

    /**
     * Restore ENV from backup
     */
    public function restoreBackup(string $backupName): array
    {
        $backupPath = storage_path('backups/env/'.$backupName);

        if (! File::exists($backupPath)) {
            return ['success' => false, 'error' => __('Backup file not found.')];
        }

        try {
            $this->createBackup();
            $content = File::get($backupPath);
            File::put(base_path('.env'), $content);

            return ['success' => true, 'content' => $content];
        } catch (Exception $exception) {
            return ['success' => false, 'error' => $exception->getMessage()];
        }
    }

    /**
     * Get ENV value from content string
     */
    protected function getEnvValue(string $content, string $key): ?string
    {
        $lines = explode("\n", $content);

        foreach ($lines as $line) {
            $line = trim($line);

            if (str_starts_with($line, $key.'=')) {
                [, $value] = explode('=', $line, 2);

                return trim($value, '"\'');
            }
        }

        return null;
    }

    /**
     * Cleanup old backups
     */
    protected function cleanupOldBackups(string $dir, int $keep): void
    {
        $pattern = $dir.'/.env.backup.*';
        $files = glob($pattern);

        if ($files === [] || $files === false) {
            return;
        }

        usort($files, fn ($a, $b): int => filemtime($b) - filemtime($a));

        foreach (array_slice($files, $keep) as $file) {
            @unlink($file);
        }
    }
}
