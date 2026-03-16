<?php

namespace Modules\CMS\Services;

use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Auth;
use Modules\CMS\Models\CmsPost;

class CMSService
{
    /**
     * Get page data with related content for theme rendering
     * Optimized to prevent N+1 queries with proper eager loading
     */
    public function getPageDataForTheme(CmsPost $page, bool $includeDrafts = false): array
    {
        $query = CmsPost::query();

        if (! $includeDrafts) {
            $query = $query
                ->where('status', 'published')
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now());
        } else {
            $query = $query->where('created_by', Auth::id());
        }

        $query->where('type', $page->type);
        // Add featuredImage to prevent N+1 queries
        $query->with(['author:id,first_name,last_name,avatar', 'createdBy:id,first_name,last_name', 'category', 'featuredImage']);

        // Get previous and next pages
        $previousPage = $query->clone()
            ->where('id', '<', $page->id)
            ->orderBy('id', 'desc')
            ->first();

        $nextPage = $query->clone()
            ->where('id', '>', $page->id)
            ->orderBy('id', 'asc')
            ->first();

        // Get recent posts for sidebar with eager loading
        $recentPostsLimit = theme_get_option('show_latest_posts_limit', 2);
        $recentPosts = CmsPost::query()
            ->with(['author:id,first_name,last_name,avatar', 'featuredImage'])
            ->where('type', 'post')
            ->where('id', '!=', $page->id);

        if (! $includeDrafts) {
            $recentPosts = $recentPosts
                ->where('status', 'published')
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now());
        }

        $recentPosts = $recentPosts->orderByDesc('is_featured')
            ->latest('published_at')
            ->take($recentPostsLimit)
            ->get();

        // Get cached categories and tags
        $taxonomyCache = resolve(TaxonomyCacheService::class);
        $categories = $taxonomyCache->getCategories();
        $tags = $taxonomyCache->getTags();

        $return_data = [
            'previousPage' => $previousPage,
            'nextPage' => $nextPage,
            'recentPosts' => $recentPosts,
            'categories' => $categories, // Add your categories logic here
            'tags' => $tags, // Add your tags logic here
        ];

        if ($page->type === 'post') {
            $return_data['post'] = $page;
        } else {
            $return_data['page'] = $page;
        }

        return $return_data;
    }

    /**
     * Get homepage data for theme rendering
     * Optimized with proper eager loading to prevent N+1 queries
     */
    public function getHomepageData(?CmsPost $page = null): array
    {
        // Get pages for homepage with eager loading
        $pages = CmsPost::query()->published()
            ->with(['author:id,first_name,last_name,avatar', 'featuredImage'])
            ->where('type', 'page')
            ->latest('published_at')
            ->paginate(10);

        // Get recent posts for sidebar with eager loading
        $recentPostsLimit = theme_get_option('show_latest_posts_limit', 5);

        $recentPosts = CmsPost::query()->published()
            ->with(['author:id,first_name,last_name,avatar', 'categories:id,title', 'featuredImage'])
            ->where('type', 'post')
            ->orderByDesc('is_featured')
            ->latest('published_at')
            ->take($recentPostsLimit)
            ->get();

        $taxonomyCache = resolve(TaxonomyCacheService::class);
        $categories = $taxonomyCache->getCategories(withTrashed: true);
        $tags = $taxonomyCache->getTags(withTrashed: true);

        return [
            'page' => $page ?? null,
            'pages' => $pages,
            'recentPosts' => $recentPosts,
            'categories' => $categories,
            'tags' => $tags,
        ];
    }

    /**
     * Get archive data for theme rendering
     * Optimized with proper eager loading to prevent N+1 queries
     */
    public function getArchiveData(?CmsPost $category = null): array
    {
        $query = CmsPost::query()->published()
            ->where('cms_posts.type', 'post')
            ->with(['author:id,first_name,last_name,avatar', 'categories:id,title', 'category', 'featuredImage'])
            ->select('cms_posts.*');

        if (! is_null($category) && ! in_array($category->type, ['post', 'page'])) {
            $category_ids = CmsPost::getChildrenIds($category->id);
            $category_ids[] = $category->id;
            // Filter by category if your Page model has categories
            $query->join('cms_post_terms', 'cms_posts.id', '=', 'cms_post_terms.post_id')
                ->whereIn('cms_post_terms.term_id', $category_ids);
        }

        if (request()->has('q')) {
            $searchQuery = (string) request()->query('q');
            $escapedSearchQuery = $this->escapeLikeQuery($searchQuery);
            $query->where(function ($q) use ($escapedSearchQuery): void {
                $q->where('cms_posts.title', 'ilike', sprintf('%%%s%%', $escapedSearchQuery))
                    ->orWhere('cms_posts.content', 'ilike', sprintf('%%%s%%', $escapedSearchQuery));
            });
        }

        $short_order = 'latest';
        if (request()->has('sort')) {
            $short_order = request()->query('sort');
        }

        if ($short_order === 'title') {
            $query->orderByDesc('cms_posts.is_featured');
            $query->orderBy('cms_posts.title', 'asc');
        } elseif ($short_order === 'oldest') {
            $query->orderByDesc('cms_posts.is_featured');
            $query->oldest('cms_posts.published_at');
        } else {
            $query->orderByDesc('cms_posts.is_featured');
            $query->latest('cms_posts.published_at');
        }

        $pages = $query->paginate(10);

        $taxonomyCache = resolve(TaxonomyCacheService::class);
        $categories = $taxonomyCache->getCategories(withTrashed: true);
        $tags = $taxonomyCache->getTags(withTrashed: true);

        return [
            'posts' => $pages,
            'category' => $category,
            'categories' => $categories,
            'tags' => $tags,
        ];
    }

    /**
     * Get search results data
     * Optimized with proper eager loading to prevent N+1 queries
     */
    public function getSearchResults(?string $query = null): array
    {
        $escapedQuery = $this->escapeLikeQuery($query ?? '');

        $posts = CmsPost::query()->published()
            ->with(['author:id,first_name,last_name,avatar', 'featuredImage'])
            ->where('type', 'post')
            ->where(function ($q) use ($escapedQuery): void {
                $q->where('title', 'ilike', sprintf('%%%s%%', $escapedQuery))
                    ->orWhere('content', 'ilike', sprintf('%%%s%%', $escapedQuery));
            })
            ->orderByDesc('is_featured')
            ->latest('published_at')
            ->paginate(10);

        $taxonomyCache = resolve(TaxonomyCacheService::class);
        $categories = $taxonomyCache->getCategories(withTrashed: true);
        $tags = $taxonomyCache->getTags(withTrashed: true);

        return [
            'posts' => $posts,
            'query' => $query,
            'categories' => $categories,
            'tags' => $tags,
        ];
    }

    /**
     * Extract content from HTML for page editing.
     *
     * The Astero builder sends just the innerHTML of the [data-astero-enabled] element,
     * so we only need extraction if a full HTML document is provided.
     */
    public function extractPageContent(string $content): string
    {
        // If content doesn't look like a full HTML document, return as-is
        // The builder sends just the innerHTML of the editable area
        if (! str_contains($content, '<html') && ! str_contains($content, '<!DOCTYPE')) {
            return $content;
        }

        // Extract content from the editable content div if full HTML is provided
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML($content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        // Look for data-astero-enabled first (current builder attribute)
        $xpath = new DOMXPath($dom);
        $pageContentNodes = $xpath->query('//*[@data-astero-enabled]');

        // Fallback: data-astero-editable="true" (legacy)
        if ($pageContentNodes->length === 0) {
            $pageContentNodes = $xpath->query('//div[@data-astero-editable="true"]');
        }

        // Fallback to class-based selection if data attribute not found
        if ($pageContentNodes->length === 0) {
            $pageContentNodes = $xpath->query('//div[contains(@class, "page-content")]');
        }

        // Additional fallback for post-content class (legacy templates)
        if ($pageContentNodes->length === 0) {
            $pageContentNodes = $xpath->query('//div[contains(@class, "post-content")]');
        }

        if ($pageContentNodes->length > 0) {
            $pageContentDiv = $pageContentNodes->item(0);
            $extractedContent = '';
            foreach ($pageContentDiv->childNodes as $child) {
                $extractedContent .= $dom->saveHTML($child);
            }

            return $extractedContent;
        }

        // Fallback to body extraction if page-content div not found
        $body = $dom->getElementsByTagName('body')->item(0);
        if ($body) {
            $bodyContent = '';
            foreach ($body->childNodes as $child) {
                $bodyContent .= $dom->saveHTML($child);
            }

            return $bodyContent;
        }

        return $content;
    }

    /**
     * Update page content from the builder.
     * Builder content is NOT purified - it's trusted content from authenticated users
     * and purification would break HTML5 elements, data attributes, inline styles, etc.
     */
    public function updatePageContent(CmsPost $page, string $content, ?string $css = null, ?string $js = null): void
    {
        $extractedContent = $this->extractPageContent($content);

        // Note: Builder content is NOT purified because:
        // 1. It's from authenticated admin/editor users (trusted)
        // 2. Purification strips HTML5 elements (main, section, article, etc.)
        // 3. Purification strips data-* attributes needed by the builder
        // 4. Purification strips inline styles needed for design

        // Sanitize CSS - strip any potential JS injection attempts
        $sanitizedCss = $css ? $this->sanitizeCss($css) : null;

        // Note: JS is stored but should be reviewed/approved before execution
        // Consider adding a review workflow for JS in future versions

        $page->update([
            'content' => $extractedContent,
            'css' => $sanitizedCss,
            'js' => $js,
        ]);
    }

    /**
     * Check if user owns the page
     */
    public function userOwnsPage(CmsPost $page, ?int $userId = null): bool
    {
        $userId ??= Auth::id();

        return $page->created_by === $userId;
    }

    public function getAuthorDataForTheme(?User $userobj): array
    {
        if (! is_null($userobj)) {
            $data['page_title'] = ucwords($userobj->name).' - Blogs';
            $data['author_obj'] = $userobj;
            $data['bio_details'] = $userobj->bio;

            $short_order = 'latest';
            if (request()->has('sort')) {
                $short_order = request()->query('sort');
            }

            $authorposts = CmsPost::query()->published()
                ->with(['category', 'author:id,first_name,last_name,avatar', 'featuredImage'])
                ->where('type', 'post')
                ->where('author_id', $userobj->id);

            if (request()->has('q')) {
                $searchQuery = (string) request()->query('q');
                $escapedSearchQuery = $this->escapeLikeQuery($searchQuery);
                $authorposts->where(function ($q) use ($escapedSearchQuery): void {
                    $q->where('title', 'ilike', sprintf('%%%s%%', $escapedSearchQuery))
                        ->orWhere('content', 'ilike', sprintf('%%%s%%', $escapedSearchQuery));
                });
            }

            if ($short_order === 'title') {
                $authorposts = $authorposts->orderBy('title', 'asc');
            } elseif ($short_order === 'oldest') {
                $authorposts = $authorposts->oldest();
            } else {
                $authorposts = $authorposts->latest();
            }

            $data['posts'] = $authorposts->paginate(10);

            $taxonomyCache = resolve(TaxonomyCacheService::class);
            $data['categories'] = $taxonomyCache->getCategories(withTrashed: true);
            $data['tags'] = $taxonomyCache->getTags(withTrashed: true);

            return $data;
        }

        abort(404);
    }

    /**
     * Sanitize CSS to remove potential attack vectors.
     * Strips expression(), javascript:, behavior, -moz-binding, etc.
     */
    protected function sanitizeCss(?string $css): ?string
    {
        if (in_array($css, [null, '', '0'], true)) {
            return null;
        }

        // Remove CSS expressions (IE-specific, dangerous)
        $css = preg_replace('/expression\s*\([^)]*\)/i', '', $css);

        // Remove javascript: URLs
        $css = preg_replace('/javascript\s*:/i', '', (string) $css);

        // Remove behavior property (IE-specific, dangerous)
        $css = preg_replace('/behavior\s*:\s*[^;]+/i', '', (string) $css);

        // Remove -moz-binding (Firefox, dangerous)
        $css = preg_replace('/-moz-binding\s*:\s*[^;]+/i', '', (string) $css);

        // Remove @import with external URLs (could load malicious CSS)
        $css = preg_replace('/@import\s+["\']?https?:\/\/[^;]+/i', '', (string) $css);

        // Remove HTML comments that could break out of <style> tags
        $css = preg_replace('/<!--/', '', (string) $css);
        $css = preg_replace('/-->/', '', (string) $css);

        return trim((string) $css);
    }

    private function escapeLikeQuery(string $value): string
    {
        return addcslashes($value, '\\%_');
    }
}
