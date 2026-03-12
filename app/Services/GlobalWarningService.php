<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Global Warning Service
 *
 * Collects system-wide warnings from modules to display in the header.
 * Uses per-request caching to avoid performance impact.
 */
class GlobalWarningService
{
    /**
     * Registered warnings (per-request cache)
     */
    protected static ?array $warnings = null;

    /**
     * Warning collectors (closures that check for warnings)
     */
    protected static array $collectors = [];

    /**
     * Register a warning collector callback.
     * The callback should return null or an array with: title, message, type, action (optional)
     */
    public static function registerCollector(string $key, callable $collector): void
    {
        static::$collectors[$key] = $collector;
    }

    /**
     * Get all warnings (cached per request for performance)
     */
    public static function getAll(): array
    {
        // Return cached warnings if already collected this request
        if (static::$warnings !== null) {
            return static::$warnings;
        }

        static::$warnings = [];

        // Run each collector and gather warnings
        foreach (static::$collectors as $key => $collector) {
            try {
                $result = $collector();
                if ($result !== null && is_array($result)) {
                    static::$warnings[$key] = $result;
                }
            } catch (Throwable $e) {
                // Silently fail - don't let warning collection break the app
                Log::warning(sprintf("Global warning collector '%s' failed: ", $key).$e->getMessage());
            }
        }

        return static::$warnings;
    }

    /**
     * Check if there are any warnings
     */
    public static function hasWarnings(): bool
    {
        return static::getAll() !== [];
    }

    /**
     * Get warning count
     */
    public static function count(): int
    {
        return count(static::getAll());
    }

    /**
     * Clear cached warnings (useful for testing)
     */
    public static function clearCache(): void
    {
        static::$warnings = null;
    }
}
