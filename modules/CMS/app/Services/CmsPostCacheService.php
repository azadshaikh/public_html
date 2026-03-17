<?php

namespace Modules\CMS\Services;

use App\Support\Cache\AbstractCacheService;
use Closure;
use Illuminate\Support\Collection;
use Modules\CMS\Models\CmsPost;

/**
 * Cache service for CMS posts frontend queries.
 *
 * Caches popular posts, categories, and tags for fast frontend rendering.
 * Automatically invalidated via CmsPostObserver when posts are modified.
 *
 * Uses two-tier caching (memory + persistent) for all cache keys.
 */
class CmsPostCacheService extends AbstractCacheService
{
    /**
     * Content types used for cache keys.
     */
    public const TYPES = ['post', 'page', 'category', 'tag'];

    /**
     * Cache key prefix for popular posts
     */
    public const POPULAR_POSTS_PREFIX = 'popular_posts_';

    /**
     * Cache key prefix for categories
     */
    public const CATEGORIES_PREFIX = 'categories_';

    /**
     * Cache key prefix for tags
     */
    public const TAGS_PREFIX = 'tags_';

    /**
     * Default widget limits used across the CMS.
     * Keep in sync with widgets/templates that use these caches.
     */
    public const POPULAR_LIMITS = [3, 5, 10];

    public const CATEGORY_LIMITS = [6, 10, 20];

    public const TAG_LIMITS = [6, 10, 20];

    /**
     * Get popular posts by type.
     */
    public function getPopularPosts(string $type = 'post', int $limit = 3): Collection
    {
        $cacheKey = self::POPULAR_POSTS_PREFIX.$type.'_'.$limit;

        return $this->rememberPosts($cacheKey, fn () => CmsPost::query()->where('type', $type)
            ->where('status', 'published')
            ->latest('published_at')
            ->limit($limit)
            ->get());
    }

    /**
     * Get categories with post counts.
     */
    public function getCategories(string $type = 'category', int $limit = 6): Collection
    {
        $cacheKey = self::CATEGORIES_PREFIX.$type.'_'.$limit;

        return $this->rememberPosts($cacheKey, fn () => CmsPost::query()->withCount('publishedTermPosts as post_count')
            ->where('status', 'published')
            ->where('type', $type)
            ->orderBy('post_count', 'desc')
            ->limit($limit)
            ->get());
    }

    /**
     * Get tags with post counts.
     */
    public function getTags(string $type = 'tag', int $limit = 6): Collection
    {
        $cacheKey = self::TAGS_PREFIX.$type.'_'.$limit;

        return $this->rememberPosts($cacheKey, fn () => CmsPost::query()->withCount('publishedTermPosts as post_count')
            ->where('status', 'published')
            ->where('type', $type)
            ->orderBy('post_count', 'desc')
            ->limit($limit)
            ->get());
    }

    /**
     * Invalidate popular posts cache for a type.
     */
    public function invalidatePopularPosts(?string $type = null): void
    {
        if ($type) {
            foreach (self::POPULAR_LIMITS as $limit) {
                $this->forget(self::POPULAR_POSTS_PREFIX.$type.'_'.$limit);
            }
        }

        // Clear memory cache
        $this->clearMemoryCache();
    }

    /**
     * Invalidate categories cache for a type.
     */
    public function invalidateCategories(?string $type = null): void
    {
        if ($type) {
            foreach (self::CATEGORY_LIMITS as $limit) {
                $this->forget(self::CATEGORIES_PREFIX.$type.'_'.$limit);
            }
        }

        $this->clearMemoryCache();
    }

    /**
     * Invalidate tags cache for a type.
     */
    public function invalidateTags(?string $type = null): void
    {
        if ($type) {
            foreach (self::TAG_LIMITS as $limit) {
                $this->forget(self::TAGS_PREFIX.$type.'_'.$limit);
            }
        }

        $this->clearMemoryCache();
    }

    /**
     * Invalidate all caches.
     */
    public function invalidate(?string $reason = null): void
    {
        $this->clearAll();
    }

    /**
     * Clear all cached entries for all types and limits.
     */
    public function clearAll(): void
    {
        foreach (self::TYPES as $type) {
            foreach (self::POPULAR_LIMITS as $limit) {
                $this->forget(self::POPULAR_POSTS_PREFIX.$type.'_'.$limit);
            }

            foreach (self::CATEGORY_LIMITS as $limit) {
                $this->forget(self::CATEGORIES_PREFIX.$type.'_'.$limit);
            }

            foreach (self::TAG_LIMITS as $limit) {
                $this->forget(self::TAGS_PREFIX.$type.'_'.$limit);
            }
        }

        $this->clearMemoryCache();
    }

    /**
     * Default cache key (not used directly, but required by abstract)
     */
    protected function getCacheKey(): string
    {
        return 'cms_posts_cache';
    }

    protected function getCacheTtl(): ?int
    {
        return null; // Cache forever - invalidated when posts change
    }

    /**
     * Not used directly - we use specific methods instead.
     */
    protected function loadFromSource(): mixed
    {
        return null;
    }

    private function rememberPosts(string $cacheKey, Closure $loader): Collection
    {
        $cached = $this->remember($cacheKey, fn (): array => $this->serializePosts($loader()));

        if (is_array($cached)) {
            return $this->hydratePosts($cached);
        }

        $this->forget($cacheKey);

        return $loader();
    }

    private function serializePosts(Collection $posts): array
    {
        return $posts->map(fn (CmsPost $post): array => $post->getAttributes())->all();
    }

    private function hydratePosts(array $payload): Collection
    {
        return CmsPost::hydrate($payload);
    }
}
