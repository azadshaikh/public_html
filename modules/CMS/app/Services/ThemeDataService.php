<?php

namespace Modules\CMS\Services;

use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Modules\CMS\Models\CmsPost;
use Modules\CMS\Models\Theme;
use Modules\CMS\Transformers\ContentTransformer;

/**
 * ThemeDataService
 *
 * Centralizes all data preparation for theme templates.
 * Provides a consistent data contract across all template types.
 */
class ThemeDataService
{
    protected ?string $currentContext = null;

    public function __construct(protected ContentTransformer $transformer) {}

    /**
     * Get base context that's available on ALL pages.
     */
    public function getBaseContext(): array
    {
        return [
            'site' => $this->getSiteData(),
            'theme' => $this->getThemeData(),
            'urls' => $this->getCommonUrls(),
            'user' => $this->getCurrentUser(),
            'is_logged_in' => Auth::check(),
            'current_year' => date('Y'),
        ];
    }

    /**
     * Get data for single post page.
     */
    public function getPostData(CmsPost $post): array
    {
        $this->currentContext = 'single';

        // Get adjacent posts
        $adjacentPosts = $this->getAdjacentPosts($post);

        // Transform the post with navigation
        $postData = $this->transformer->transform($post, [
            'previous' => $adjacentPosts['previous'],
            'next' => $adjacentPosts['next'],
        ]);

        // Get related/recent posts
        $related_posts = $this->getRelatedPosts($post);

        return array_merge($this->getBaseContext(), [
            'post' => $postData,
            'previous_post' => $adjacentPosts['previous'],
            'next_post' => $adjacentPosts['next'],
            'related_posts' => $related_posts,

            // Context flags
            '_context' => 'single',
            '_template_type' => 'post',
        ]);
    }

    /**
     * Get data for single page.
     */
    public function getPageData(CmsPost $page): array
    {
        $this->currentContext = 'page';

        $pageData = $this->transformer->transform($page);

        return array_merge($this->getBaseContext(), [
            'page' => $pageData,

            // Context flags
            '_context' => 'page',
            '_template_type' => 'page',
        ]);
    }

    /**
     * Get data for homepage.
     */
    public function getHomepageData(?CmsPost $page = null): array
    {
        $this->currentContext = 'home';

        $data = $this->getBaseContext();

        // If homepage is set to a specific page
        if ($page instanceof CmsPost) {
            $data['page'] = $this->transformer->transform($page);
        }

        // Get recent posts
        $postsLimit = (int) theme_get_option('show_latest_posts_limit', 10);
        $posts = $this->getRecentPosts($postsLimit, $page?->id);

        $data['posts'] = $posts;
        $data['_context'] = 'home';
        $data['_template_type'] = 'home';

        return $data;
    }

    /**
     * Get data for archive pages (blog listing, category, tag).
     */
    public function getArchiveData(?CmsPost $term = null): array
    {
        $this->currentContext = 'archive';

        $data = $this->getBaseContext();

        // Build query
        $query = CmsPost::query()->published()
            ->where('cms_posts.type', 'post')
            ->with(['author:id,name,first_name,last_name,avatar,username', 'categories', 'tags', 'featuredImage'])
            ->select('cms_posts.*');

        // Filter by category/tag if provided
        if ($term && ! in_array($term->type, ['post', 'page'])) {
            $termIds = CmsPost::getChildrenIds($term->id);
            $termIds[] = $term->id;

            $query->whereIn('cms_posts.id', function ($subQuery) use ($termIds): void {
                $subQuery->select('post_id')
                    ->from('cms_post_terms')
                    ->whereIn('term_id', $termIds);
            });
        }

        // Handle search
        if (request()->has('q')) {
            $searchQuery = (string) request()->query('q');
            $escapedSearchQuery = $this->escapeLikeQuery($searchQuery);
            $query->where(function ($q) use ($escapedSearchQuery): void {
                $q->where('cms_posts.title', 'ilike', sprintf('%%%s%%', $escapedSearchQuery))
                    ->orWhere('cms_posts.content', 'ilike', sprintf('%%%s%%', $escapedSearchQuery));
            });
        }

        // Handle sorting
        $sortOrder = request()->query('sort', 'latest');

        // Sticky/featured posts first (WordPress-style)
        $query->orderByDesc('cms_posts.is_featured');
        match ($sortOrder) {
            'title' => $query->orderBy('cms_posts.title', 'asc'),
            'oldest' => $query->oldest('cms_posts.published_at'),
            default => $query->latest('cms_posts.published_at'),
        };

        // Paginate
        $perPage = (int) setting('posts_per_page', 10);
        $posts = $query->paginate($perPage);

        // Transform posts while preserving pagination
        $transformedPosts = $this->transformPaginatedPosts($posts);

        // Transform category/tag if provided
        $categoryData = null;
        if ($term instanceof CmsPost) {
            $categoryData = [
                'id' => $term->id,
                'title' => $term->title,
                'slug' => $term->slug,
                'type' => $term->type,
                'description' => $term->post_excerpt ?? '',
                'excerpt' => $term->post_excerpt ?? '',
                'content' => $term->content,
                'url' => $term->permalink_url,
                'permalink_url' => $term->permalink_url,
                'feature_image_id' => $term->post_feature_image_id,
                'featured_image' => $this->transformer->transformFeaturedImage($term->featuredImage, $term->title),
            ];
        }

        $data['posts'] = $transformedPosts;
        $data['category'] = $categoryData;
        $data['_context'] = 'archive';
        $data['_template_type'] = $term instanceof CmsPost ? $term->type : 'archive';

        return $data;
    }

    /**
     * Get data for author pages.
     */
    public function getAuthorData(User $author): array
    {
        $this->currentContext = 'author';

        $data = $this->getBaseContext();

        // Transform author
        $authorData = $this->transformer->transformAuthor($author);

        // Get author's posts
        $query = CmsPost::query()->published()
            ->where('type', 'post')
            ->where('author_id', $author->id)
            ->with(['categories', 'tags', 'featuredImage'])
            ->orderByDesc('is_featured')
            ->latest('published_at');

        // Handle search within author's posts
        if (request()->has('q')) {
            $searchQuery = (string) request()->query('q');
            $escapedSearchQuery = $this->escapeLikeQuery($searchQuery);
            $query->where(function ($q) use ($escapedSearchQuery): void {
                $q->where('title', 'ilike', sprintf('%%%s%%', $escapedSearchQuery))
                    ->orWhere('content', 'ilike', sprintf('%%%s%%', $escapedSearchQuery));
            });
        }

        // Handle sorting
        $sortOrder = request()->query('sort', 'latest');
        match ($sortOrder) {
            'title' => $query->orderBy('title', 'asc'),
            'oldest' => $query->oldest('published_at'),
            default => $query->latest('published_at'),
        };

        $perPage = (int) setting('posts_per_page', 10);
        $posts = $query->paginate($perPage);
        $transformedPosts = $this->transformPaginatedPosts($posts);

        $data['author'] = $authorData;
        $data['posts'] = $transformedPosts;
        $data['page_title'] = ucwords($author->name).' - Posts';
        $data['_context'] = 'author';
        $data['_template_type'] = 'author';

        return $data;
    }

    /**
     * Get data for search results.
     */
    public function getSearchData(?string $query = null): array
    {
        $this->currentContext = 'search';

        $data = $this->getBaseContext();

        $postsQuery = CmsPost::query()->published()
            ->where('type', 'post')
            ->with(['author:id,name,first_name,last_name,avatar,username', 'categories', 'tags', 'featuredImage']);

        if (! in_array($query, [null, '', '0'], true)) {
            $escapedQuery = $this->escapeLikeQuery($query);
            $postsQuery->where(function ($q) use ($escapedQuery): void {
                $q->where('title', 'ilike', sprintf('%%%s%%', $escapedQuery))
                    ->orWhere('content', 'ilike', sprintf('%%%s%%', $escapedQuery));
            });
        }

        $postsQuery->orderByDesc('is_featured')->latest('published_at');

        $perPage = (int) setting('posts_per_page', 10);
        $posts = $postsQuery->paginate($perPage);
        $transformedPosts = $this->transformPaginatedPosts($posts);

        $data['posts'] = $transformedPosts;
        $data['query'] = $query;
        $data['search_query'] = $query;
        $data['_context'] = 'search';
        $data['_template_type'] = 'search';

        return $data;
    }

    /**
     * Get data for 404 error page.
     */
    public function get404Data(): array
    {
        $this->currentContext = '404';

        return array_merge($this->getBaseContext(), [
            'is_404' => true,
            '_context' => '404',
            '_template_type' => '404',
        ]);
    }

    /**
     * Get current template context.
     */
    public function getCurrentContext(): ?string
    {
        return $this->currentContext;
    }

    /**
     * Check if current page is a specific type.
     */
    public function is(string $type): bool
    {
        return $this->currentContext === $type;
    }

    /**
     * Get site-wide data.
     */
    protected function getSiteData(): array
    {
        return [
            'name' => setting('site_title', config('app.name')),
            'title' => setting('site_title', config('app.name')),
            'description' => setting('site_description', ''),
            'url' => config('app.url'),
            'logo' => theme_get_option('logo'),
            'logo_width' => $this->sanitizeLogoWidth(theme_get_option('logo_width', 160)),
            'favicon' => theme_get_option('favicon'),
            'admin_email' => setting('admin_email', ''),
            'language' => app()->getLocale(),
            'date_format' => setting('date_format', 'M j, Y'),
            'time_format' => setting('time_format', 'g:i a'),
            'posts_per_page' => (int) setting('posts_per_page', 10),
        ];
    }

    /**
     * Get common URLs used across all pages.
     * These are pre-computed to prevent URL construction in templates.
     * URLs are cached using two-tier caching via CmsDefaultPagesCacheService.
     */
    protected function getCommonUrls(): array
    {
        return resolve(CmsDefaultPagesCacheService::class)->getCached();
    }

    /**
     * Get theme-specific data.
     */
    protected function getThemeData(): array
    {
        $activeTheme = Theme::getActiveTheme();

        return [
            'name' => $activeTheme['name'] ?? 'default',
            'directory' => $activeTheme['directory'] ?? 'default',
            'version' => $activeTheme['version'] ?? '1.0.0',
        ];
    }

    /**
     * Get current authenticated user data.
     */
    protected function getCurrentUser(): ?array
    {
        if (! Auth::check()) {
            return null;
        }

        /** @var User $user */
        $user = Auth::user();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            // @phpstan-ignore-next-line property.notFound
            'avatar' => $user->avatar_image,
            'is_admin' => $user->hasRole(['super', 'admin']),
            'can_edit_posts' => $user->can('edit_posts') || $user->can('cms.posts.edit'),
        ];
    }

    /**
     * Get adjacent (previous/next) posts based on published_at date.
     * Previous = posts published earlier than current post
     * Next = posts published later than current post
     */
    protected function getAdjacentPosts(CmsPost $post): array
    {
        // Drafts/unscheduled content may not have a published_at timestamp.
        // Comparing with '< null' / '> null' throws: "Illegal operator and value combination."
        if ($post->published_at === null) {
            return [
                'previous' => null,
                'next' => null,
            ];
        }

        $baseQuery = CmsPost::query()->published()
            ->where('type', $post->type)
            ->with(['featuredImage']);

        // Previous post: published before this one (older)
        $previous = (clone $baseQuery)
            ->where('published_at', '<', $post->published_at)
            ->latest('published_at')
            ->first();

        // Next post: published after this one (newer)
        $next = (clone $baseQuery)
            ->where('published_at', '>', $post->published_at)
            ->oldest('published_at')
            ->first();

        /** @var CmsPost|null $previousPost */
        $previousPost = $previous;
        /** @var CmsPost|null $nextPost */
        $nextPost = $next;

        return [
            'previous' => $previousPost ? $this->transformer->transform($previousPost) : null,
            'next' => $nextPost ? $this->transformer->transform($nextPost) : null,
        ];
    }

    /**
     * Get related posts based on categories.
     */
    protected function getRelatedPosts(CmsPost $post, int $limit = 3): array
    {
        $categoryIds = $post->categories->pluck('id')->toArray();

        $query = CmsPost::query()->published()
            ->where('type', 'post')
            ->where('id', '!=', $post->id)
            ->with(['author:id,name,first_name,last_name,avatar,username', 'featuredImage', 'categories']);

        // If post has categories, prioritize related
        if (! empty($categoryIds)) {
            $query->whereHas('categories', function ($q) use ($categoryIds): void {
                $q->whereIn('cms_posts.id', $categoryIds);
            });
        }

        $query->latest('published_at')->limit($limit);

        $posts = $query->get();

        // If not enough related, fill with recent
        if ($posts->count() < $limit) {
            $existingIds = $posts->pluck('id')->toArray();
            $existingIds[] = $post->id;

            $fillPosts = CmsPost::query()->published()
                ->where('type', 'post')
                ->whereNotIn('id', $existingIds)
                ->with(['author:id,name,first_name,last_name,avatar,username', 'featuredImage', 'categories'])
                ->orderBy('published_at', 'desc')
                ->limit($limit - $posts->count())
                ->get();

            $posts = $posts->merge($fillPosts);
        }

        return $this->transformer->transformCollection($posts);
    }

    /**
     * Get recent posts.
     */
    protected function getRecentPosts(int $limit = 4, ?int $excludeId = null): array
    {
        $query = CmsPost::query()->published()
            ->where('type', 'post')
            ->with(['author:id,name,first_name,last_name,avatar,username', 'featuredImage', 'categories']);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        $posts = $query->orderByDesc('is_featured')->latest('published_at')
            ->limit($limit)
            ->get();

        return $this->transformer->transformCollection($posts);
    }

    /**
     * Transform paginated posts while preserving pagination.
     */
    protected function transformPaginatedPosts(LengthAwarePaginator $paginator): LengthAwarePaginator
    {
        $transformed = $this->transformer->transformCollection($paginator->items());

        return new LengthAwarePaginator(
            $transformed,
            $paginator->total(),
            $paginator->perPage(),
            $paginator->currentPage(),
            [
                'path' => $paginator->path(),
                'query' => request()->query(),
            ]
        );
    }

    private function escapeLikeQuery(string $value): string
    {
        return addcslashes($value, '\\%_');
    }

    /**
     * Sanitize frontend logo width to a safe pixel value.
     */
    private function sanitizeLogoWidth(mixed $value): int
    {
        if (is_string($value)) {
            $value = trim($value);
        }

        if (! is_numeric($value)) {
            return 160;
        }

        $width = (int) $value;

        return max(60, min($width, 480));
    }
}
