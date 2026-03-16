<?php

namespace Modules\CMS\Services;

use App\Support\Cache\AbstractCacheService;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\CMS\Models\Redirection;

/**
 * Cache service for CMS redirections.
 *
 * Caches active redirections for fast URL matching on every request.
 * Automatically invalidated via RedirectionObserver when redirections are modified.
 *
 * Uses two-tier caching (memory + persistent) for all cache keys.
 */
class RedirectionCacheService extends AbstractCacheService
{
    /**
     * Cache key for all redirections
     */
    public const CACHE_KEY = 'cms_redirections';

    /**
     * Cache key for active redirections only
     */
    public const ACTIVE_CACHE_KEY = 'cms_redirections_active';

    /**
     * Cache key for redirections table existence check.
     */
    private const string TABLE_EXISTS_CACHE_KEY = 'cms_redirections_table_exists';

    /**
     * Get active (non-expired, status=active) redirections.
     * Most commonly used for URL matching.
     */
    public function getActive(): Collection
    {
        if (! $this->isRedirectionsAvailable()) {
            return collect();
        }

        $value = $this->remember(
            self::ACTIVE_CACHE_KEY,
            fn () => Redirection::getActiveRedirections()
        );

        if ($value instanceof Collection) {
            return $value;
        }

        // Cache value is corrupted / from an older format (e.g. array). Heal it.
        $this->forget(self::ACTIVE_CACHE_KEY);

        $fresh = Redirection::getActiveRedirections();

        return $fresh instanceof Collection ? $fresh : collect($fresh);
    }

    /**
     * Find a redirection by source URL.
     * Checks exact match first, then wildcard, then regex.
     */
    public function findBySourceUrl(string $url): ?Redirection
    {
        $redirections = $this->getActive();

        // Normalize URL
        $url = '/'.ltrim($url, '/');

        // Check exact matches first
        $exactMatch = $redirections
            ->where('match_type', 'exact')
            ->where('source_url', $url)
            ->first();

        if ($exactMatch) {
            return $exactMatch;
        }

        // Check wildcard matches
        foreach ($redirections->where('match_type', 'wildcard') as $redirect) {
            $pattern = str_replace('*', '.*', preg_quote((string) $redirect->source_url, '/'));
            if (preg_match('/^'.$pattern.'$/i', $url)) {
                return $redirect;
            }
        }

        // Check regex matches
        foreach ($redirections->where('match_type', 'regex') as $redirect) {
            try {
                if (preg_match($redirect->source_url, $url)) {
                    return $redirect;
                }
            } catch (Exception) {
                // Invalid regex, skip
                continue;
            }
        }

        return null;
    }

    /**
     * Increment hit counter for a redirection.
     * Does NOT invalidate cache since hits are non-critical data.
     */
    public function recordHit(Redirection $redirection): void
    {
        // @phpstan-ignore-next-line method.protected
        $redirection->incrementQuietly('hits');
        $redirection->updateQuietly(['last_hit_at' => now()]);
    }

    protected function getCacheKey(): string
    {
        return self::CACHE_KEY;
    }

    protected function getRelatedCacheKeys(): array
    {
        return [self::ACTIVE_CACHE_KEY];
    }

    protected function getCacheTtl(): ?int
    {
        return null; // Cache forever - invalidated when redirections change
    }

    /**
     * Load all redirections from database
     */
    protected function loadFromSource(): mixed
    {
        if (! $this->isRedirectionsAvailable()) {
            return collect();
        }

        return Redirection::query()
            ->orderBy('match_type')
            ->orderByDesc(DB::raw('LENGTH(source_url)'))
            ->get();
    }

    protected function isRedirectionsAvailable(): bool
    {
        if (! active_modules('cms')) {
            return false;
        }

        static $tableExists = null;
        if ($tableExists !== null) {
            return $tableExists;
        }

        $tableExists = (bool) Cache::remember(
            self::TABLE_EXISTS_CACHE_KEY,
            now()->addMinutes(10),
            fn () => Schema::hasTable('cms_redirections')
        );

        return $tableExists;
    }
}
