<?php

namespace App\Services\LaravelTools;

use Illuminate\Support\Facades\File;

class ConfigService
{
    /**
     * Sensitive config keys to mask
     */
    protected array $sensitivePatterns = [
        'password', 'secret', 'key', 'token', 'api_key', 'apikey',
        'private', 'credential', 'auth', 'encryption',
    ];

    /**
     * Get list of config files
     */
    public function getFiles(): array
    {
        $configPath = config_path();
        $files = File::files($configPath);
        $configFiles = [];

        foreach ($files as $file) {
            if ($file->getExtension() === 'php') {
                $configFiles[] = [
                    'name' => $file->getFilenameWithoutExtension(),
                    'path' => $file->getPathname(),
                ];
            }
        }

        usort($configFiles, fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return $configFiles;
    }

    /**
     * Get config values for a specific file
     */
    public function getValues(string $file): array
    {
        $configFiles = collect($this->getFiles())->pluck('name')->toArray();

        if (! in_array($file, $configFiles)) {
            return [
                'success' => false,
                'error' => __('Configuration file not found.'),
            ];
        }

        $config = config($file);
        $masked = $this->maskSensitive($config);

        return [
            'success' => true,
            'config' => $masked,
        ];
    }

    /**
     * Mask sensitive config values
     */
    public function maskSensitive(mixed $config, string $prefix = ''): mixed
    {
        if (! is_array($config)) {
            if ($this->isSensitiveKey($prefix)) {
                return is_string($config) && $config !== '' ? '********' : $config;
            }

            return $config;
        }

        $masked = [];
        foreach ($config as $key => $value) {
            $fullKey = $prefix !== '' && $prefix !== '0' ? sprintf('%s.%s', $prefix, $key) : $key;
            $masked[$key] = $this->maskSensitive($value, $fullKey);
        }

        return $masked;
    }

    /**
     * Check if a key is sensitive
     */
    protected function isSensitiveKey(string $key): bool
    {
        $key = strtolower($key);
        foreach ($this->sensitivePatterns as $pattern) {
            if (str_contains($key, (string) $pattern)) {
                return true;
            }
        }

        return false;
    }
}
