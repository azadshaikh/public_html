<?php

namespace Modules\CMS\Services\Components;

use App\Models\User;
use App\Services\IntegrationService;
use Exception;
use Illuminate\Support\Facades\Log;
use Modules\CMS\Models\CmsPost;
use Modules\CMS\Services\SeoMetaService;
use Throwable;

/**
 * SEO Meta Component
 * Automatically generates SEO meta tags for CMS content
 * Usage: {seo_meta}
 */
class SeoMetaComponent extends ThemeComponent
{
    public function render(array $params, $template = null): string
    {
        $contentObject = null;
        $contentType = null;

        $routeSegments = request()->segments();

        // Remove CMS base prefix if present
        $cmsBase = setting('seo_cms_base', '');
        if (! empty($cmsBase) && ! empty($routeSegments) && $routeSegments[0] === $cmsBase) {
            array_shift($routeSegments);
        }

        $lastSegment = end($routeSegments);

        // Handle URL extension
        $extension = setting('seo_url_extension', '');
        if (! empty($extension) && ! empty($lastSegment)) {
            $lastSegment = str_replace($extension, '', $lastSegment);
        }

        // Try to find content based on route (no status filter - access control is handled by controller)
        if (! empty($lastSegment)) {
            // Try numeric ID first
            if (is_numeric($lastSegment)) {
                $cmsPost = CmsPost::with(['author', 'category', 'parent'])
                    ->where('id', $lastSegment)
                    ->withTrashed()
                    ->first();
            } else {
                // Try slug
                $cmsPost = CmsPost::with(['author', 'category', 'parent'])
                    ->where('slug', $lastSegment)
                    ->withTrashed()
                    ->first();
            }

            if ($cmsPost) {
                $contentObject = $cmsPost;
                // @phpstan-ignore-next-line property.notFound
                $contentType = $cmsPost->type;
            }
        } else {
            // Homepage - get home page from settings
            $homePageId = setting('cms_default_pages_home_page', '');
            if (! empty($homePageId)) {
                $cmsPost = CmsPost::with(['author', 'category', 'parent'])
                    ->where('id', $homePageId)
                    ->withTrashed()
                    ->first();

                if ($cmsPost) {
                    $contentObject = $cmsPost;
                    // @phpstan-ignore-next-line property.notFound
                    $contentType = $cmsPost->type;
                }
            }
        }

        // Check for author pages if no CMS post found
        if (! $contentObject && ! empty($routeSegments)) {
            $authorBase = setting('seo_authors_permalink_base', 'author');

            if (isset($routeSegments[0]) && $routeSegments[0] === $authorBase && ! empty($lastSegment)) {
                $author = User::query()->where('username', $lastSegment)->first();
                if ($author) {
                    $contentObject = $author;
                    $contentType = 'author';
                }
            }
        }

        // Generate SEO HTML if we have content
        if (! is_null($contentObject) && ! empty($contentType)) {
            try {
                $seoService = new SeoMetaService($contentObject, $contentType);

                return $seoService->generateSeoHtml().$this->renderIntegrations();
            } catch (Exception $e) {
                // Log error but don't break the page
                Log::error('SEO Meta generation failed: '.$e->getMessage(), [
                    'content_type' => $contentType,
                    'content_id' => $contentObject->id ?? null,
                ]);

                // Provide minimal fallback SEO
                return $this->generateFallbackSeo().$this->renderIntegrations();
            }
        }

        // Check if this is a 404 page (via template variable set by controller, or HTTP status)
        $is404 = false;
        if ($template !== null && method_exists($template, 'getTemplateVars')) {
            try {
                $is404 = (bool) $template->getTemplateVars('is_404');
            } catch (Throwable) {
                // Variable doesn't exist, ignore
                $is404 = false;
            }
        }

        if (! $is404 && http_response_code() === 404) {
            $is404 = true;
        }

        if ($is404) {
            return $this->generate404Seo().$this->renderIntegrations();
        }

        // Provide minimal fallback SEO for pages without specific content
        return $this->generateFallbackSeo().$this->renderIntegrations();
    }

    /**
     * Render integration scripts (analytics, tracking, meta tags, etc.)
     * Auto-appended after SEO meta tags in the <head> section.
     * Note: Custom CSS/JS are injected separately by ThemeService for proper ordering.
     */
    protected function renderIntegrations(): string
    {
        try {
            $integrationService = new IntegrationService('header');
            $code = $integrationService->getIntegrationCode();

            if ($code !== '' && $code !== '0') {
                return "\n<!-- Integrations -->\n".$code;
            }

            return '';
        } catch (Exception $exception) {
            Log::error('Integration scripts rendering failed: '.$exception->getMessage());

            return '';
        }
    }

    /**
     * Generate minimal fallback SEO when no content is found
     */
    protected function generateFallbackSeo(): string
    {
        $siteName = setting('site_title', config('app.name'));
        $siteDescription = setting('site_description', '');

        $html = '<title>'.$this->escape($siteName).'</title>'."\n";

        if (! empty($siteDescription)) {
            $html .= '<meta name="description" content="'.$this->escape($siteDescription).'">'."\n";
        }

        $html .= '<meta name="robots" content="index, follow">'."\n";
        $html .= '<link rel="canonical" href="'.url('/').request()->getPathInfo().'" />'."\n";

        // Add rel="me" link for X/Twitter profile verification
        $twitterUsername = setting('seo_social_media_twitter_username', '');
        if (! empty($twitterUsername)) {
            $username = ltrim((string) $twitterUsername, '@');
            $html .= '<link rel="me" href="https://x.com/'.$username.'" />'."\n";
        }

        // Add Local Business schema as part of fallback SEO (e.g., homepage without CMS page)
        try {
            $seoService = new SeoMetaService((object) [], 'page');
            $html .= $seoService->generateLocalBusinessSchema();
        } catch (Throwable) {
            // Silently ignore schema errors in fallback
        }

        return $html;
    }

    /**
     * Generate SEO for 404 error page using error page settings
     */
    protected function generate404Seo(): string
    {
        $siteName = setting('site_title', config('app.name'));
        $separator = setting('seo_general_separator', '-');

        // Get error page SEO settings
        $titleTemplate = setting('seo_error_page_title_template', '404 - Page not found %separator% %site_title%');
        $descriptionTemplate = setting('seo_error_page_description_template', 'The page you are looking for is not found. 404 error.');
        $robots = setting('seo_error_page_robots_default', 'noindex, nofollow');

        // Build title from template
        $title = str_replace(
            ['%site_title%', '%separator%'],
            [$siteName, $separator],
            $titleTemplate
        );

        // Build description from template
        $description = str_replace(
            ['%site_title%'],
            [$siteName],
            $descriptionTemplate
        );

        $html = '<title>'.$this->escape($title).'</title>'."\n";

        if (! empty($description)) {
            $html .= '<meta name="description" content="'.$this->escape($description).'">'."\n";
        }

        $html .= '<meta name="robots" content="'.$this->escape($robots).'">'."\n";

        return $html.('<link rel="canonical" href="'.url('/').request()->getPathInfo().'" />'."\n");
    }
}
