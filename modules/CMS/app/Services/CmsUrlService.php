<?php

namespace Modules\CMS\Services;

use App\Models\User;
use Modules\CMS\Models\CmsPost;

/**
 * CmsUrlService
 *
 * Centralized URL generation service for all CMS entities.
 * Handles base paths, extensions, and consistent URL formatting.
 *
 * Usage:
 *   $urlService = app(CmsUrlService::class);
 *   $url = $urlService->getAuthorUrl($author);
 *   $url = $urlService->getPostUrl($post);
 */
class CmsUrlService
{
    protected ?string $extension = null;

    protected ?string $cmsBase = null;

    /**
     * Get the configured URL extension.
     */
    public function getExtension(): string
    {
        if ($this->extension === null) {
            $this->extension = setting('seo_url_extension', '');
        }

        return $this->extension;
    }

    /**
     * Get the configured CMS base path.
     */
    public function getCmsBase(): string
    {
        if ($this->cmsBase === null) {
            $this->cmsBase = setting('seo_cms_base', '');
        }

        return $this->cmsBase;
    }

    /**
     * Build a URL with proper base and extension.
     *
     * @param  array  $segments  URL path segments
     * @param  bool  $includeExtension  Whether to add the extension
     */
    public function buildUrl(array $segments, bool $includeExtension = true): string
    {
        $path = implode('/', array_filter($segments, fn ($s): bool => ! empty($s)));

        if ($includeExtension) {
            $path .= $this->getExtension();
        }

        return url($path);
    }

    /**
     * Get author page URL.
     */
    public function getAuthorUrl(User $author): string
    {
        $authorBase = setting('seo_authors_permalink_base', 'author');

        return $this->buildUrl([
            $this->getCmsBase(),
            $authorBase,
            $author->username,
        ]);
    }

    /**
     * Get post/page URL.
     * Note: For posts, use $post->permalink_url which handles complex permalink structures.
     */
    public function getPostUrl(CmsPost $post): string
    {
        // The permalink_url is already computed with extension in the model accessor
        return $post->permalink_url;
    }

    /**
     * Get category URL.
     * Uses the model's permalink_url for consistency with PermaLinkService.
     */
    public function getCategoryUrl(CmsPost $category): string
    {
        // Use the model's permalink_url which is computed by PermaLinkService
        // This ensures URL consistency across the application
        return url($category->permalink_url);
    }

    /**
     * Get tag URL.
     * Uses the model's permalink_url for consistency with PermaLinkService.
     */
    public function getTagUrl(CmsPost $tag): string
    {
        // Use the model's permalink_url which is computed by PermaLinkService
        // This ensures URL consistency across the application
        return url($tag->permalink_url);
    }

    /**
     * Get taxonomy (category or tag) URL.
     */
    public function getTaxonomyUrl(CmsPost $term): string
    {
        return match ($term->type) {
            'category' => $this->getCategoryUrl($term),
            'tag' => $this->getTagUrl($term),
            default => $term->permalink_url,
        };
    }

    /**
     * Get archive page URL.
     */
    public function getArchiveUrl(?int $page = null): string
    {
        $blogBase = setting('seo_posts_permalink_base', 'blog');

        $url = $this->buildUrl([
            $this->getCmsBase(),
            $blogBase,
        ], false); // No extension for archive root

        if ($page && $page > 1) {
            $url .= '?page='.$page;
        }

        return $url;
    }

    /**
     * Get search results URL.
     */
    public function getSearchUrl(string $query, ?int $page = null): string
    {
        $url = url('search');

        $params = ['q' => $query];
        if ($page && $page > 1) {
            $params['page'] = $page;
        }

        return $url.'?'.http_build_query($params);
    }

    /**
     * Get home URL.
     */
    public function getHomeUrl(): string
    {
        return url('/');
    }

    /**
     * Get feed URL.
     */
    public function getFeedUrl(): string
    {
        return url('feed');
    }

    /**
     * Get sitemap URL.
     */
    public function getSitemapUrl(): string
    {
        return url('sitemap.xml');
    }

    /**
     * Get social share URLs for a post.
     */
    public function getShareUrls(CmsPost $post): array
    {
        $url = urlencode((string) $post->permalink_url);
        $title = urlencode((string) $post->title);
        $excerpt = urlencode($this->generateExcerpt($post->post_excerpt ?? $post->content ?? '', 100));

        return [
            'twitter' => sprintf('https://twitter.com/intent/tweet?url=%s&text=%s', $url, $title),
            'facebook' => 'https://www.facebook.com/sharer/sharer.php?u='.$url,
            'linkedin' => 'https://www.linkedin.com/sharing/share-offsite/?url='.$url,
            'email' => sprintf('mailto:?subject=%s&body=%s%%0A%%0A%s', $title, $excerpt, $url),
            'whatsapp' => sprintf('https://wa.me/?text=%s%%20%s', $title, $url),
            'pinterest' => sprintf('https://pinterest.com/pin/create/button/?url=%s&description=%s', $url, $title),
            'copy' => $post->permalink_url,
        ];
    }

    /**
     * Get default avatar URL.
     */
    public function getDefaultAvatarUrl(): string
    {
        // Use theme setting or fallback to a gravatar-style default
        $defaultAvatar = theme_get_option('default_avatar');

        if (! empty($defaultAvatar)) {
            return $defaultAvatar;
        }

        // Use UI Avatars as fallback (generates initials-based avatar)
        return 'https://ui-avatars.com/api/?name=User&background=random&size=128';
    }

    /**
     * Get avatar URL with fallback to default.
     */
    public function getAvatarUrl(?string $avatar, ?string $name = null): string
    {
        if (! in_array($avatar, [null, '', '0'], true)) {
            return $avatar;
        }

        if ($name) {
            // Use UI Avatars with actual name
            $encodedName = urlencode($name);

            return sprintf('https://ui-avatars.com/api/?name=%s&background=random&size=128', $encodedName);
        }

        return $this->getDefaultAvatarUrl();
    }

    /**
     * Get placeholder image URL.
     */
    public function getPlaceholderImage(int $width = 800, int $height = 600): string
    {
        return get_placeholder_image_url();
    }

    /**
     * Clear cached settings (useful after settings update).
     */
    public function clearCache(): void
    {
        $this->extension = null;
        $this->cmsBase = null;
    }

    /**
     * Generate excerpt from content.
     */
    protected function generateExcerpt(string $content, int $length = 160): string
    {
        if ($content === '' || $content === '0') {
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
}
