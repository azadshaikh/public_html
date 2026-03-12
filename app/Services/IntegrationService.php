<?php

namespace App\Services;

/**
 * IntegrationService - Renders third-party integration scripts
 *
 * Reads settings from seo_integrations_* keys and injects:
 * - Tracking scripts (GA, GTM, Meta Pixel, Clarity)
 * - Webmaster verification meta tags
 * - AdSense code (with conditional logic)
 * - Custom scripts
 */
class IntegrationService
{
    /**
     * Allowed HTML tags in <head> section
     * Only these tags are valid and won't break the page
     */
    protected const ALLOWED_HEAD_TAGS = ['meta', 'script', 'style', 'link', 'noscript'];

    public function __construct(public string $type = 'header') {}

    public function getIntegrationCode(): string
    {
        $code = '';

        if ($this->type === 'header') {
            $code .= $this->getWebmasterMetaTags();
            $code .= $this->getTrackingScripts();
            $code .= $this->getAdSenseCode();
        }

        return $code;
    }

    /**
     * Sanitize HTML content to only include valid <head> tags.
     * Invalid tags (like <a>, <div>, etc.) are stripped to prevent
     * breaking the page layout by pushing content to body.
     */
    protected function sanitizeHeadContent(string $content): string
    {
        $content = trim($content);
        if ($content === '' || $content === '0') {
            return '';
        }

        // Build regex pattern for allowed tags
        $allowedTagsPattern = implode('|', self::ALLOWED_HEAD_TAGS);

        // Match only allowed tags (opening, closing, and self-closing)
        // This regex captures complete tags including their content
        $pattern = '/<('.$allowedTagsPattern.')(\s[^>]*)?\s*\/?>(?:.*?<\/\1>)?/is';

        preg_match_all($pattern, $content, $matches);

        if (empty($matches[0])) {
            return '';
        }

        return implode("\n", $matches[0]);
    }

    /**
     * Generate webmaster verification meta tags
     * Users paste full meta tags directly, we sanitize and output them
     */
    protected function getWebmasterMetaTags(): string
    {
        $metaTags = '';

        $webmasterSettings = [
            'google_search_console',
            'bing_webmaster',
            'baidu_webmaster',
            'yandex_verification',
            'pinterest_verification',
            'norton_verification',
            'custom_meta_tags',
        ];

        foreach ($webmasterSettings as $settingKey) {
            $value = trim((string) setting('seo_integrations_'.$settingKey, ''));
            if ($value !== '' && $value !== '0') {
                $sanitized = $this->sanitizeHeadContent($value);
                if ($sanitized !== '' && $sanitized !== '0') {
                    $metaTags .= $sanitized."\n";
                }
            }
        }

        return $metaTags;
    }

    /**
     * Get tracking scripts (Analytics, GTM, Pixel, Clarity, etc.)
     */
    protected function getTrackingScripts(): string
    {
        $scripts = '';

        // Google Analytics
        $googleAnalytics = setting('seo_integrations_google_analytics', '');
        if (! empty($googleAnalytics)) {
            $sanitized = $this->sanitizeHeadContent($googleAnalytics);
            if ($sanitized !== '' && $sanitized !== '0') {
                $scripts .= $sanitized."\n";
            }
        }

        // Google Tag Manager
        $googleTags = setting('seo_integrations_google_tags', '');
        if (! empty($googleTags)) {
            $sanitized = $this->sanitizeHeadContent($googleTags);
            if ($sanitized !== '' && $sanitized !== '0') {
                $scripts .= $sanitized."\n";
            }
        }

        // Meta Pixel (Facebook)
        $metaPixel = setting('seo_integrations_meta_pixel', '');
        if (! empty($metaPixel)) {
            $sanitized = $this->sanitizeHeadContent($metaPixel);
            if ($sanitized !== '' && $sanitized !== '0') {
                $scripts .= $sanitized."\n";
            }
        }

        // Microsoft Clarity
        $clarity = setting('seo_integrations_ms_clarity', '');
        if (! empty($clarity)) {
            $sanitized = $this->sanitizeHeadContent($clarity);
            if ($sanitized !== '' && $sanitized !== '0') {
                $scripts .= $sanitized."\n";
            }
        }

        // Other/Custom scripts
        $other = setting('seo_integrations_other', '');
        if (! empty($other)) {
            $sanitized = $this->sanitizeHeadContent($other);
            if ($sanitized !== '' && $sanitized !== '0') {
                $scripts .= $sanitized."\n";
            }
        }

        return $scripts;
    }

    /**
     * Get AdSense code with conditional display logic
     */
    protected function getAdSenseCode(): string
    {
        // Check if AdSense is enabled
        if (! filter_var(setting('seo_integrations_google_adsense_enabled', false), FILTER_VALIDATE_BOOLEAN)) {
            return '';
        }

        // Exclude auth routes
        $authRoutes = [
            'register', 'login', 'password.request', 'password.email',
            'password.reset', 'password.update', 'verification.notice',
            'user.verification', 'verification.send', 'password.confirm', 'logout',
        ];

        $currentRoute = request()->route()?->getName();
        if (in_array($currentRoute, $authRoutes)) {
            return '';
        }

        // Check if ads should be hidden for logged-in users
        if (filter_var(setting('seo_integrations_google_adsense_hide_for_logged_in', false), FILTER_VALIDATE_BOOLEAN) && auth()->check()) {
            return '';
        }

        // Check if ads should be hidden on homepage
        if ($currentRoute === 'home' && filter_var(setting('seo_integrations_google_adsense_hide_on_homepage', false), FILTER_VALIDATE_BOOLEAN)) {
            return '';
        }

        $adsenseCode = setting('seo_integrations_google_adsense_code', '');
        $sanitized = $this->sanitizeHeadContent($adsenseCode);

        return $sanitized === '' || $sanitized === '0' ? '' : $sanitized."\n";
    }
}
