<?php

namespace Modules\CMS\Services;

use Illuminate\Support\Str;
use Modules\CMS\Enums\MetaRobotsTag;

/**
 * SEO Meta Service
 *
 * Generates SEO meta tags, schema markup, and social media tags for CMS content
 */
class SeoMetaService
{
    /**
     * Plural form of content type
     */
    public string $pluralContentType;

    /**
     * Normalized SEO type for settings lookup
     */
    public string $seoType;

    /**
     * Site name from theme settings
     */
    public string $siteName;

    /**
     * Initialize SEO service with content object and type
     *
     * @param  mixed  $contentObject  The content object (CmsPost, User, etc.)
     * @param  string  $contentType  Content type (post, page, category, tag, author, etc.)
     */
    public function __construct(public mixed $contentObject, public string $contentType)
    {
        $this->pluralContentType = Str::plural($this->contentType);
        $this->seoType = $this->contentType;

        // Get site name from settings
        $this->siteName = setting('site_title', config('app.name'));

        // Normalize SEO type for special cases
        if ($this->contentType === 'author') {
            $this->seoType = 'authors';
        }
    }

    /**
     * Generate complete SEO HTML including meta tags and schema markup
     *
     * @return string Complete SEO HTML
     */
    public function generateSeoHtml(): string
    {
        $seoHtml = '';

        // Basic SEO meta tags (title, description, robots, canonical)
        $seoHtml .= $this->generateBasicMetaTags();

        // Social media meta tags (Open Graph, Twitter Card)
        $seoHtml .= $this->generateSocialMediaTags();

        // Content-type specific schema markup
        if ($this->seoType === 'post') {
            $seoHtml .= $this->generateArticleSchema();
        }

        // Local Business Schema (show on only pages, not on categories/tags and posts)
        if ($this->seoType === 'page') {
            $seoHtml .= $this->generateLocalBusinessSchema();
        }

        // Custom schema from content object
        if (! is_null($this->contentObject) && isset($this->contentObject->schema) && ! empty($this->contentObject->schema)) {
            $seoHtml .= $this->contentObject->schema;
        }

        // Breadcrumb schema
        $seoHtml .= $this->generateBreadcrumbSchema();

        return $seoHtml;
    }

    /**
     * Generate basic SEO meta tags (title, description, robots, canonical)
     *
     * @return string Basic meta tags HTML
     */
    public function generateBasicMetaTags(): string
    {
        $metaHtml = '';

        // Title
        $metaHtml .= '<title>'.$this->generateTitleTag().'</title>'."\n";

        // Meta Description
        $metaHtml .= '<meta name="description" content="'.$this->generateMetaDescription().'" />'."\n";

        // Robots
        $metaHtml .= '<meta name="robots" content="'.$this->generateRobotsTag().'" />'."\n";

        // Canonical
        $metaHtml .= '<link rel="canonical" href="'.$this->generateCanonicalUrl().'" />'."\n";

        return $metaHtml;
    }

    /**
     * Generate title tag based on content type and SEO settings
     *
     * Priority:
     * 1. Custom meta_title from seo_data JSON (page-level override)
     * 2. Title template from SEO settings with variable replacement
     * 3. Content object title + site name fallback
     *
     * @return string Title tag content
     */
    public function generateTitleTag(): string
    {
        $title = '';
        $titleTemplate = '';
        $separator = setting('seo_separator_character', '-');

        // Priority 1: Get custom meta title from seo_data JSON for CMS content
        if (in_array($this->pluralContentType, ['posts', 'categories', 'tags', 'pages'])) {
            $seoData = $this->getSeoDataFromObject();
            $title = $seoData['meta_title'] ?? '';
            $titleTemplate = setting('seo_'.$this->pluralContentType.'_title_template', '');
        } elseif ($this->seoType === 'search-page') {
            $title = $this->contentObject->meta_title ?? '';
            $titleTemplate = setting('seo_search_cms_title_template', '');
        } elseif ($this->seoType === 'authors') {
            $titleTemplate = setting('seo_authors_title_template', '');
        }

        // If no custom title, try building from template
        if (empty($title) && ! empty($titleTemplate)) {
            $title = $this->buildTitleFromTemplate($titleTemplate);
        }

        // Fallback: Content title + site name
        if (empty($title)) {
            $contentTitle = $this->contentObject->title ?? $this->contentObject->name ?? '';
            if (! empty($contentTitle)) {
                $title = htmlspecialchars((string) $contentTitle, ENT_QUOTES, 'UTF-8').' '.$separator.' '.$this->siteName;
            } else {
                $title = $this->siteName;
            }
        }

        return $title;
    }

    /**
     * Generate canonical URL for the content
     *
     * @return string Canonical URL
     */
    public function generateCanonicalUrl(): string
    {
        // Check for custom canonical URL in seo_data
        if (in_array($this->pluralContentType, ['posts', 'categories', 'tags', 'pages'])) {
            $seoData = $this->getSeoDataFromObject();
            if (! empty($seoData['canonical_url'])) {
                return $seoData['canonical_url'];
            }
        }

        // Try to get URL from content object's permalink_url accessor
        if (isset($this->contentObject->permalink_url) && ! empty($this->contentObject->permalink_url)) {
            $url = $this->contentObject->permalink_url;
            // Ensure it's a full URL
            if (! str_starts_with((string) $url, 'http')) {
                return url($url);
            }

            return $url;
        }

        // Fallback to current request URL
        return request()->url();
    }

    /**
     * Generate meta description based on content type
     */
    public function generateMetaDescription(): string
    {
        $metaDescription = '';
        $descriptionTemplate = '';

        // Get description template based on content type
        if (in_array($this->pluralContentType, ['posts', 'categories', 'tags', 'pages'])) {
            // Priority 1: Get custom meta description from seo_data JSON
            $seoData = $this->getSeoDataFromObject();
            $metaDescription = $seoData['meta_description'] ?? '';

            // Get description template from settings
            $descriptionTemplate = setting('seo_'.$this->pluralContentType.'_description_template', '');
        } elseif ($this->seoType === 'authors') {
            $descriptionTemplate = setting('seo_authors_description_template', '');
        }

        // If no custom description, build from template or content
        if (empty($metaDescription)) {
            $metaDescription = $this->buildDescriptionFromTemplate($descriptionTemplate);
        }

        return htmlspecialchars((string) $metaDescription, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Extract plain text description from HTML content (limited to 150 characters)
     *
     * @param  string  $htmlContent  HTML content
     * @return string Plain text description with ellipsis
     */
    public function extractPlainTextDescription(string $htmlContent): string
    {
        $contentDetails = getContentDetails($htmlContent);
        $plainText = $contentDetails['plain_text'] ?? '';

        // Limit to 150 characters
        if (mb_strlen($plainText) > 150) {
            $plainText = mb_substr($plainText, 0, 150);
            // Add ellipsis since we don't know if the sentence is complete
            $plainText = rtrim($plainText, ' .,;:!?').'...';
        }

        return $plainText;
    }

    /**
     * Generate robots meta tag with proper 3-tier priority
     *
     * Priority:
     * 1. Global Search Engine Visibility (MOST POWERFUL - if disabled, always returns noindex, nofollow)
     * 2. Page-level custom meta_robots from seo_data JSON (overrides content-type default)
     * 3. Content-type default from SEO settings (e.g., seo_pages_robots_default)
     *
     * @return string Robots tag content
     */
    public function generateRobotsTag(): string
    {
        // Priority 1: Global search engine visibility - MOST POWERFUL RULE, overrides everything
        $searchEngineVisibility = setting('seo_search_engine_visibility', 'true');

        if (in_array($searchEngineVisibility, ['false', false, '0'], true)) {
            return MetaRobotsTag::NOINDEX_NOFOLLOW->value;
        }

        $robotsTag = '';

        // Priority 2: Page-level custom robots tag (highest priority when visibility is enabled)
        if (in_array($this->pluralContentType, ['posts', 'categories', 'tags', 'pages'])) {
            $seoData = $this->getSeoDataFromObject();
            $robotsTag = $seoData['meta_robots'] ?? '';
        } elseif ($this->seoType === 'authors') {
            $robotsTag = $this->contentObject->meta_robots ?? '';
        }

        // Priority 3: Content-type default from SEO settings
        if (empty($robotsTag)) {
            if (in_array($this->pluralContentType, ['posts', 'categories', 'tags', 'pages'])) {
                $robotsTag = setting('seo_'.$this->pluralContentType.'_robots_default', '');
            } elseif ($this->seoType === 'authors') {
                $robotsTag = setting('seo_authors_robots_default', '');
            }
        }

        // Final fallback to default enum value
        if (empty($robotsTag)) {
            return MetaRobotsTag::default()->value;
        }

        return $robotsTag;
    }

    /**
     * Generate social media meta tags (Open Graph and Twitter Card)
     *
     * @return string Social media tags HTML
     */
    public function generateSocialMediaTags(): string
    {
        $socialHtml = '';
        $ogType = $this->contentType === 'post' ? 'article' : 'website';

        // Open Graph tags
        $socialHtml .= '<meta property="og:locale" content="en" />'."\n";
        $socialHtml .= '<meta property="og:type" content="'.$ogType.'" />'."\n";

        // Get OG-specific values or fallback to regular SEO values
        if (! empty($this->contentObject) && in_array($this->seoType, ['post', 'category', 'tag', 'page'])) {
            // Get Open Graph data from og_data JSON column
            $ogData = $this->getOgDataFromObject();

            $ogTitle = $ogData['og_title'] ?? $this->generateTitleTag();
            $ogDescription = $ogData['og_description'] ?? $this->generateMetaDescription();
            $ogImage = empty($ogData['og_image'])
                ? $this->getSocialMediaImage()
                : get_media_url($ogData['og_image']);
        } else {
            $ogTitle = $this->generateTitleTag();
            $ogDescription = $this->generateMetaDescription();
            $ogImage = $this->getSocialMediaImage();
        }

        $socialHtml .= '<meta property="og:title" content="'.$ogTitle.'"/>'."\n";
        $socialHtml .= '<meta property="og:description" content="'.$ogDescription.'"/>'."\n";
        $socialHtml .= '<meta property="og:url" content="'.$this->generateCanonicalUrl().'"/>'."\n";
        $socialHtml .= '<meta property="og:site_name" content="'.htmlspecialchars($this->siteName, ENT_QUOTES, 'UTF-8').'"/>'."\n";
        if (! in_array($ogImage, [null, '', '0'], true)) {
            $socialHtml .= '<meta property="og:image" content="'.$ogImage.'" />'."\n";
        }

        // Twitter Card tags
        $twitterCardType = setting('seo_social_media_twitter_card_type', 'summary');
        $twitterUsername = setting('seo_social_media_twitter_username', '');

        $socialHtml .= '<meta name="twitter:card" content="'.$twitterCardType.'" />'."\n";
        $socialHtml .= '<meta name="twitter:title" content="'.$ogTitle.'" />'."\n";
        $socialHtml .= '<meta name="twitter:description" content="'.$ogDescription.'" />'."\n";
        if (! in_array($ogImage, [null, '', '0'], true)) {
            $socialHtml .= '<meta name="twitter:image" content="'.$ogImage.'" />'."\n";
        }

        if (! empty($twitterUsername)) {
            $socialHtml .= '<meta name="twitter:site" content="'.$twitterUsername.'" />'."\n";
        }

        // Additional Twitter tags for posts
        if ($this->seoType === 'post' && isset($this->contentObject->name)) {
            $socialHtml .= '<meta name="twitter:label1" content="Written by" />'."\n";
            $socialHtml .= '<meta name="twitter:data1" content="'.htmlspecialchars(ucwords($this->contentObject->name), ENT_QUOTES, 'UTF-8').'" />'."\n";
            $socialHtml .= '<meta name="twitter:label2" content="Time to read" />'."\n";
            $readingTime = $this->contentObject->post_reading_seconds ?? 0;
            $socialHtml .= '<meta name="twitter:data2" content="'.getSecondsToReadableTime($readingTime, 'minutes').'" />'."\n";
        }

        return $socialHtml;
    }

    /**
     * Get social media image URL
     *
     * @return string Image URL
     */
    public function getSocialMediaImage(): string
    {
        $imageUrl = '';
        $defaultOgImage = setting('seo_social_media_open_graph_image', '');

        // Try to get feature image from content
        if (in_array($this->pluralContentType, ['posts', 'pages', 'categories', 'tags'])
            && ! empty($this->contentObject->post_feature_image_id)) {
            $imageUrl = get_media_url($this->contentObject->post_feature_image_id);
        } elseif (! empty($defaultOgImage)) {
            $imageUrl = get_media_url($defaultOgImage);
        }

        return $imageUrl;
    }

    /**
     * Generate Article schema for posts
     *
     * @return string Article schema JSON-LD
     */
    public function generateArticleSchema(): string
    {
        // Check if Article schema is enabled in settings
        if (! setting('seo_enable_article_schema', false)) {
            return '';
        }

        $schema = '<script type="application/ld+json">'."\n";

        $pageUrl = $this->generateCanonicalUrl();
        $pageImage = $this->getSocialMediaImage();
        $pageTitle = $this->generateTitleTag();
        $pageDescription = $this->generateMetaDescription();

        $organizationName = $this->siteName;
        $organizationLogo = function_exists('theme_get_option') && active_modules('cms') && ! empty(theme_get_option('logo'))
            ? theme_get_option('logo')
            : get_media_url(setting('seo_local_seo_logo_image', ''));

        $schema .= '{'."\n";
        $schema .= '  "@context": "https://schema.org",'."\n";
        $schema .= '  "@type": "Article",'."\n";
        $schema .= '  "mainEntityOfPage": {'."\n";
        $schema .= '    "@type": "WebPage",'."\n";
        $schema .= '    "@id": "'.$pageUrl.'"'."\n";
        $schema .= '  },'."\n";

        if ($pageImage !== '' && $pageImage !== '0') {
            $schema .= '  "image": "'.$pageImage.'?width=1280",'."\n";
        }

        $schema .= '  "url": "'.$pageUrl.'",'."\n";
        $schema .= '  "headline": "'.htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8').'",'."\n";
        $schema .= '  "description": "'.htmlspecialchars($pageDescription, ENT_QUOTES, 'UTF-8').'",'."\n";
        $schema .= '  "datePublished": "'.schema_date_time_format($this->contentObject->post_published_at ?? $this->contentObject->created_at).'",'."\n";
        $schema .= '  "dateModified": "'.schema_date_time_format($this->contentObject->updated_at).'",'."\n";

        $schema .= '  "author": {'."\n";
        $schema .= '    "@type": "Person",'."\n";

        $authorBase = setting('seo_authors_permalink_base', 'author');

        // Get author data from the relationship (should be eager-loaded)
        $authorName = 'Unknown';
        $authorUsername = '';

        // @phpstan-ignore-next-line booleanAnd.rightAlwaysTrue
        if (isset($this->contentObject->author) && is_object($this->contentObject) && $this->contentObject->author) {
            $authorName = $this->contentObject->author->name ?? 'Unknown';
            $authorUsername = $this->contentObject->author->username ?? '';
        } elseif (is_object($this->contentObject) && method_exists($this->contentObject, 'relationLoaded') && $this->contentObject->relationLoaded('author') && $this->contentObject->author) {
            $authorName = $this->contentObject->author->name ?? 'Unknown';
            $authorUsername = $this->contentObject->author->username ?? '';
        }

        if (! empty($authorUsername)) {
            $schema .= '    "name": "'.htmlspecialchars(ucwords($authorName), ENT_QUOTES, 'UTF-8').'",'."\n";
            $schema .= '    "url": "'.url('/'.$authorBase.'/'.$authorUsername).'"'."\n";
        } else {
            $schema .= '    "name": "'.htmlspecialchars(ucwords($authorName), ENT_QUOTES, 'UTF-8').'"'."\n";
        }

        $schema .= '  },'."\n";

        $schema .= '  "publisher": {'."\n";
        $schema .= '    "@type": "Organization",'."\n";
        $schema .= '    "name": "'.htmlspecialchars($organizationName, ENT_QUOTES, 'UTF-8').'",'."\n";
        $schema .= '    "logo": {'."\n";
        $schema .= '      "@type": "ImageObject",'."\n";
        $schema .= '      "url": "'.$organizationLogo.'"'."\n";
        $schema .= '    }'."\n";
        $schema .= '  }'."\n";

        $schema .= '}'."\n";

        return $schema.'</script>'."\n";
    }

    /**
     * Generate Local Business schema for pages
     *
     * @return string Local Business schema JSON-LD
     */
    public function generateLocalBusinessSchema(): string
    {
        if (! setting('seo_local_seo_is_schema', false)) {
            return '';
        }

        // Check if any local business data exists
        if (empty(setting('seo_local_seo_name', ''))
            && empty(setting('seo_local_seo_street_address', ''))
            && empty(setting('seo_local_seo_locality', ''))) {
            return '';
        }

        $decodeArray = function ($value): array {
            if (is_array($value)) {
                return $value;
            }

            if (is_string($value)) {
                $decoded = json_decode($value, true);

                return is_array($decoded) ? $decoded : [];
            }

            return [];
        };

        $settings = [
            'seo_local_seo_is_schema' => setting('seo_local_seo_is_schema', false),
            'seo_local_seo_type' => setting('seo_local_seo_type', 'Person'),
            'seo_local_seo_business_type' => setting('seo_local_seo_business_type', ''),
            'seo_local_seo_name' => setting('seo_local_seo_name', ''),
            'seo_local_seo_description' => setting('seo_local_seo_description', ''),
            'seo_local_seo_logo_image' => setting('seo_local_seo_logo_image', ''),
            'seo_local_seo_url' => setting('seo_local_seo_url', ''),
            'seo_local_seo_founding_date' => setting('seo_local_seo_founding_date', ''),
            'seo_local_seo_price_range' => setting('seo_local_seo_price_range', ''),
            'seo_local_seo_currencies_accepted' => setting('seo_local_seo_currencies_accepted', ''),
            'seo_local_seo_payment_accepted' => setting('seo_local_seo_payment_accepted', ''),
            'seo_local_seo_street_address' => setting('seo_local_seo_street_address', ''),
            'seo_local_seo_locality' => setting('seo_local_seo_locality', ''),
            'seo_local_seo_region' => setting('seo_local_seo_region', ''),
            'seo_local_seo_postal_code' => setting('seo_local_seo_postal_code', ''),
            'seo_local_seo_country_code' => setting('seo_local_seo_country_code', ''),
            'seo_local_seo_geo_coordinates_latitude' => setting('seo_local_seo_geo_coordinates_latitude', ''),
            'seo_local_seo_geo_coordinates_longitude' => setting('seo_local_seo_geo_coordinates_longitude', ''),
            'seo_local_seo_phone' => setting('seo_local_seo_phone', ''),
            'seo_local_seo_email' => setting('seo_local_seo_email', ''),
            'seo_local_seo_is_opening_hour_24_7' => setting('seo_local_seo_is_opening_hour_24_7', false),
            'seo_local_seo_opening_hour_day' => $decodeArray(setting('seo_local_seo_opening_hour_day', [])),
            'seo_local_seo_opening_hours' => $decodeArray(setting('seo_local_seo_opening_hours', [])),
            'seo_local_seo_closing_hours' => $decodeArray(setting('seo_local_seo_closing_hours', [])),
            'seo_local_seo_phone_number_type' => $decodeArray(setting('seo_local_seo_phone_number_type', [])),
            'seo_local_seo_phone_number' => $decodeArray(setting('seo_local_seo_phone_number', [])),
            'seo_local_seo_facebook_url' => setting('seo_local_seo_facebook_url', ''),
            'seo_local_seo_twitter_url' => setting('seo_local_seo_twitter_url', ''),
            'seo_local_seo_linkedin_url' => setting('seo_local_seo_linkedin_url', ''),
            'seo_local_seo_instagram_url' => setting('seo_local_seo_instagram_url', ''),
            'seo_local_seo_youtube_url' => setting('seo_local_seo_youtube_url', ''),
        ];

        $generator = new LocalSeoSchemaGenerator;

        return $generator->generateScriptTag($settings);
    }

    /**
     * Generate Breadcrumb schema
     *
     * @return string Breadcrumb schema JSON-LD
     */
    public function generateBreadcrumbSchema(): string
    {
        $schema = '';

        if (! setting('seo_enable_breadcrumb_schema', false)) {
            return $schema;
        }

        // Don't show breadcrumb on homepage
        if (isset($this->contentObject->id) && $this->contentObject->id === setting('cms_default_pages_home_page', '')) {
            return $schema;
        }

        // Build breadcrumbs from the content object directly
        $breadcrumbs = $this->buildBreadcrumbsFromContent();

        if (count($breadcrumbs) < 2) {
            return $schema;
        }

        $lastIndex = count($breadcrumbs) - 1;

        $schema .= '<script type="application/ld+json">'."\n";
        $schema .= '{'."\n";
        $schema .= '  "@context": "https://schema.org",'."\n";
        $schema .= '  "@type": "BreadcrumbList",'."\n";
        $schema .= '  "itemListElement": ['."\n";

        foreach ($breadcrumbs as $index => $breadcrumb) {
            if ($index > 0) {
                $schema .= ','."\n";
            }

            $schema .= '    {'."\n";
            $schema .= '      "@type": "ListItem",'."\n";
            $schema .= '      "position": '.($index + 1).','."\n";
            $schema .= '      "name": "'.htmlspecialchars((string) $breadcrumb['label'], ENT_QUOTES, 'UTF-8').'"';

            // Per Google's spec: last item should NOT have "item" property
            if ($index < $lastIndex) {
                $schema .= ','."\n";
                $schema .= '      "item": "'.$breadcrumb['url'].'"'."\n";
            } else {
                $schema .= "\n";
            }

            $schema .= '    }';
        }

        $schema .= "\n".'  ]'."\n";
        $schema .= '}'."\n";

        return $schema.'</script>'."\n";
    }

    /**
     * Build title from template with variable replacement
     *
     * @param  string  $template  Title template with variables like %title%, %site_title%, etc.
     * @return string Built title
     */
    private function buildTitleFromTemplate(string $template): string
    {
        $title = $template;

        // Get content title
        $contentTitle = '';
        if (in_array($this->pluralContentType, ['posts', 'categories', 'pages', 'tags'])) {
            $contentTitle = $this->contentObject->title ?? '';
        } elseif ($this->seoType === 'authors') {
            $contentTitle = $this->contentObject->name ?? '';
        }

        // Replace all possible variables
        $title = str_replace('%title%', htmlspecialchars($contentTitle, ENT_QUOTES, 'UTF-8'), $title);
        $title = str_replace('%site_title%', $this->siteName, $title);
        $title = str_replace('%separator%', setting('seo_separator_character', '-'), $title);

        // For authors
        if ($this->seoType === 'authors') {
            $name = $this->contentObject->name ?? '';
            $title = str_replace('%name%', htmlspecialchars($name, ENT_QUOTES, 'UTF-8'), $title);
            $title = str_replace('%user_name%', htmlspecialchars($name, ENT_QUOTES, 'UTF-8'), $title);
        }

        // For categories
        if ($this->contentType === 'category') {
            $categoryName = $this->contentObject->title ?? '';
            $title = str_replace('%category%', htmlspecialchars($categoryName, ENT_QUOTES, 'UTF-8'), $title);
        }

        // For tags
        if ($this->contentType === 'tag') {
            $tagName = $this->contentObject->title ?? '';
            $title = str_replace('%tag%', htmlspecialchars($tagName, ENT_QUOTES, 'UTF-8'), $title);
        }

        return $title;
    }

    /**
     * Build description from template or content with variable replacement
     *
     * @param  string  $template  Description template with variables like %excerpt%, %title%, etc.
     * @return string Built description
     */
    private function buildDescriptionFromTemplate(string $template): string
    {
        $description = '';

        if ($template !== '' && $template !== '0') {
            // Replace variables in the template
            $description = $template;

            // Get excerpt/content for replacement
            $excerpt = '';
            $content = '';
            $title = '';

            if (in_array($this->pluralContentType, ['posts', 'categories', 'pages', 'tags'])) {
                $excerpt = $this->contentObject->excerpt ?? '';
                $content = $this->contentObject->content ?? '';
                $title = $this->contentObject->title ?? '';
            }

            // Extract text description from content if needed
            if (empty($excerpt) && ! empty($content)) {
                $excerpt = $this->extractPlainTextDescription($content);
            }

            // Replace all possible variables
            $description = str_replace('%excerpt%', $excerpt, $description);
            $description = str_replace('%title%', $title, $description);
            $description = str_replace('%site_title%', $this->siteName, $description);

            // For authors
            if ($this->seoType === 'authors') {
                $bio = empty($this->contentObject->bio ?? null)
                    ? ''
                    : $this->extractPlainTextDescription($this->contentObject->bio);
                $description = str_replace('%user_bio%', $bio, $description);
                $description = str_replace('%bio%', $bio, $description);
            }
        }

        // Fallback to content extraction if template resulted in empty string
        if (empty($description)) {
            if (in_array($this->pluralContentType, ['posts', 'categories', 'pages', 'tags'])) {
                $description = $this->extractPlainTextDescription($this->contentObject->content ?? '');
            } elseif ($this->seoType === 'authors') {
                $description = empty($this->contentObject->bio ?? null)
                    ? ''
                    : $this->extractPlainTextDescription($this->contentObject->bio);
            }
        }

        return $description;
    }

    /**
     * Build breadcrumbs array from the content object
     *
     * @return array Breadcrumbs array with label and url
     */
    private function buildBreadcrumbsFromContent(): array
    {
        $breadcrumbs = [];

        // Always start with Home
        $breadcrumbs[] = [
            'label' => 'Home',
            'url' => url('/'),
        ];

        if (is_null($this->contentObject)) {
            return $breadcrumbs;
        }

        // Add category for posts
        if ($this->contentType === 'post' && isset($this->contentObject->category) && $this->contentObject->category) {
            $category = $this->contentObject->category;
            $categoryUrl = '';

            // Try to get category URL via permalink_url accessor if available
            if (isset($category->permalink_url)) {
                $categoryUrl = url($category->permalink_url);
            } elseif (isset($category->slug)) {
                $categoryBase = setting('seo_categories_permalink_base', '');
                $categoryUrl = url(($categoryBase ? $categoryBase.'/' : '').$category->slug);
            }

            if (isset($category->title) && ! empty($categoryUrl)) {
                $breadcrumbs[] = [
                    'label' => $category->title,
                    'url' => $categoryUrl,
                ];
            }
        }

        // Add parent page for pages with parent
        if ($this->contentType === 'page' && isset($this->contentObject->parent) && $this->contentObject->parent) {
            $parent = $this->contentObject->parent;
            $parentUrl = '';

            if (isset($parent->permalink_url)) {
                $parentUrl = url($parent->permalink_url);
            } elseif (isset($parent->slug)) {
                $parentUrl = url($parent->slug);
            }

            if (isset($parent->title) && ! empty($parentUrl)) {
                $breadcrumbs[] = [
                    'label' => $parent->title,
                    'url' => $parentUrl,
                ];
            }
        }

        // Add current page/post
        $currentUrl = $this->generateCanonicalUrl();
        $currentTitle = '';

        if (isset($this->contentObject->title)) {
            $currentTitle = $this->contentObject->title;
        } elseif (isset($this->contentObject->name)) {
            $currentTitle = $this->contentObject->name;
        }

        if (! empty($currentTitle)) {
            $breadcrumbs[] = [
                'label' => $currentTitle,
                'url' => $currentUrl,
            ];
        }

        return $breadcrumbs;
    }

    /**
     * Get SEO data from object's seo_data JSON column
     *
     * @return array SEO data array with meta_title, meta_description, meta_robots, etc.
     */
    private function getSeoDataFromObject(): array
    {
        $seoData = [];

        if (isset($this->contentObject->seo_data)) {
            // If seo_data is already an array (from accessor), use it
            if (is_array($this->contentObject->seo_data)) {
                $seoData = $this->contentObject->seo_data;
            }
            // If it's a JSON string, decode it
            elseif (is_string($this->contentObject->seo_data)) {
                $decoded = json_decode($this->contentObject->seo_data, true);
                $seoData = is_array($decoded) ? $decoded : [];
            }
        }

        return $seoData;
    }

    /**
     * Get Open Graph data from object's og_data JSON column
     *
     * @return array OG data array with og_title, og_description, og_image, etc.
     */
    private function getOgDataFromObject(): array
    {
        $ogData = [];

        if (isset($this->contentObject->og_data)) {
            // If og_data is already an array (from accessor), use it
            if (is_array($this->contentObject->og_data)) {
                $ogData = $this->contentObject->og_data;
            }
            // If it's a JSON string, decode it
            elseif (is_string($this->contentObject->og_data)) {
                $decoded = json_decode($this->contentObject->og_data, true);
                $ogData = is_array($decoded) ? $decoded : [];
            }
        }

        return $ogData;
    }
}
