<?php

namespace App\Support\Cache;

use App\Services\SettingsCacheService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Abstract base class for cache services.
 *
 * Provides a standardized, robust approach to caching with:
 * - Two-tier caching (memory + persistent)
 * - Automatic invalidation support via Model Observers
 * - Debug logging
 * - Cache statistics
 *
 * Usage:
 * 1. Extend this class
 * 2. Implement getCacheKey() and loadFromSource()
 * 3. Create an Observer that calls invalidate() on model changes
 * 4. Register the service as a singleton in AppServiceProvider
 *
 * @see SettingsCacheService for example implementation
 */
abstract class AbstractCacheService
{
    /**
     * Default cache TTL in seconds (24 hours)
     */
    protected const DEFAULT_TTL = 86400;

    /**
     * In-memory cache for the current request.
     * Keyed by cache key to support multiple cache entries per service.
     */
    private static array $memoryCache = [];

    /**
     * Get cached data using two-tier caching.
     *
     * Retrieval order:
     * 1. Memory cache (fastest, request-scoped)
     * 2. Persistent cache (Laravel cache driver)
     * 3. Source (database, file, etc.)
     */
    public function getCached(): mixed
    {
        return $this->remember($this->getCacheKey(), fn (): mixed => $this->loadFromSource());
    }

    /**
     * Alias for getCached()
     */
    public function all(): mixed
    {
        return $this->getCached();
    }

    /**
     * Invalidate all caches for this service.
     *
     * @param  string|null  $reason  Optional reason for logging
     */
    public function invalidate(?string $reason = null): void
    {
        // Clear primary cache
        $this->forget($this->getCacheKey());

        // Clear related caches
        foreach ($this->getRelatedCacheKeys() as $key) {
            $this->forget($key);
        }

        // Log invalidation
        if ($this->shouldLog()) {
            Log::debug($this->getServiceName().' cache invalidated', [
                'cache_key' => $this->getCacheKey(),
                'related_keys' => $this->getRelatedCacheKeys(),
                'reason' => $reason ?? 'manual invalidation',
            ]);
        }
    }

    /**
     * Refresh the cache by clearing and rebuilding it.
     */
    public function refresh(?string $reason = null): mixed
    {
        $this->invalidate($reason ?? 'cache refresh');

        return $this->getCached();
    }

    /**
     * Clear only the memory cache for this service.
     */
    public function clearMemoryCache(): void
    {
        unset(self::$memoryCache[$this->getCacheKey()]);
        foreach ($this->getRelatedCacheKeys() as $key) {
            unset(self::$memoryCache[$key]);
        }
    }

    /**
     * Clear all memory caches across all services.
     * Useful for testing.
     */
    public static function clearAllMemoryCaches(): void
    {
        self::$memoryCache = [];
    }

    /**
     * Warm up the cache by preloading data.
     */
    public function warmUp(): mixed
    {
        return $this->refresh('cache warm up');
    }

    /**
     * Check if persistent cache exists.
     */
    public function isCached(): bool
    {
        return Cache::has($this->getCacheKey());
    }

    /**
     * Get cache statistics for debugging.
     */
    public function getCacheStats(): array
    {
        $memoryKeys = array_keys(self::$memoryCache);

        return [
            'service' => $this->getServiceName(),
            'cache_key' => $this->getCacheKey(),
            'cache_ttl' => $this->getCacheTtl(),
            'memory_cache_enabled' => $this->useMemoryCache(),
            'memory_cache_keys' => $memoryKeys,
            'persistent_cache_exists' => Cache::has($this->getCacheKey()),
            'related_keys' => $this->getRelatedCacheKeys(),
        ];
    }

    /**
     * Get a specific item from cached collection by key.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $cached = $this->getCached();

        if (is_array($cached)) {
            return $cached[$key] ?? $default;
        }

        if ($cached instanceof Collection) {
            return $cached->get($key, $default);
        }

        return $default;
    }

    /**
     * Check if a key exists in cached collection.
     */
    public function has(string $key): bool
    {
        $cached = $this->getCached();

        if (is_array($cached)) {
            return isset($cached[$key]);
        }

        if ($cached instanceof Collection) {
            return $cached->has($key);
        }

        return false;
    }

    /**
     * Get the primary cache key for this service.
     */
    abstract protected function getCacheKey(): string;

    /**
     * Load data from the source (database, file, API, etc.)
     */
    abstract protected function loadFromSource(): mixed;

    /**
     * Get the cache TTL in seconds.
     * Override to customize. Return null for forever caching.
     */
    protected function getCacheTtl(): ?int
    {
        return self::DEFAULT_TTL;
    }

    /**
     * Get additional cache keys to invalidate.
     * Override to add related cache keys.
     */
    protected function getRelatedCacheKeys(): array
    {
        return [];
    }

    /**
     * Get the service name for logging.
     */
    protected function getServiceName(): string
    {
        return class_basename(static::class);
    }

    /**
     * Whether to use memory caching.
     * Reads from config, can be overridden per-service.
     */
    protected function useMemoryCache(): bool
    {
        return config('cache.use_memory_cache', true);
    }

    /**
     * Whether to log cache operations in debug mode.
     */
    protected function shouldLog(): bool
    {
        return config('app.debug', false);
    }

    /**
     * Remember a value with two-tier caching (memory + persistent).
     *
     * Use this method for sub-caches when a service manages multiple cache keys.
     * Example: TaxonomyCacheService has separate keys for categories and tags.
     *
     * @param  string  $cacheKey  The persistent cache key
     * @param  callable  $callback  Callback to load data from source
     * @param  int|null  $ttl  Optional TTL override (null = forever)
     */
    protected function remember(string $cacheKey, callable $callback, ?int $ttl = null): mixed
    {
        // Try memory cache first
        if ($this->useMemoryCache() && array_key_exists($cacheKey, self::$memoryCache)) {
            return self::$memoryCache[$cacheKey];
        }

        // Load from persistent cache or source
        $effectiveTtl = $ttl ?? $this->getCacheTtl();

        if ($effectiveTtl === null) {
            // Cache forever
            $data = Cache::rememberForever($cacheKey, $callback);
        } else {
            $data = Cache::remember($cacheKey, $effectiveTtl, $callback);
        }

        // Store in memory cache
        if ($this->useMemoryCache()) {
            self::$memoryCache[$cacheKey] = $data;
        }

        return $data;
    }

    /**
     * Forget a specific cache key from both tiers.
     *
     * @param  string  $cacheKey  The cache key to forget
     */
    protected function forget(string $cacheKey): void
    {
        Cache::forget($cacheKey);
        unset(self::$memoryCache[$cacheKey]);
    }
}
