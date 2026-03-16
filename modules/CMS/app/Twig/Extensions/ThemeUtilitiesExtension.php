<?php

namespace Modules\CMS\Twig\Extensions;

use App\Models\CustomMedia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Modules\CMS\Services\ThemeDataService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * ThemeUtilitiesExtension
 *
 * Provides security utilities, development helpers, and context detection
 * functions for theme templates.
 */
class ThemeUtilitiesExtension extends AbstractExtension
{
    protected ?ThemeDataService $themeDataService = null;

    public function getFunctions(): array
    {
        return [
            // Context Detection Functions
            new TwigFunction('is_single', $this->isSingle(...)),
            new TwigFunction('is_page', $this->isPage(...)),
            new TwigFunction('is_archive', $this->isArchive(...)),
            new TwigFunction('is_author', $this->isAuthor(...)),
            new TwigFunction('is_search', $this->isSearch(...)),
            new TwigFunction('is_home', $this->isHome(...)),
            new TwigFunction('is_404', $this->is404(...)),

            // Security Utilities
            new TwigFunction('esc_html', $this->escHtml(...)),
            new TwigFunction('esc_attr', $this->escAttr(...)),
            new TwigFunction('esc_url', $this->escUrl(...)),
            new TwigFunction('esc_js', $this->escJs(...)),
            new TwigFunction('nonce_field', $this->nonceField(...), ['is_safe' => ['html']]),
            new TwigFunction('verify_nonce', $this->verifyNonce(...)),
            new TwigFunction('sanitize_html', $this->sanitizeHtml(...), ['is_safe' => ['html']]),
            new TwigFunction('is_admin', $this->isAdmin(...)),
            new TwigFunction('can', $this->can(...)),

            // Development Utilities
            new TwigFunction('dump', $this->dump(...), ['is_safe' => ['html']]),
            new TwigFunction('placeholder_image', $this->placeholderImage(...)),
            new TwigFunction('svg_icon', $this->svgIcon(...), ['is_safe' => ['html']]),
            new TwigFunction('time_ago', $this->timeAgo(...)),
            new TwigFunction('reading_time', $this->readingTime(...)),
            new TwigFunction('word_count', $this->wordCount(...)),
            new TwigFunction('truncate_words', $this->truncateWords(...)),
            new TwigFunction('asset_version', $this->assetVersion(...)),
            new TwigFunction('json_encode', $this->jsonEncode(...), ['is_safe' => ['html', 'js']]),

            // Image Utilities
            new TwigFunction('image_url', $this->imageUrl(...)),
            new TwigFunction('srcset', $this->srcset(...)),
            new TwigFunction('lazy_image', $this->lazyImage(...), ['is_safe' => ['html']]),

            // Pagination
            new TwigFunction('paginate_links', $this->paginateLinks(...), ['is_safe' => ['html']]),
        ];
    }

    public function getFilters(): array
    {
        return [
            // Convenience filters for common escaping
            new TwigFilter('esc_html', $this->escHtml(...)),
            new TwigFilter('esc_attr', $this->escAttr(...)),
            new TwigFilter('esc_url', $this->escUrl(...)),
            new TwigFilter('esc_js', $this->escJs(...)),
            new TwigFilter('time_ago', $this->timeAgo(...)),
            new TwigFilter('reading_time', $this->readingTime(...)),
            new TwigFilter('word_count', $this->wordCount(...)),
            new TwigFilter('truncate_words', $this->truncateWords(...)),
        ];
    }

    // =========================================================================
    // Context Detection Functions
    // =========================================================================

    /**
     * Check if viewing a single post.
     */
    public function isSingle(): bool
    {
        return $this->getThemeDataService()->is('single');
    }

    /**
     * Check if viewing a page.
     */
    public function isPage(): bool
    {
        return $this->getThemeDataService()->is('page');
    }

    /**
     * Check if viewing an archive.
     */
    public function isArchive(): bool
    {
        return $this->getThemeDataService()->is('archive');
    }

    /**
     * Check if viewing an author page.
     */
    public function isAuthor(): bool
    {
        return $this->getThemeDataService()->is('author');
    }

    /**
     * Check if viewing search results.
     */
    public function isSearch(): bool
    {
        return $this->getThemeDataService()->is('search');
    }

    /**
     * Check if viewing homepage.
     */
    public function isHome(): bool
    {
        return $this->getThemeDataService()->is('home');
    }

    /**
     * Check if viewing 404 page.
     */
    public function is404(): bool
    {
        return $this->getThemeDataService()->is('404');
    }

    // =========================================================================
    // Security Utilities
    // =========================================================================

    /**
     * Escape string for safe HTML output.
     */
    public function escHtml(?string $string): string
    {
        if ($string === null) {
            return '';
        }

        return htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8', false);
    }

    /**
     * Escape string for use in HTML attributes.
     */
    public function escAttr(?string $string): string
    {
        if ($string === null) {
            return '';
        }

        return htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8', true);
    }

    /**
     * Sanitize and validate a URL.
     */
    public function escUrl(?string $url): string
    {
        if ($url === null || $url === '') {
            return '';
        }

        // Trim whitespace
        $url = trim($url);

        // Check for dangerous protocols
        $dangerousProtocols = ['javascript:', 'data:', 'vbscript:'];
        foreach ($dangerousProtocols as $protocol) {
            if (stripos($url, $protocol) === 0) {
                return '';
            }
        }

        // Encode the URL
        return htmlspecialchars($url, ENT_QUOTES | ENT_HTML5, 'UTF-8', true);
    }

    /**
     * Escape string for use in inline JavaScript.
     */
    public function escJs(?string $string): string
    {
        if ($string === null) {
            return '';
        }

        return addslashes($string);
    }

    /**
     * Generate a CSRF hidden field with optional action-based nonce.
     */
    public function nonceField(?string $action = null): string
    {
        $token = csrf_token();

        $html = '<input type="hidden" name="_token" value="'.htmlspecialchars((string) $token, ENT_QUOTES).'">';

        if ($action) {
            $nonce = $this->generateNonce($action);
            $html .= '<input type="hidden" name="_nonce" value="'.htmlspecialchars($nonce, ENT_QUOTES).'">';
            $html .= '<input type="hidden" name="_action" value="'.htmlspecialchars($action, ENT_QUOTES).'">';
        }

        return $html;
    }

    /**
     * Verify an action-based nonce.
     */
    public function verifyNonce(string $action): bool
    {
        $nonce = request()->input('_nonce');
        $requestAction = request()->input('_action');

        if (! $nonce || $requestAction !== $action) {
            return false;
        }

        $expected = $this->generateNonce($action);

        return hash_equals($expected, $nonce);
    }

    /**
     * Strip unsafe HTML, keeping only allowed tags.
     */
    public function sanitizeHtml(?string $html, array $allowedTags = ['p', 'a', 'strong', 'em', 'br', 'ul', 'ol', 'li']): string
    {
        if ($html === null) {
            return '';
        }

        $allowedTagString = '<'.implode('><', $allowedTags).'>';

        return strip_tags($html, $allowedTagString);
    }

    /**
     * Check if current user is an admin.
     */
    public function isAdmin(): bool
    {
        if (! Auth::check()) {
            return false;
        }

        return Auth::user()->hasRole(['super', 'admin']);
    }

    /**
     * Check if current user has a specific permission.
     */
    public function can(string $permission): bool
    {
        if (! Auth::check()) {
            return false;
        }

        return Auth::user()->can($permission);
    }

    // =========================================================================
    // Development Utilities
    // =========================================================================

    /**
     * Dump variable for debugging (only in dev mode).
     */
    public function dump($var): string
    {
        if (! config('app.debug')) {
            return '';
        }

        ob_start();
        echo '<pre style="background:#1e1e1e;color:#d4d4d4;padding:1rem;margin:1rem 0;border-radius:4px;overflow:auto;font-size:12px;">';
        var_dump($var);
        echo '</pre>';

        return ob_get_clean();
    }

    /**
     * Generate a placeholder image URL.
     */
    public function placeholderImage(int $width = 300, int $height = 200, ?string $text = null): string
    {
        return get_placeholder_image_url();
    }

    /**
     * Get an SVG icon by name.
     */
    public function svgIcon(string $name, ?string $class = null): string
    {
        $classAttr = $class ? ' class="'.htmlspecialchars($class).'"' : '';

        // Return a Remix Icon (since that's what the theme uses)
        return sprintf('<i class="ri-%s"%s></i>', $name, $classAttr);
    }

    /**
     * Get human-readable relative time.
     */
    public function timeAgo($date): string
    {
        if ($date === null) {
            return '';
        }

        if (is_string($date)) {
            $date = Date::parse($date);
        }

        return $date->diffForHumans();
    }

    /**
     * Calculate reading time from content.
     */
    public function readingTime(?string $content, int $wordsPerMinute = 200): string
    {
        $minutes = $this->getReadingTimeMinutes($content, $wordsPerMinute);

        return $minutes.' min read';
    }

    /**
     * Count words in content.
     */
    public function wordCount(?string $content): int
    {
        if (in_array($content, [null, '', '0'], true)) {
            return 0;
        }

        return str_word_count(strip_tags($content));
    }

    /**
     * Truncate text by word count.
     */
    public function truncateWords(?string $text, int $count = 25, string $suffix = '...'): string
    {
        if (in_array($text, [null, '', '0'], true)) {
            return '';
        }

        $text = strip_tags($text);
        $words = explode(' ', $text);

        if (count($words) <= $count) {
            return $text;
        }

        return implode(' ', array_slice($words, 0, $count)).$suffix;
    }

    /**
     * Get cache-busted asset URL.
     */
    public function assetVersion(string $path): string
    {
        $fullPath = public_path($path);

        if (file_exists($fullPath)) {
            $version = filemtime($fullPath);

            return asset($path).'?v='.$version;
        }

        return asset($path);
    }

    /**
     * JSON encode data safely for embedding in script tags.
     */
    public function jsonEncode($data): string
    {
        return json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
    }

    // =========================================================================
    // Image Utilities
    // =========================================================================

    /**
     * Get image URL by ID and size.
     */
    public function imageUrl(?int $id, string $size = 'full'): string
    {
        if (! $id) {
            return '';
        }

        $media = CustomMedia::query()->find($id);

        if (! $media) {
            return '';
        }

        return match ($size) {
            'thumbnail' => $media->getThumbnailUrl() ?? $media->url,
            'medium' => $media->getMediumUrl() ?? $media->url,
            'large' => $media->getLargeUrl() ?? $media->url,
            default => $media->url,
        };
    }

    /**
     * Generate srcset attribute for responsive images.
     */
    public function srcset(?int $id, array $sizes = ['sm', 'md', 'lg']): string
    {
        if (! $id) {
            return '';
        }

        $media = CustomMedia::query()->find($id);

        if (! $media) {
            return '';
        }

        $srcsets = [];

        $sizeMap = [
            'sm' => ['url' => $media->getThumbnailUrl() ?? $media->url, 'width' => 320],
            'md' => ['url' => $media->getMediumUrl() ?? $media->url, 'width' => 768],
            'lg' => ['url' => $media->getLargeUrl() ?? $media->url, 'width' => 1024],
            'full' => ['url' => $media->url, 'width' => $media->width ?? 1920],
        ];

        foreach ($sizes as $size) {
            if (isset($sizeMap[$size])) {
                $srcsets[] = $sizeMap[$size]['url'].' '.$sizeMap[$size]['width'].'w';
            }
        }

        return implode(', ', $srcsets);
    }

    /**
     * Generate a lazy-loaded image tag.
     */
    public function lazyImage(?int $id, array $options = []): string
    {
        if (! $id) {
            return '';
        }

        $media = CustomMedia::query()->find($id);

        if (! $media) {
            return '';
        }

        $class = $options['class'] ?? '';
        $alt = htmlspecialchars($options['alt'] ?? $media->alt_text ?? $media->title ?? '', ENT_QUOTES);
        $width = $options['width'] ?? $media->width ?? '';
        $height = $options['height'] ?? $media->height ?? '';

        $widthAttr = $width ? ' width="'.$width.'"' : '';
        $heightAttr = $height ? ' height="'.$height.'"' : '';
        $classAttr = $class ? ' class="'.htmlspecialchars($class).'"' : '';

        return sprintf(
            '<img src="%s" alt="%s" loading="lazy"%s%s%s>',
            htmlspecialchars((string) $media->url),
            $alt,
            $classAttr,
            $widthAttr,
            $heightAttr
        );
    }

    // =========================================================================
    // Pagination
    // =========================================================================

    /**
     * Generate pagination links.
     */
    public function paginateLinks($paginator, array $options = []): string
    {
        if (! $paginator || ! $paginator->hasPages()) {
            return '';
        }

        $class = $options['class'] ?? 'pagination';
        $prevText = $options['prev_text'] ?? '&laquo; Previous';
        $nextText = $options['next_text'] ?? 'Next &raquo;';

        $html = '<nav aria-label="Pagination"><ul class="'.$class.'">';

        // Previous
        if ($paginator->onFirstPage()) {
            $html .= '<li class="page-item disabled"><span class="page-link">'.$prevText.'</span></li>';
        } else {
            $html .= '<li class="page-item"><a class="page-link" href="'.$paginator->previousPageUrl().'">'.$prevText.'</a></li>';
        }

        // Page numbers
        $currentPage = $paginator->currentPage();
        $lastPage = $paginator->lastPage();

        for ($i = 1; $i <= $lastPage; $i++) {
            // Show first, last, current, and 2 pages around current
            if ($i === 1 || $i === $lastPage || abs($i - $currentPage) <= 2) {
                $activeClass = $i === $currentPage ? ' active' : '';
                $html .= '<li class="page-item'.$activeClass.'">';

                if ($i === $currentPage) {
                    $html .= '<span class="page-link">'.$i.'</span>';
                } else {
                    $html .= '<a class="page-link" href="'.$paginator->url($i).'">'.$i.'</a>';
                }

                $html .= '</li>';
            } elseif (abs($i - $currentPage) === 3) {
                $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
        }

        // Next
        if ($paginator->hasMorePages()) {
            $html .= '<li class="page-item"><a class="page-link" href="'.$paginator->nextPageUrl().'">'.$nextText.'</a></li>';
        } else {
            $html .= '<li class="page-item disabled"><span class="page-link">'.$nextText.'</span></li>';
        }

        return $html.'</ul></nav>';
    }

    protected function getThemeDataService(): ThemeDataService
    {
        if (! $this->themeDataService instanceof ThemeDataService) {
            $this->themeDataService = resolve(ThemeDataService::class);
        }

        return $this->themeDataService;
    }

    /**
     * Generate a nonce for an action.
     */
    protected function generateNonce(string $action): string
    {
        $secret = config('app.key');
        $userId = Auth::id() ?? 0;
        $date = date('Y-m-d');

        return hash_hmac('sha256', sprintf('%s|%s|%s', $action, $userId, $date), (string) $secret);
    }

    /**
     * Get reading time in minutes.
     */
    protected function getReadingTimeMinutes(?string $content, int $wordsPerMinute = 200): int
    {
        if (in_array($content, [null, '', '0'], true)) {
            return 1;
        }

        $wordCount = str_word_count(strip_tags($content));

        return max(1, (int) ceil($wordCount / $wordsPerMinute));
    }
}
