<?php

namespace Modules\CMS\Services;

use App\Support\Cache\AbstractCacheService;
use Illuminate\Database\Eloquent\Collection;
use Modules\CMS\Models\CmsPost;

/**
 * Cache service for CMS default pages URLs.
 *
 * Caches the computed URLs for default pages (home, blog, contact, about, etc.)
 * for fast frontend rendering. Automatically invalidated via SettingsObserver
 * when default page settings are modified.
 *
 * Uses two-tier caching (memory + persistent) for all cache keys.
 */
class CmsDefaultPagesCacheService extends AbstractCacheService
{
    /**
     * Cache key for default pages URLs
     */
    public const CACHE_KEY = 'cms_default_pages_urls';

    protected function getCacheKey(): string
    {
        return self::CACHE_KEY;
    }

    protected function getCacheTtl(): ?int
    {
        return null; // Cache forever - invalidated when default pages change
    }

    /**
     * Load default pages URLs from source.
     */
    protected function loadFromSource(): mixed
    {
        return $this->buildCommonUrls();
    }

    /**
     * Build common URLs array from settings.
     * Resolves page IDs to their permalink URLs.
     */
    protected function buildCommonUrls(): array
    {
        $extension = setting('seo_url_extension', '');

        // Get default page settings
        setting('cms_default_pages_home_page', '');
        $blogsPageId = setting('cms_default_pages_blogs_page', '');
        $contactPageId = setting('cms_default_pages_contact_page', '');
        $aboutPageId = setting('cms_default_pages_about_page', '');
        $privacyPageId = setting('cms_default_pages_privacy_policy_page', '');
        $termsPageId = setting('cms_default_pages_terms_of_service_page', '');
        $blogSameAsHome = setting('cms_default_pages_blog_same_as_home', false);

        // Collect all page IDs to fetch in one query
        $pageIds = array_filter([
            $contactPageId,
            $aboutPageId,
            $privacyPageId,
            $termsPageId,
        ]);

        // Fetch pages in a single query
        $pages = [];
        if ($pageIds !== []) {
            /** @var Collection<int|string, CmsPost> $pages */
            $pages = CmsPost::query()->whereIn('id', $pageIds)
                ->where('status', 'published')
                ->get(['id', 'slug', 'parent_id'])
                ->keyBy('id');
        }

        // Helper to get page URL or null
        $getPageUrl = function ($pageId) use ($pages) {
            if (empty($pageId) || ! isset($pages[$pageId])) {
                return null;
            }

            return $pages[$pageId]->permalink_url;
        };

        // Build URLs array
        $urls = [
            'home' => url('/'),
            'search' => url('/search'.$extension),
            'newsletter_subscribe' => url('/newsletter/subscribe'),
            'login' => route('login'),
            'register' => route('register'),
        ];

        // Blog URL - either same as home or separate page
        if ($blogSameAsHome) {
            $urls['blog'] = url('/');
        } elseif (! empty($blogsPageId)) {
            // Get blog page URL from the blog page itself
            $blogPage = CmsPost::query()->where('id', $blogsPageId)
                ->where('status', 'published')
                ->first(['id', 'slug', 'parent_id']);
            // @phpstan-ignore-next-line property.notFound
            $urls['blog'] = $blogPage ? $blogPage->permalink_url : url('/'.strtolower((string) setting('cms_default_pages_blog_base_url', 'blog')).$extension);
        } else {
            $urls['blog'] = url('/'.strtolower((string) setting('cms_default_pages_blog_base_url', 'blog')).$extension);
        }

        // Important pages (null if not set)
        $urls['contact'] = $getPageUrl($contactPageId);
        $urls['about'] = $getPageUrl($aboutPageId);
        $urls['privacy_policy'] = $getPageUrl($privacyPageId);
        $urls['terms_of_service'] = $getPageUrl($termsPageId);

        return $urls;
    }
}
