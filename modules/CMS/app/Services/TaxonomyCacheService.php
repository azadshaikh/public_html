<?php

namespace Modules\CMS\Services;

use App\Support\Cache\AbstractCacheService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Log;
use Modules\CMS\Models\CmsPost;

/**
 * Cache service for CMS taxonomies (categories, tags).
 *
 * Caches taxonomy lists for sidebar widgets and archive pages.
 * Automatically invalidated via CmsPostTaxonomyObserver when taxonomies change.
 *
 * Uses two-tier caching (memory + persistent) for all cache keys.
 */
class TaxonomyCacheService extends AbstractCacheService
{
    /**
     * Cache key for categories (without trashed)
     */
    public const CATEGORIES_KEY = 'cms_categories';

    /**
     * Cache key for tags (without trashed)
     */
    public const TAGS_KEY = 'cms_tags';

    /**
     * Cache key for categories (with trashed)
     */
    public const CATEGORIES_TRASHED_KEY = 'cms_categories_trashed';

    /**
     * Cache key for tags (with trashed)
     */
    public const TAGS_TRASHED_KEY = 'cms_tags_trashed';

    /**
     * Cache key for categories with counts
     */
    public const CATEGORIES_COUNTS_KEY = 'cms_categories_with_counts';

    /**
     * Cache key for tags with counts
     */
    public const TAGS_COUNTS_KEY = 'cms_tags_with_counts';

    /**
     * Get all categories as id => title array.
     *
     * @param  bool  $withTrashed  Include soft-deleted categories
     */
    public function getCategories(bool $withTrashed = false): array
    {
        if ($withTrashed) {
            return $this->remember(self::CATEGORIES_TRASHED_KEY, fn () => CmsPost::query()->where('type', 'category')
                ->withTrashed()
                ->pluck('title', 'id')
                ->toArray());
        }

        return $this->getCached();
    }

    /**
     * Get all tags as id => title array.
     *
     * @param  bool  $withTrashed  Include soft-deleted tags
     */
    public function getTags(bool $withTrashed = false): array
    {
        $cacheKey = $withTrashed ? self::TAGS_TRASHED_KEY : self::TAGS_KEY;

        return $this->remember($cacheKey, function () use ($withTrashed) {
            $query = CmsPost::query()->where('type', 'tag');

            if ($withTrashed) {
                $query->withTrashed();
            }

            return $query->pluck('title', 'id')->toArray();
        });
    }

    /**
     * Get a category by ID.
     */
    public function getCategory(int $id): ?string
    {
        return $this->getCategories()[$id] ?? null;
    }

    /**
     * Get a tag by ID.
     */
    public function getTag(int $id): ?string
    {
        return $this->getTags()[$id] ?? null;
    }

    /**
     * Get categories with post counts for widgets.
     */
    public function getCategoriesWithCounts(): array
    {
        return $this->remember(self::CATEGORIES_COUNTS_KEY, function () {
            /** @var EloquentCollection<int, CmsPost> $categories */
            $categories = CmsPost::query()->where('type', 'category')
                ->withCount(['postTerms' => function ($query): void {
                    $query->whereHas('post', function ($q): void {
                        $q->where('type', 'post')
                            ->whereNotNull('published_at')
                            ->where('published_at', '<=', now());
                    });
                },
                ])
                ->get();

            return $categories
                ->map(fn (CmsPost $cat): array => [
                    'id' => $cat->id,
                    'title' => $cat->title,
                    'slug' => $cat->slug,
                    'count' => $cat->post_terms_count,
                ])
                ->all();
        });
    }

    /**
     * Get tags with post counts for widgets.
     */
    public function getTagsWithCounts(): array
    {
        return $this->remember(self::TAGS_COUNTS_KEY, function () {
            /** @var EloquentCollection<int, CmsPost> $tags */
            $tags = CmsPost::query()->where('type', 'tag')
                ->withCount(['postTerms' => function ($query): void {
                    $query->whereHas('post', function ($q): void {
                        $q->where('type', 'post')
                            ->whereNotNull('published_at')
                            ->where('published_at', '<=', now());
                    });
                },
                ])
                ->get();

            return $tags
                ->map(fn (CmsPost $tag): array => [
                    'id' => $tag->id,
                    'title' => $tag->title,
                    'slug' => $tag->slug,
                    'count' => $tag->post_terms_count,
                ])
                ->all();
        });
    }

    /**
     * Invalidate only category caches.
     */
    public function invalidateCategories(?string $reason = null): void
    {
        $this->forget(self::CATEGORIES_KEY);
        $this->forget(self::CATEGORIES_TRASHED_KEY);
        $this->forget(self::CATEGORIES_COUNTS_KEY);

        if ($this->shouldLog() && $reason) {
            Log::debug('Categories cache invalidated', ['reason' => $reason]);
        }
    }

    /**
     * Invalidate only tag caches.
     */
    public function invalidateTags(?string $reason = null): void
    {
        $this->forget(self::TAGS_KEY);
        $this->forget(self::TAGS_TRASHED_KEY);
        $this->forget(self::TAGS_COUNTS_KEY);

        if ($this->shouldLog() && $reason) {
            Log::debug('Tags cache invalidated', ['reason' => $reason]);
        }
    }

    protected function getCacheKey(): string
    {
        return self::CATEGORIES_KEY;
    }

    protected function getRelatedCacheKeys(): array
    {
        return [
            self::TAGS_KEY,
            self::CATEGORIES_TRASHED_KEY,
            self::TAGS_TRASHED_KEY,
            self::CATEGORIES_COUNTS_KEY,
            self::TAGS_COUNTS_KEY,
        ];
    }

    protected function getCacheTtl(): ?int
    {
        return null; // Cache forever - invalidated when taxonomies change
    }

    /**
     * Load categories from database (without trashed)
     */
    protected function loadFromSource(): mixed
    {
        return CmsPost::query()->where('type', 'category')
            ->pluck('title', 'id')
            ->toArray();
    }
}
