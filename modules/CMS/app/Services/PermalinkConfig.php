<?php

namespace Modules\CMS\Services;

/**
 * Centralized permalink configuration.
 * Single source of truth for all SEO/permalink settings.
 * Cached for performance - settings are loaded once per request.
 */
class PermalinkConfig
{
    // Base URLs
    public readonly string $cmsBase;

    public readonly string $postsBase;

    public readonly string $categoriesBase;

    public readonly string $tagsBase;

    public readonly string $authorsBase;

    // Structure
    public readonly string $postsStructure;

    public readonly array $postsStructureArray;

    public readonly string $urlExtension;

    // Classified (for future)
    public readonly string $classifiedBase;

    public readonly string $usersBase;

    private static ?self $instance = null;

    private function __construct()
    {
        // Load all settings once
        $this->cmsBase = strtolower((string) setting('seo_cms_base', ''));
        $this->postsBase = strtolower((string) setting('seo_posts_permalink_base', ''));
        $this->categoriesBase = strtolower((string) setting('seo_categories_permalink_base', ''));
        $this->tagsBase = strtolower((string) setting('seo_tags_permalink_base', ''));
        $this->authorsBase = strtolower((string) setting('seo_authors_permalink_base', 'author'));
        $this->postsStructure = setting('seo_posts_permalink_structure', '');
        $this->postsStructureArray = array_filter(explode('/', $this->postsStructure));
        $this->urlExtension = setting('seo_url_extension', '');
        $this->classifiedBase = strtolower((string) setting('seo_general_classified_base', ''));
        $this->usersBase = strtolower((string) setting('seo_users_user_base', 'breeder'));
    }

    /**
     * Get the singleton instance (cached for the request lifecycle)
     */
    public static function getInstance(): self
    {
        if (! self::$instance instanceof PermalinkConfig) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    /**
     * Clear the cached instance (useful after settings change)
     */
    public static function clearCache(): void
    {
        self::$instance = null;
    }

    /**
     * Check if CMS base is configured
     */
    public function hasCmsBase(): bool
    {
        return $this->cmsBase !== '' && $this->cmsBase !== '0';
    }

    /**
     * Check if URL extension is configured
     */
    public function hasExtension(): bool
    {
        return $this->urlExtension !== '' && $this->urlExtension !== '0';
    }

    /**
     * Strip extension from a path segment
     */
    public function stripExtension(string $segment): string
    {
        if ($this->hasExtension() && str_ends_with($segment, $this->urlExtension)) {
            return substr($segment, 0, -strlen($this->urlExtension));
        }

        return $segment;
    }

    /**
     * Add extension to a path
     */
    public function addExtension(string $path): string
    {
        if ($this->hasExtension()) {
            return $path.$this->urlExtension;
        }

        return $path;
    }

    /**
     * Build the full CMS path prefix
     */
    public function getCmsPrefix(): string
    {
        return $this->hasCmsBase() ? '/'.$this->cmsBase : '';
    }
}
