<?php

/**
 * Environment Variable Management Helper Functions
 *
 * This file contains helper functions for reading, writing, and managing
 * environment variables with automatic cache management.
 */
use App\Jobs\RecacheApplication;
use Illuminate\Support\Facades\Log;

if (! function_exists('get_env_file_value')) {
    function get_env_file_value(string $key, $default = null)
    {
        try {
            $envFile = app()->environmentFilePath();
            if (! file_exists($envFile)) {
                return $default;
            }

            $envContent = file_get_contents($envFile);
            if (! is_string($envContent) || $envContent === '') {
                return $default;
            }

            $escapedKey = preg_quote($key, '/');
            if (preg_match(sprintf('/^%s=(.*)$/m', $escapedKey), $envContent, $matches) === 1) {
                $value = trim($matches[1]);
                if (str_contains($value, '#')) {
                    $value = trim(substr($value, 0, strpos($value, '#')));
                }

                return stripEnvQuotes($value);
            }

            return $default;
        } catch (Throwable $throwable) {
            Log::warning('Failed to read environment variable from .env file', [
                'key' => $key,
                'error' => $throwable->getMessage(),
            ]);

            return $default;
        }
    }
}

/**
 * Update runtime environment so config caching sees latest .env values.
 */
if (! function_exists('set_runtime_env_value')) {
    function set_runtime_env_value(string $key, string $value): void
    {
        $normalizedValue = stripEnvQuotes($value);

        // Avoid process-level env mutation in FPM workers to prevent stale values
        // leaking across requests after .env updates.
        $_ENV[$key] = $normalizedValue;
        $_SERVER[$key] = $normalizedValue;
    }
}

/**
 * Remove a runtime environment variable.
 */
if (! function_exists('unset_runtime_env_value')) {
    function unset_runtime_env_value(string $key): void
    {
        // Keep runtime cleanup request-scoped.
        unset($_ENV[$key], $_SERVER[$key]);
    }
}

/**
 * Set environment variable value
 *
 * @param  string  $key  Environment variable key
 * @param  string  $value  Environment variable value
 * @param  bool  $recache  Whether to trigger cache rebuild (default: true, set false for bulk operations)
 * @return bool True on success, false on failure
 */
if (! function_exists('set_env_value')) {
    function set_env_value(string $key, string $value, bool $recache = true): bool
    {
        try {
            $envFile = app()->environmentFilePath();

            // Check if .env file exists and is writable
            if (! file_exists($envFile)) {
                Log::error('Environment file not found', ['file' => $envFile]);

                return false;
            }

            if (! is_writable($envFile)) {
                Log::error('Environment file is not writable', ['file' => $envFile]);

                return false;
            }

            // Read current content
            $envContent = file_get_contents($envFile);
            if ($envContent === false) {
                Log::error('Failed to read environment file', ['file' => $envFile]);

                return false;
            }

            // Quote the value if it contains spaces or special characters
            $quotedValue = $value;
            if (preg_match('/[\s#"\'\\\\]/', $value)) {
                // Escape any existing quotes and wrap in double quotes
                $quotedValue = '"'.addslashes($value).'"';
            }

            // Escape special characters in the key for regex
            $escapedKey = preg_quote($key, '/');

            // Check if key exists and update, or add new key
            if (preg_match(sprintf('/^%s=.*$/m', $escapedKey), $envContent)) {
                $envContent = preg_replace(sprintf('/^%s=.*$/m', $escapedKey), sprintf('%s=%s', $key, $quotedValue), $envContent);
            } else {
                $envContent .= sprintf('%s%s=%s', PHP_EOL, $key, $quotedValue);
            }

            // Write to file
            if (file_put_contents($envFile, $envContent) === false) {
                Log::error('Failed to write to environment file', ['file' => $envFile]);

                return false;
            }

            set_runtime_env_value($key, $value);

            // Dispatch cache recache if requested (async job)
            if ($recache) {
                dispatch(new RecacheApplication('Single env value update: '.$key, [$key => $value]));
            }

            return true;
        } catch (Exception $exception) {
            Log::error('Failed to set environment variable', [
                'key' => $key,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}

/**
 * Set multiple environment variables at once (bulk operation)
 *
 * This is more efficient than calling set_env_value multiple times
 * as it only writes to the file once and dispatches a single cache job.
 *
 * @param  array  $values  Associative array of key => value pairs
 * @param  bool  $recache  Whether to trigger cache rebuild (default: true)
 * @return bool True on success, false on failure
 */
if (! function_exists('set_env_values_bulk')) {
    function set_env_values_bulk(array $values, bool $recache = true): bool
    {
        if ($values === []) {
            return true;
        }

        try {
            $envFile = app()->environmentFilePath();

            // Check if .env file exists and is writable
            if (! file_exists($envFile)) {
                Log::error('Environment file not found', ['file' => $envFile]);

                return false;
            }

            if (! is_writable($envFile)) {
                Log::error('Environment file is not writable', ['file' => $envFile]);

                return false;
            }

            // Read current content
            $envContent = file_get_contents($envFile);
            if ($envContent === false) {
                Log::error('Failed to read environment file', ['file' => $envFile]);

                return false;
            }

            // Process each key-value pair
            foreach ($values as $key => $value) {
                // Quote the value if it contains spaces or special characters
                $quotedValue = (string) $value;
                if (preg_match('/[\s#"\'\\\\]/', $quotedValue)) {
                    $quotedValue = '"'.addslashes($quotedValue).'"';
                }

                // Escape special characters in the key for regex
                $escapedKey = preg_quote((string) $key, '/');

                // Check if key exists and update, or add new key
                if (preg_match(sprintf('/^%s=.*$/m', $escapedKey), $envContent)) {
                    $envContent = preg_replace(sprintf('/^%s=.*$/m', $escapedKey), sprintf('%s=%s', $key, $quotedValue), $envContent);
                } else {
                    $envContent .= sprintf('%s%s=%s', PHP_EOL, $key, $quotedValue);
                }
            }

            // Write to file once
            if (file_put_contents($envFile, $envContent) === false) {
                Log::error('Failed to write to environment file', ['file' => $envFile]);

                return false;
            }

            foreach ($values as $key => $value) {
                set_runtime_env_value($key, (string) $value);
            }

            // Dispatch cache recache if requested (async job)
            if ($recache) {
                dispatch(new RecacheApplication('Bulk env update: '.count($values).' values', $values));
            }

            return true;
        } catch (Exception $exception) {
            Log::error('Failed to set environment variables in bulk', [
                'keys' => array_keys($values),
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}

/**
 * Get environment variable value
 *
 * @param  string  $key  Environment variable key
 * @param  mixed  $default  Default value if key not found
 * @return mixed Environment variable value or default
 */
if (! function_exists('get_env_value')) {
    function get_env_value(string $key, $default = null)
    {
        try {
            // Read directly from superglobals and .env file to avoid env() outside config
            $value = $_ENV[$key] ?? $_SERVER[$key] ?? get_env_file_value($key);

            // If still null, return the default
            return $value ?? $default;
        } catch (Exception $exception) {
            Log::error('Failed to get environment variable', [
                'key' => $key,
                'error' => $exception->getMessage(),
            ]);

            return $default;
        }
    }
}

/**
 * Strip surrounding quotes from environment variable values
 *
 * @param  string  $value  The value to strip quotes from
 * @return string Value with quotes removed if they were present
 */
if (! function_exists('stripEnvQuotes')) {
    function stripEnvQuotes(string $value): string
    {
        $value = trim($value);

        // Check for double quotes
        if (strlen($value) >= 2 && $value[0] === '"' && $value[strlen($value) - 1] === '"') {
            return substr($value, 1, -1);
        }

        // Check for single quotes
        if (strlen($value) >= 2 && $value[0] === "'" && $value[strlen($value) - 1] === "'") {
            return substr($value, 1, -1);
        }

        return $value;
    }
}
