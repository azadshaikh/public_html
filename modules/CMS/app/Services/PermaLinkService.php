<?php

namespace Modules\CMS\Services;

use App\Models\User;
use Modules\CMS\Models\CmsPost;

/**
 * Centralized Permalink Service - Single Source of Truth
 *
 * Handles all permalink operations:
 * - URL generation for posts, pages, categories, tags, authors
 * - URL parsing and content matching
 * - URL validation (canonical check)
 * - Breadcrumb generation
 */
class PermaLinkService
{
    protected PermalinkConfig $config;

    public function __construct()
    {
        $this->config = PermalinkConfig::getInstance();
    }

    // =========================================================================
    // URL GENERATION
    // =========================================================================

    /**
     * Generate permalink URL for a CMS post
     */
    public function generatePostPermalink(CmsPost $post): string
    {
        $url = $this->buildPermalinkUrl($post);

        // Add CMS base if configured
        if ($this->config->hasCmsBase()) {
            $url = $this->config->getCmsPrefix().$url;
        }

        // Add extension if configured
        return $this->config->addExtension($url);
    }

    /**
     * Generate author permalink URL
     */
    public function generateAuthorPermalink(User $author): string
    {
        $url = '';

        // Add CMS base
        if ($this->config->hasCmsBase()) {
            $url .= '/'.$this->config->cmsBase;
        }

        // Add author base
        if ($this->config->authorsBase !== '' && $this->config->authorsBase !== '0') {
            $url .= '/'.$this->config->authorsBase;
        }

        $url .= '/'.$author->username;

        return $this->config->addExtension($url);
    }

    /**
     * Get the canonical URL for any content type.
     */
    public function getCanonicalUrl(CmsPost|User $model): string
    {
        if ($model instanceof User) {
            return url($this->generateAuthorPermalink($model));
        }

        return url($this->generatePostPermalink($model));
    }

    // =========================================================================
    // URL MATCHING & PARSING
    // =========================================================================

    /**
     * Parse URL segments and find matching content
     *
     * @param  array  $segments  URL segments from request
     * @param  bool  $canPreview  Whether user can preview unpublished content
     * @return array{type: string, model: CmsPost|User|null, segments: array, is_canonical: bool}
     */
    public function matchUrl(array $segments, bool $canPreview = false): array
    {
        if ($segments === []) {
            return ['type' => 'not_found', 'model' => null, 'segments' => $segments, 'is_canonical' => false];
        }

        $workingSegments = $segments;

        // Strip extension from last segment
        $lastKey = array_key_last($workingSegments);
        $workingSegments[$lastKey] = $this->config->stripExtension($workingSegments[$lastKey]);

        // Check for classified content (future)
        if ($this->isClassifiedUrl($workingSegments)) {
            return ['type' => 'classified', 'model' => null, 'segments' => $workingSegments, 'is_canonical' => false];
        }

        // Strip CMS base if present
        $hasCmsBase = false;
        if ($this->config->hasCmsBase() && isset($workingSegments[0]) && strtolower($workingSegments[0]) === $this->config->cmsBase) {
            array_shift($workingSegments);
            $hasCmsBase = true;
        }

        // If CMS base is required but not present, this is invalid
        if ($this->config->hasCmsBase() && ! $hasCmsBase) {
            // Check if this might be a valid CMS URL without the base (for backwards compatibility)
            // We'll try to match it but flag it as non-canonical
            $result = $this->matchContentUrl($workingSegments, $canPreview);
            if ($result['model']) {
                $result['is_canonical'] = false;
            }

            return $result;
        }

        // Check for author URL
        if ($this->config->authorsBase !== '' && $this->config->authorsBase !== '0' && isset($workingSegments[0]) && strtolower($workingSegments[0]) === $this->config->authorsBase) {
            return $this->matchAuthorUrl($workingSegments);
        }

        // Match content (posts, pages, categories, tags)
        $result = $this->matchContentUrl($workingSegments, $canPreview);
        $result['is_canonical'] = true;

        return $result;
    }

    /**
     * Validate if the current URL path matches the expected canonical permalink.
     *
     * @param  array  $originalSegments  Original URL segments (before any stripping)
     */
    public function validatePath(array $originalSegments, CmsPost|User $model): bool
    {
        // Build current path from segments (strip extension for comparison)
        $currentSegments = $originalSegments;
        if ($currentSegments !== []) {
            $lastKey = array_key_last($currentSegments);
            $currentSegments[$lastKey] = $this->config->stripExtension($currentSegments[$lastKey]);
        }

        $currentPath = '/'.implode('/', $currentSegments);

        // Get expected path
        if ($model instanceof User) {
            $expectedPath = $this->generateAuthorPermalink($model);
        } else {
            $expectedPath = $this->generatePostPermalink($model);
        }

        // Strip extension from expected path for comparison
        if ($this->config->hasExtension() && str_ends_with($expectedPath, $this->config->urlExtension)) {
            $expectedPath = substr($expectedPath, 0, -strlen($this->config->urlExtension));
        }

        return strtolower($currentPath) === strtolower($expectedPath);
    }

    /**
     * Find content by slug or ID
     */
    public function findBySlugOrId(string $identifier, bool $canPreview = false): ?CmsPost
    {
        $query = CmsPost::query();

        if (is_numeric($identifier)) {
            $query->whereKey((int) $identifier);
        } else {
            $query->where('slug', $identifier);
        }

        // Design blocks are reusable fragments, not publicly routable.
        $query->where('type', '!=', 'design_block');

        $query->withTrashed();

        if (! $canPreview) {
            $query->where('status', 'published');
        }

        $post = $query->first();

        return $post instanceof CmsPost ? $post : null;
    }

    /**
     * Find author by username
     */
    public function findAuthorByUsername(string $username): ?User
    {
        return User::query()->where('username', $username)->first();
    }

    // =========================================================================
    // BREADCRUMB GENERATION
    // =========================================================================

    /**
     * Generate breadcrumb array for CMS view route
     *
     * @param  array  $parameters  URL segments
     */
    public function generateCmsBreadcrumb(array $parameters): array
    {
        $breadcrumbsArray = [];
        $isClassified = false;

        // Handle classified routes - add bounds checking
        if ($parameters !== [] && strtolower((string) $parameters[0]) === $this->config->classifiedBase) {
            array_shift($parameters);
            $isClassified = true;
        }

        if ($parameters !== [] && strtolower((string) $parameters[0]) === $this->config->usersBase) {
            array_shift($parameters);
            $isClassified = true;
        }

        if ($isClassified) {
            return $breadcrumbsArray; // Handle classified breadcrumbs separately if needed
        }

        return $this->buildCmsBreadcrumb($parameters);
    }

    // =========================================================================
    // PRIVATE HELPERS - URL MATCHING
    // =========================================================================

    /**
     * Check if URL is for classified content
     */
    private function isClassifiedUrl(array $segments): bool
    {
        if ($segments === []) {
            return false;
        }

        $first = strtolower((string) $segments[0]);

        return ($this->config->classifiedBase !== '' && $this->config->classifiedBase !== '0' && $first === $this->config->classifiedBase)
            || ($this->config->usersBase !== '' && $this->config->usersBase !== '0' && $first === $this->config->usersBase);
    }

    /**
     * Match author URL
     */
    private function matchAuthorUrl(array $segments): array
    {
        // Remove author base
        array_shift($segments);

        if ($segments === []) {
            return ['type' => 'not_found', 'model' => null, 'segments' => $segments];
        }

        $username = end($segments);
        $author = $this->findAuthorByUsername($username);

        return [
            'type' => 'author',
            'model' => $author,
            'segments' => $segments,
            'is_canonical' => true,
        ];
    }

    /**
     * Match content URL (posts, pages, categories, tags)
     */
    private function matchContentUrl(array $segments, bool $canPreview = false): array
    {
        if ($segments === []) {
            return ['type' => 'not_found', 'model' => null, 'segments' => $segments, 'is_canonical' => false];
        }

        // Check for category base
        if ($this->config->categoriesBase !== '' && $this->config->categoriesBase !== '0' && isset($segments[0]) && strtolower($segments[0]) === $this->config->categoriesBase) {
            array_shift($segments);
        }

        // Check for tag base
        if ($this->config->tagsBase !== '' && $this->config->tagsBase !== '0' && isset($segments[0]) && strtolower($segments[0]) === $this->config->tagsBase) {
            array_shift($segments);
        }

        // Check for posts base
        if ($this->config->postsBase !== '' && $this->config->postsBase !== '0' && isset($segments[0]) && strtolower($segments[0]) === $this->config->postsBase) {
            array_shift($segments);
        }

        if ($segments === []) {
            return ['type' => 'not_found', 'model' => null, 'segments' => $segments, 'is_canonical' => false];
        }

        // The last segment should be the slug or ID
        $identifier = end($segments);

        // Try to find the content
        $post = $this->findBySlugOrId($identifier, $canPreview);

        if (! $post instanceof CmsPost) {
            return ['type' => 'not_found', 'model' => null, 'segments' => $segments, 'is_canonical' => false];
        }

        return [
            'type' => $post->type,
            'model' => $post,
            'segments' => $segments,
            'is_canonical' => false, // Will be set by matchUrl
        ];
    }

    // =========================================================================
    // PRIVATE HELPERS - URL GENERATION
    // =========================================================================

    /**
     * Build permalink URL based on post type and structure
     */
    private function buildPermalinkUrl(CmsPost $post): string
    {
        $excludePostTypes = ['tag', 'category', 'page'];
        $categoryTypes = ['category'];
        $tagTypes = ['tag'];

        if (! in_array($post->type, $excludePostTypes)) {
            $url = $this->buildRegularPostUrl($post);

            if ($this->config->postsBase !== '' && $this->config->postsBase !== '0' && $post->type === 'post') {
                return '/'.$this->config->postsBase.$url;
            }

            return $url;
        }

        if (in_array($post->type, $categoryTypes)) {
            return $this->buildCategoryUrl($post);
        }

        if (in_array($post->type, $tagTypes)) {
            return $this->buildTagUrl($post);
        }

        if ($post->type === 'page') {
            return $this->buildPageUrl($post);
        }

        return $this->buildFallbackUrl($post);
    }

    /**
     * Build URL for regular posts using permalink structure
     */
    private function buildRegularPostUrl(CmsPost $post): string
    {
        $url = '';

        foreach ($this->config->postsStructureArray as $parameter) {
            switch ($parameter) {
                case '%year%':
                    $url .= $post->published_at ? '/'.date('Y', strtotime($post->published_at)) : '';
                    break;
                case '%monthnum%':
                    $url .= $post->published_at ? '/'.date('m', strtotime($post->published_at)) : '';
                    break;
                case '%day%':
                    $url .= $post->published_at ? '/'.date('d', strtotime($post->published_at)) : '';
                    break;
                case '%postname%':
                    $url .= '/'.$post->slug;
                    break;
                case '%post_id%':
                    $url .= '/'.$post->id;
                    break;
                case '%category%':
                    $categoryPath = $this->buildCategoryHierarchyPath($post);
                    if ($categoryPath !== '' && $categoryPath !== '0') {
                        $url .= '/'.$categoryPath;
                    }

                    break;
            }
        }

        return $url;
    }

    /**
     * Build URL for category post type
     */
    private function buildCategoryUrl(CmsPost $post): string
    {
        $url = '';

        if ($this->config->categoriesBase !== '' && $this->config->categoriesBase !== '0') {
            $url = '/'.$this->config->categoriesBase;
        }

        $hierarchyPath = $this->buildParentHierarchyPath($post);
        if ($hierarchyPath !== '' && $hierarchyPath !== '0') {
            $url .= '/'.$hierarchyPath;
        }

        return $url.'/'.$post->slug;
    }

    /**
     * Build URL for tag post type
     */
    private function buildTagUrl(CmsPost $post): string
    {
        $url = '';

        if ($this->config->tagsBase !== '' && $this->config->tagsBase !== '0') {
            $url = '/'.$this->config->tagsBase;
        }

        return $url.'/'.$post->slug;
    }

    /**
     * Build URL for page post type
     */
    private function buildPageUrl(CmsPost $post): string
    {
        $url = '';

        $hierarchyPath = $this->buildParentHierarchyPath($post);
        if ($hierarchyPath !== '' && $hierarchyPath !== '0') {
            $url = '/'.$hierarchyPath;
        }

        return $url.'/'.$post->slug;
    }

    /**
     * Build fallback URL for other post types
     */
    private function buildFallbackUrl(CmsPost $post): string
    {
        if ($this->config->postsStructureArray !== []) {
            return $this->buildRegularPostUrl($post);
        }

        return '/'.$post->slug;
    }

    /**
     * Build category hierarchy path from current post's category
     */
    private function buildCategoryHierarchyPath(CmsPost $post): string
    {
        if (empty($post->category_id)) {
            return '';
        }

        $category = $post->category;
        if (! $category instanceof CmsPost) {
            return '';
        }

        return $this->buildHierarchyPath($category);
    }

    /**
     * Build parent hierarchy path for the current post
     */
    private function buildParentHierarchyPath(CmsPost $post): string
    {
        if (($post->parent_id ?? 0) === 0) {
            return '';
        }

        $parent = $post->parent;
        if (! $parent instanceof CmsPost) {
            return '';
        }

        return $this->buildHierarchyPath($parent);
    }

    /**
     * Build hierarchy path by traversing up the parent chain
     */
    private function buildHierarchyPath(CmsPost $startPost): string
    {
        $slugs = [$startPost->slug];
        $currentPost = $startPost;
        $visited = [];
        $maxDepth = 10;
        $currentDepth = 0;

        while (($currentPost->parent_id ?? 0) !== 0 &&
               $currentDepth < $maxDepth &&
               ! in_array($currentPost->id, $visited)) {
            $visited[] = $currentPost->id;
            $parentPost = $currentPost->parent;

            if (! $parentPost instanceof CmsPost) {
                break;
            }

            $slugs[] = $parentPost->slug;
            $currentPost = $parentPost;
            $currentDepth++;
        }

        return implode('/', array_reverse($slugs));
    }

    /**
     * Build CMS breadcrumb array
     */
    private function buildCmsBreadcrumb(array $parameters): array
    {
        $breadcrumbsArray = [];

        if ($parameters !== [] && strtolower((string) $parameters[0]) === $this->config->cmsBase) {
            $breadcrumbsArray[] = [
                'label' => safe_content($this->config->cmsBase),
                'url' => '',
            ];
            array_shift($parameters);
        }

        if ($parameters !== [] && strtolower((string) $parameters[0]) === $this->config->authorsBase) {
            return $this->buildAuthorBreadcrumb($parameters);
        }

        return $this->buildContentBreadcrumb($parameters);
    }

    /**
     * Build author breadcrumb
     */
    private function buildAuthorBreadcrumb(array $parameters): array
    {
        $breadcrumbsArray = [];

        $breadcrumbsArray[] = [
            'label' => safe_content($this->config->authorsBase),
            'url' => '',
        ];
        array_shift($parameters);

        $authorUsername = end($parameters);
        $author = $this->findAuthorByUsername($authorUsername);

        if ($author instanceof User) {
            $breadcrumbsArray[] = [
                'label' => safe_content($author->name),
                'url' => $this->getCanonicalUrl($author),
            ];
        }

        return $breadcrumbsArray;
    }

    /**
     * Build content breadcrumb (categories, tags, posts)
     */
    private function buildContentBreadcrumb(array $parameters): array
    {
        $breadcrumbsArray = [];

        if ($parameters !== [] && strtolower((string) $parameters[0]) === $this->config->categoriesBase) {
            // Skip category base in breadcrumb, just remove it from parameters
            array_shift($parameters);
        } elseif ($parameters !== [] && strtolower((string) $parameters[0]) === $this->config->tagsBase) {
            // Skip tag base in breadcrumb, just remove it from parameters
            array_shift($parameters);
        } elseif ($parameters !== [] && strtolower((string) $parameters[0]) === $this->config->postsBase) {
            $breadcrumbsArray[] = [
                'label' => safe_content($this->config->postsBase),
                'url' => route('archive'),
            ];
            array_shift($parameters);
        }

        return $this->buildPostBreadcrumb($parameters, $breadcrumbsArray);
    }

    /**
     * Build post-specific breadcrumb
     */
    private function buildPostBreadcrumb(array $parameters, array $breadcrumbsArray): array
    {
        // Check if parameters array is empty
        if ($parameters === []) {
            return $breadcrumbsArray;
        }

        $lastParameter = end($parameters);
        $lastParameter = $this->config->stripExtension($lastParameter);

        $permalinkStructureArray = $this->config->postsStructureArray;
        array_pop($permalinkStructureArray);

        $page = $this->findBySlugOrId($lastParameter);

        if (! $page instanceof CmsPost) {
            return $breadcrumbsArray;
        }

        if ($page->type === 'post') {
            $breadcrumbsArray = $this->buildPostTypeBreadcrumb($page, $breadcrumbsArray);
        } elseif (in_array($page->type, ['page', 'category'])) {
            $breadcrumbsArray = $this->buildHierarchicalBreadcrumb($page, $breadcrumbsArray);
        }

        $breadcrumbsArray[] = [
            'label' => safe_content($page->title),
            'url' => $this->getCanonicalUrl($page),
        ];

        return $breadcrumbsArray;
    }

    /**
     * Build breadcrumb for post type with permalink structure
     * Note: Categories always appear in breadcrumbs regardless of permalink structure
     */
    private function buildPostTypeBreadcrumb(CmsPost $page, array $breadcrumbsArray): array
    {
        // Always add category breadcrumb for posts (if post has a category)
        if ($page->category_id) {
            return $this->buildCategoryBreadcrumb($page, $breadcrumbsArray);
        }

        return $breadcrumbsArray;
    }

    /**
     * Build category breadcrumb for post
     */
    private function buildCategoryBreadcrumb(CmsPost $page, array $breadcrumbsArray): array
    {
        if (empty($page->category_id)) {
            return $breadcrumbsArray;
        }

        $category = $page->category;
        if (! $category) {
            return $breadcrumbsArray;
        }

        $categoryArray = [];
        $currentPost = $category;
        $visited = [];
        $maxDepth = 10;
        $currentDepth = 0;

        // Build category hierarchy
        $categoryArray[] = [
            'label' => safe_content($category->title),
            'url' => $this->getCanonicalUrl($category),
        ];

        while (($currentPost->parent_id ?? 0) !== 0 &&
               $currentDepth < $maxDepth &&
               ! in_array($currentPost->id, $visited)) {
            $visited[] = $currentPost->id;
            $parentPost = $currentPost->parent;

            if (! $parentPost instanceof CmsPost) {
                break;
            }

            $categoryArray[] = [
                'label' => safe_content($parentPost->title),
                'url' => $this->getCanonicalUrl($parentPost),
            ];

            $currentPost = $parentPost;
            $currentDepth++;
        }

        return array_merge($breadcrumbsArray, array_reverse($categoryArray));
    }

    /**
     * Build hierarchical breadcrumb for pages and categories
     */
    private function buildHierarchicalBreadcrumb(CmsPost $page, array $breadcrumbsArray): array
    {
        $tmpBreadcrumbsArray = [];
        $currentPost = $page;
        $visited = [];
        $maxDepth = 10;
        $currentDepth = 0;

        while (($currentPost->parent_id ?? 0) !== 0 &&
               $currentDepth < $maxDepth &&
               ! in_array($currentPost->id, $visited)) {
            $visited[] = $currentPost->id;
            $parentPost = $currentPost->parent;

            if (! $parentPost instanceof CmsPost) {
                break;
            }

            $tmpBreadcrumbsArray[] = [
                'label' => safe_content($parentPost->title),
                'url' => $this->getCanonicalUrl($parentPost),
            ];

            $currentPost = $parentPost;
            $currentDepth++;
        }

        return array_merge($breadcrumbsArray, array_reverse($tmpBreadcrumbsArray));
    }
}
