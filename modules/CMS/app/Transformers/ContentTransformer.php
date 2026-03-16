<?php

namespace Modules\CMS\Transformers;

use App\Models\CustomMedia;
use App\Models\User;
use Exception;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Auth;
use Modules\CMS\Models\CmsPost;
use Modules\CMS\Services\CmsUrlService;

/**
 * ContentTransformer
 *
 * Transforms CmsPost models into a consistent, standardized data structure
 * for theme templates. This ensures all templates receive data in a predictable format
 * with pre-computed fields to eliminate template-side calculations.
 *
 * @see docs/astero-modules-system/DATA_CONTRACT.md for the full data contract
 */
class ContentTransformer
{
    public function __construct(protected CmsUrlService $urlService) {}

    /**
     * Transform a CmsPost into a standardized content array for templates.
     */
    public function transform(CmsPost $post, array $options = []): array
    {
        $canEdit = $this->canEdit($post);
        $featuredImage = $post->featuredImage;
        $author = $post->author ?? $post->createdBy;

        return [
            // Identity
            'id' => $post->id,
            'type' => $post->type,
            'slug' => $post->slug,

            // Content
            'title' => $post->title,
            'content' => $post->content,
            'excerpt' => $post->post_excerpt ?? $this->generateExcerpt($post->content),
            'format' => $post->format ?? 'standard',

            // URLs (pre-computed)
            'url' => $post->permalink_url,
            'edit_url' => $canEdit ? $this->getEditUrl($post) : null,
            'canonical_url' => $post->canonical_url ?? $post->permalink_url,

            // Dates
            'created_at' => $post->created_at,
            'updated_at' => $post->updated_at,
            'published_at' => $post->published_at,
            'published_date' => $post->published_date,
            'formatted_date' => $post->published_at?->format(setting('date_format', 'M j, Y'))
                ?? $post->created_at->format(setting('date_format', 'M j, Y')),
            'time_ago' => $post->created_at->diffForHumans(),

            // Meta (pre-computed)
            'word_count' => $this->getWordCount($post->content),
            'read_time' => $this->getReadTime($post->content),

            // Media
            'featured_image' => $this->transformFeaturedImage($featuredImage, $post->title),

            // Author (unified structure)
            'author' => $this->transformAuthor($author),

            // Taxonomy
            'categories' => $this->transformTaxonomy($post->categories ?? collect()),
            'tags' => $this->transformTaxonomy($post->tags ?? collect()),

            // Sharing (pre-computed)
            'share' => $this->urlService->getShareUrls($post),

            // Status
            'status' => $post->status,
            'visibility' => $post->visibility ?? 'public',
            'is_featured' => (bool) ($post->is_featured ?? false),
            // Backward-compatible alias (some themes/components may still use `is_sticky`)
            'is_sticky' => (bool) ($post->is_featured ?? false),
            'password_required' => $post->isPasswordProtected(),

            // Navigation (optional, set by caller)
            'previous' => $options['previous'] ?? null,
            'next' => $options['next'] ?? null,

            // Custom CSS/JS
            'css' => $post->css,
            'js' => $post->js,
        ];
    }

    /**
     * Transform a collection of posts.
     */
    public function transformCollection($posts, array $options = []): array
    {
        $transformed = [];
        foreach ($posts as $post) {
            $transformed[] = $this->transform($post, $options);
        }

        return $transformed;
    }

    /**
     * Transform a User into an author structure.
     *
     * Following the data contract, author URLs and avatars are always pre-computed
     * with proper fallbacks - templates should never need to construct these.
     *
     * @see docs/astero-modules-system/DATA_CONTRACT.md
     */
    public function transformAuthor(?User $author): ?array
    {
        if (! $author instanceof User) {
            return null;
        }

        return [
            // Identity
            'id' => $author->id,
            'name' => $author->name,
            'first_name' => $author->first_name,
            'last_name' => $author->last_name,
            'username' => $author->username,

            // URLs (pre-computed - templates should NEVER construct these)
            'url' => $this->urlService->getAuthorUrl($author),
            // @phpstan-ignore-next-line property.notFound
            'avatar' => $this->urlService->getAvatarUrl($author->avatar_image, $author->name),

            // Bio/Profile
            'bio' => $this->sanitizeText($author->bio ?? ''),

            // Stats
            'posts_count' => $this->getAuthorPostsCount($author),

            // Social links (nested object - use author.social.twitter, not author.twitter_url)
            'social' => [
                'website' => $this->sanitizeUrl($author->website_url ?? ''),
                'twitter' => $this->sanitizeUrl($author->twitter_url ?? ''),
                'facebook' => $this->sanitizeUrl($author->facebook_url ?? ''),
                'instagram' => $this->sanitizeUrl($author->instagram_url ?? ''),
                'linkedin' => $this->sanitizeUrl($author->linkedin_url ?? ''),
            ],
        ];
    }

    /**
     * Transform featured image into standardized structure.
     *
     * All image URLs are pre-computed. The 'alt' field always has a value
     * (falls back to post title, then image title, then empty string).
     */
    public function transformFeaturedImage(?CustomMedia $image, ?string $fallbackAlt = null): ?array
    {
        if (! $image instanceof CustomMedia) {
            return null;
        }

        return [
            'id' => $image->id,
            'url' => $image->url,
            'thumbnail_url' => $image->getMediaUrl('thumbnail') ?? $image->url,
            'alt' => $image->alt_text ?? $fallbackAlt ?? $image->title ?? '',
            'title' => $image->title ?? $image->name ?? '',
            'caption' => $image->caption ?? '',
            'description' => $image->description ?? '',
            'width' => $image->width,
            'height' => $image->height,
            'mime_type' => $image->mime_type,
            'sizes' => [
                'thumbnail' => $image->getMediaUrl('thumbnail') ?? $image->url,
                'medium' => $image->getMediaUrl('medium') ?? $image->url,
                'large' => $image->getMediaUrl('large') ?? $image->url,
                'full' => $image->url,
            ],
        ];
    }

    /**
     * Transform taxonomy items (categories/tags) into standardized structure.
     *
     * URLs are pre-computed with proper extensions. Use category.url directly.
     */
    public function transformTaxonomy($items): array
    {
        if ($items instanceof EloquentCollection) {
            $items->loadCount('termPosts');
        }

        $transformed = [];
        foreach ($items as $item) {
            $count = 0;
            if (isset($item->term_posts_count)) {
                $count = (int) $item->term_posts_count;
            } elseif (method_exists($item, 'termPosts')) {
                $count = $item->termPosts()->count();
            }

            $transformed[] = [
                'id' => $item->id,
                'title' => $item->title,
                'slug' => $item->slug,
                'url' => $this->urlService->getTaxonomyUrl($item),
                'description' => $item->post_excerpt ?? '',
                'count' => $count,
                'parent_id' => $item->parent_id,
            ];
        }

        return $transformed;
    }

    /**
     * Sanitize plain text fields to prevent stored HTML.
     */
    protected function sanitizeText(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        $text = strip_tags($text);
        $text = preg_replace('/\s+/', ' ', $text);

        return trim((string) $text);
    }

    /**
     * Strip dangerous URL protocols but keep safe URLs intact.
     */
    protected function sanitizeUrl(?string $url): string
    {
        if ($url === null || $url === '') {
            return '';
        }

        $url = trim($url);
        if ($url === '') {
            return '';
        }

        $lower = strtolower($url);
        foreach (['javascript:', 'data:', 'vbscript:'] as $protocol) {
            if (str_starts_with($lower, $protocol)) {
                return '';
            }
        }

        return $url;
    }

    /**
     * Generate excerpt from content.
     */
    protected function generateExcerpt(?string $content, int $length = 160): string
    {
        if (in_array($content, [null, '', '0'], true)) {
            return '';
        }

        $text = strip_tags($content);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim((string) $text);

        if (strlen($text) <= $length) {
            return $text;
        }

        return rtrim(substr($text, 0, $length), ' .').'...';
    }

    /**
     * Get word count from content.
     */
    protected function getWordCount(?string $content): int
    {
        if (in_array($content, [null, '', '0'], true)) {
            return 0;
        }

        $text = strip_tags($content);

        return str_word_count($text);
    }

    /**
     * Get estimated reading time in minutes.
     */
    protected function getReadTime(?string $content, int $wordsPerMinute = 200): int
    {
        $wordCount = $this->getWordCount($content);

        return max(1, (int) ceil($wordCount / $wordsPerMinute));
    }

    /**
     * Check if current user can edit the post.
     */
    protected function canEdit(CmsPost $post): bool
    {
        if (! Auth::check()) {
            return false;
        }

        $user = Auth::user();

        // Super admin or admin can edit anything
        if ($user->hasRole(['super', 'admin'])) {
            return true;
        }

        // Author can edit their own posts
        if ($post->created_by === $user->id) {
            return true;
        }

        // Check permissions
        if ($user->can('edit_posts')) {
            return true;
        }

        return (bool) $user->can('cms.posts.edit');
    }

    /**
     * Get edit URL for post.
     */
    protected function getEditUrl(CmsPost $post): string
    {
        $type = $post->type;
        $routeName = match ($type) {
            'post' => 'cms.posts.edit',
            'page' => 'cms.pages.edit',
            'category' => 'cms.categories.edit',
            'tag' => 'cms.tags.edit',
            default => 'cms.posts.edit',
        };

        try {
            return route($routeName, $post->id);
        } catch (Exception) {
            return '';
        }
    }

    /**
     * Get author's total published posts count.
     */
    protected function getAuthorPostsCount(User $author): int
    {
        return CmsPost::query()->where('author_id', $author->id)
            ->where('type', 'post')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->count();
    }
}
