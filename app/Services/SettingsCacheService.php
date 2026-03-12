<?php

namespace App\Services;

use App\Models\Settings;
use App\Support\Cache\AbstractCacheService;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Centralized settings cache management service.
 *
 * This service provides a single point of control for all settings caching
 * operations, ensuring consistent cache keys, TTLs, and invalidation logic.
 *
 * Uses two-tier caching (memory + persistent) via AbstractCacheService.
 */
class SettingsCacheService extends AbstractCacheService
{
    /**
     * Cache key for the main settings collection
     */
    public const CACHE_KEY = 'app_settings';

    /**
     * Cache key for table existence check
     */
    public const TABLE_EXISTS_KEY = 'settings_table_exists';

    /**
     * Flag to track if table exists check has been performed
     */
    private static ?bool $tableExists = null;

    /**
     * Get a setting value by key
     */
    public function get(string $key, mixed $default = ''): mixed
    {
        // Check if table exists first
        if (! $this->tableExists()) {
            return $default;
        }

        $settings = $this->getCached();

        // Special handling for site_title: default to app.name config
        if ($key === 'site_title' && ! isset($settings[$key])) {
            return config('app.name', $default);
        }

        return $settings[$key] ?? $default;
    }

    /**
     * Get all settings as a flat array
     */
    public function all(): array
    {
        if (! $this->tableExists()) {
            return [];
        }

        return $this->getCached();
    }

    /**
     * Check if a setting exists
     */
    public function has(string $key): bool
    {
        if (! $this->tableExists()) {
            return false;
        }

        $settings = $this->getCached();

        return isset($settings[$key]);
    }

    /**
     * Refresh the settings cache.
     * Clears and rebuilds the cache from database.
     */
    public function refresh(?string $reason = null): mixed
    {
        $this->invalidate($reason ?? 'cache refresh');

        return $this->all();
    }

    /**
     * Warm up the cache by preloading settings
     */
    public function warmUp(): mixed
    {
        return $this->refresh('cache warm up');
    }

    /**
     * Invalidate table exists cache (useful during migrations)
     */
    public function invalidateTableExistsCache(): void
    {
        $this->forget(self::TABLE_EXISTS_KEY);
        self::$tableExists = null;
    }

    /**
     * Override invalidate to also clear the tableExists static cache
     */
    public function invalidate(?string $reason = null): void
    {
        parent::invalidate($reason);
        self::$tableExists = null;
    }

    /**
     * Get cache statistics for debugging
     */
    public function getCacheStats(): array
    {
        $stats = parent::getCacheStats();
        $stats['table_exists'] = self::$tableExists;

        return $stats;
    }

    protected function getCacheKey(): string
    {
        return self::CACHE_KEY;
    }

    protected function getCacheTtl(): int
    {
        return 86400; // 24 hours
    }

    protected function getRelatedCacheKeys(): array
    {
        return [self::TABLE_EXISTS_KEY];
    }

    /**
     * Load all settings from database and format them
     */
    protected function loadFromSource(): mixed
    {
        $flatSettings = [];

        try {
            $allSettings = Settings::all();

            foreach ($allSettings as $setting) {
                // Create flat key for grouped settings (group_key format)
                if ($setting->group) {
                    $flatKey = $setting->group.'_'.$setting->key;
                    $flatSettings[$flatKey] = $setting->cast_value;
                }

                // Also store original key for direct access
                $flatSettings[$setting->key] = $setting->cast_value;
            }
        } catch (Exception $exception) {
            Log::error('Failed to load settings from database', [
                'error' => $exception->getMessage(),
            ]);
        }

        return $flatSettings;
    }

    /**
     * Check if the settings table exists
     */
    private function tableExists(): bool
    {
        if (self::$tableExists !== null) {
            return self::$tableExists;
        }

        self::$tableExists = $this->remember(self::TABLE_EXISTS_KEY, fn () => Schema::hasTable('settings'));

        return self::$tableExists;
    }
}
