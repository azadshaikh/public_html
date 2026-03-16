<?php

namespace Modules\CMS\Services;

use Exception;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\CMS\Helpers\HtmlMinifier;
use Modules\CMS\Models\Theme;
use Modules\CMS\View\Components\AdminBar as AdminBarViewComponent;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class ThemeService
{
    protected ?string $adminBarMarkup = null;

    /**
     * Page-level CSS to inject before </head>
     */
    protected ?string $pageCss = null;

    /**
     * Page-level JS to inject before </body>
     */
    protected ?string $pageJs = null;

    public function __construct(
        protected TwigService $twigService,
        protected FrontendFaviconService $frontendFaviconService
    ) {}

    /**
     * Render template with theme data using Twig
     */
    public function renderThemeTemplate(string $template, array $data = [], int $status = 200): Response
    {
        $activeTheme = Theme::getActiveTheme();

        if (! $activeTheme) {
            return $this->fallbackResponse($template, $data, $status);
        }

        // Convert .tpl extension to .twig if present
        $template = preg_replace('/\.tpl$/', '.twig', $template);

        // Configure Twig for this theme
        $this->twigService->setTheme($activeTheme['directory']);

        // Add global theme data
        $data['theme'] = $activeTheme;

        // Extract page-level CSS/JS for auto-injection
        $this->extractPageAssets($data);

        try {
            // Render the Twig template
            $content = $this->twigService->render($template, $data);

            // Head integrations are rendered via {{ seo_meta() }}; footer scripts are injected here.
            return $this->applyFrontendEnhancements(response($content, $status));
        } catch (Exception $exception) {
            // Log the error for debugging
            Log::error('Twig template rendering failed in ThemeService', [
                'template' => $template,
                'theme' => $activeTheme['directory'],
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            // In debug mode, show the actual error
            if (config('app.debug')) {
                // Extract actual template file and line number from error message
                $actualLine = 0;
                $codePreview = [];

                // Try to extract line number from Twig error message
                if (preg_match('/at line (\d+)/', $exception->getMessage(), $matches)) {
                    $actualLine = (int) $matches[1];
                }

                // Get the actual template file path
                $themePath = Theme::getThemesPath().'/'.$activeTheme['directory'];
                $templatePath = $themePath.'/'.$template;

                // Read template file and extract lines around error
                if (file_exists($templatePath) && $actualLine > 0) {
                    $lines = file($templatePath);
                    $startLine = max(1, $actualLine - 5);
                    $endLine = min(count($lines), $actualLine + 5);

                    for ($i = $startLine; $i <= $endLine; $i++) {
                        $codePreview[$i] = rtrim($lines[$i - 1]);
                    }
                }

                $errorHtml = view('errors.twig-error', [
                    'template' => $template,
                    'theme' => $activeTheme['directory'],
                    'error' => $exception->getMessage(),
                    'file' => '/'.$template,
                    'line' => $actualLine,
                    'codePreview' => $codePreview,
                    'trace' => '',
                ])->render();

                return $this->applyFrontendEnhancements(response($errorHtml, 500));
            }

            // If template fails to render, fall back to basic response
            return $this->fallbackResponse($template, $data, $status);
        }
    }

    /**
     * Fallback response when theme template fails
     */
    public function fallbackResponse(string $context, array $data = [], int $status = 200): Response
    {
        // Try to use fallback views or create a basic response
        try {
            $response = response()->view('fallback.page', $data, $status);

            return $this->applyFrontendEnhancements($response);
        } catch (Exception) {
            // Ultimate fallback - basic HTML response
            $page = $data['page'] ?? null;
            if ($page) {
                $title = e($page->title);
                $content = safe_content($page->content ?? '');
                $html = sprintf('<!DOCTYPE html><html><head><title>%s</title></head><body>', $title);
                $html .= sprintf('<h1>%s</h1>', $title);
                $html .= sprintf('<div>%s</div>', $content);
                $html .= '</body></html>';

                return $this->applyFrontendEnhancements(response($html, $status));
            }

            return $this->applyFrontendEnhancements(response('Page not found', 404));
        }
    }

    /**
     * Get theme asset securely
     */
    public function getThemeAsset(string $theme, string $asset): BinaryFileResponse
    {
        // Security: Block directory traversal attempts
        abort_if(str_contains($asset, '..') || str_contains($asset, '\\'), 403, 'Access denied');

        // Security: Block sensitive theme files and directories
        $blockedPrefixes = ['config/', 'templates/', 'layouts/', 'widgets/'];
        foreach ($blockedPrefixes as $prefix) {
            abort_if(str_starts_with($asset, $prefix), 403, 'Access denied');
        }

        $blockedFiles = ['manifest.json', '.active_theme'];
        abort_if(in_array($asset, $blockedFiles, true), 403, 'Access denied');

        // Security: Only allow specific static asset file extensions
        $allowedExtensions = [
            // Stylesheets
            'css', 'scss', 'sass', 'less',
            // JavaScript
            'js', 'mjs', 'ts',
            // Images
            'jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'ico', 'bmp',
            // Fonts
            'woff', 'woff2', 'ttf', 'otf', 'eot',
            // Documents
            'pdf', 'txt', 'md',
            // Media
            'mp4', 'webm', 'ogg', 'mp3', 'wav',
            // Data
            'json', 'xml', 'csv',
            // Archives (for downloadable assets)
            'zip', 'tar', 'gz',
        ];

        $fileExtension = strtolower(pathinfo($asset, PATHINFO_EXTENSION));

        // Security: Block if extension is not in allowed list
        if (! in_array($fileExtension, $allowedExtensions)) {
            // Log security attempt for monitoring
            Log::warning('SECURITY: Blocked theme asset access to file with extension: '.$fileExtension, [
                'theme' => $theme,
                'asset' => $asset,
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
            abort(403, 'File type not allowed');
        }

        // Security: Explicitly block dangerous file patterns
        $dangerousPatterns = [
            '/\.php/i',
            '/\.phtml/i',
            '/\.php\d/i',
            '/\.phar/i',
            '/\.htaccess/i',
            '/\.env/i',
            '/\.ini/i',
            '/\.conf/i',
            '/\.sh/i',
            '/\.bat/i',
            '/\.exe/i',
            '/\.dll/i',
        ];

        foreach ($dangerousPatterns as $pattern) {
            if (preg_match($pattern, $asset)) {
                Log::warning('SECURITY: Blocked theme asset access to dangerous file pattern', [
                    'theme' => $theme,
                    'asset' => $asset,
                    'pattern' => $pattern,
                    'ip' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);
                abort(403, 'Access denied');
            }
        }

        // Resolve asset path with child theme cascade support
        $assetPath = $this->resolveThemeAssetPath($theme, $asset);

        abort_unless((bool) $assetPath, 404);

        // Additional security: Ensure the resolved path is within theme directories
        $themesBasePath = realpath(Theme::getThemesPath());
        $realAssetPath = realpath($assetPath);

        if (! $themesBasePath || ! $realAssetPath || ! str_starts_with($realAssetPath, $themesBasePath)) {
            Log::warning('SECURITY: Path traversal attempt detected in theme assets', [
                'theme' => $theme,
                'asset' => $asset,
                'resolved_path' => $realAssetPath,
                'themes_base' => $themesBasePath,
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
            abort(403, 'Access denied');
        }

        $mimeType = mime_content_type($assetPath);

        // Ensure proper MIME types for critical web assets
        $extension = strtolower(pathinfo($asset, PATHINFO_EXTENSION));
        switch ($extension) {
            case 'css':
                $mimeType = 'text/css';
                break;
            case 'js':
                $mimeType = 'application/javascript';
                break;
            case 'json':
                $mimeType = 'application/json';
                break;
            case 'svg':
                $mimeType = 'image/svg+xml';
                break;
            case 'woff':
                $mimeType = 'font/woff';
                break;
            case 'woff2':
                $mimeType = 'font/woff2';
                break;
            case 'ttf':
                $mimeType = 'font/ttf';
                break;
            case 'otf':
                $mimeType = 'font/otf';
                break;
            case 'eot':
                $mimeType = 'application/vnd.ms-fontobject';
                break;
        }

        return response()->file($assetPath, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'public, max-age=31536000', // 1 year cache
        ]);
    }

    /**
     * Generate custom CSS for theme
     */
    public function generateCustomCSS(string $theme): Response
    {
        $css = theme_generate_custom_css($theme);

        // Add any custom CSS from theme options
        $customCSS = theme_get_option('custom_css', '');
        if ($customCSS) {
            $decoded = base64_decode((string) $customCSS, true);
            if ($decoded !== false) {
                $customCSS = $decoded;
            }

            $css .= "\n\n/* Custom CSS */\n".$customCSS;
        }

        return response($css, 200, [
            'Content-Type' => 'text/css',
            'Cache-Control' => 'public, max-age=3600',
            'ETag' => '"'.md5($css).'"',
        ]);
    }

    /**
     * Extract page-level CSS and JS from template data for auto-injection.
     * Searches all array values in data for 'css' and 'js' fields.
     * Supports both transformed arrays and Eloquent model objects.
     */
    protected function extractPageAssets(array $data): void
    {
        // Reset previous values
        $this->pageCss = null;
        $this->pageJs = null;

        // Search all top-level values for css/js fields
        foreach ($data as $value) {
            // Handle transformed arrays (from ContentTransformer)
            if (is_array($value) && (isset($value['css']) || isset($value['js']))) {
                $this->pageCss = $value['css'] ?? null;
                $this->pageJs = $value['js'] ?? null;

                return;
            }

            // Handle Eloquent model objects (from builder/editor context)
            if (is_object($value) && method_exists($value, 'getAttribute')) {
                $css = $value->getAttribute('css');
                $js = $value->getAttribute('js');
                if ($css || $js) {
                    $this->pageCss = $css;
                    $this->pageJs = $js;

                    return;
                }
            }
        }
    }

    /**
     * Apply frontend enhancements: custom CSS, custom JS, admin bar, and HTML minification.
     * Order matters:
     * 1. Page CSS - injected before </head> (page builder styles)
     * 2. Custom CSS - injected before </head> (after theme CSS so it can override)
     * 3. Page JS - injected before </body> (page builder scripts)
     * 4. Custom JS - injected before </body> (after theme JS so it can use theme functions)
     * 5. Admin bar - injected before </body>
     * 6. HTML minification - applied last to minify the final output
     */
    protected function applyFrontendEnhancements(Response $response): Response
    {
        $response = $this->applyFaviconTags($response);
        $response = $this->applyPageCss($response);
        $response = $this->applyCustomCss($response);
        $response = $this->applyPageJs($response);
        $response = $this->applyCustomJs($response);
        $response = $this->applyAdminBar($response);

        return $this->applyHtmlMinification($response);
    }

    /**
     * Auto-inject favicon markup for frontend themes and remove duplicate theme-defined tags.
     */
    protected function applyFaviconTags(Response $response): Response
    {
        $contentType = $response->headers->get('Content-Type');

        if ($contentType && ! str_contains(strtolower($contentType), 'text/html')) {
            return $response;
        }

        $content = $response->getContent();

        if (! is_string($content) || trim($content) === '') {
            return $response;
        }

        $headPos = stripos($content, '</head>');
        if ($headPos === false) {
            return $response;
        }

        // Remove old auto-injected block and any theme-defined favicon tags to prevent duplicates.
        $content = preg_replace('/<!--\s*cms-auto-favicon:start\s*-->.*?<!--\s*cms-auto-favicon:end\s*-->/is', '', $content) ?? $content;
        $content = $this->stripExistingFaviconTags($content);

        $faviconMarkup = $this->frontendFaviconService->renderHeadMarkup();
        $headPos = stripos($content, '</head>');

        if ($headPos === false) {
            return $response;
        }

        $updatedContent = substr($content, 0, $headPos).$faviconMarkup.substr($content, $headPos);
        $response->setContent($updatedContent);

        return $response;
    }

    /**
     * Remove existing favicon/manifest related tags so auto-injection remains the single output.
     */
    protected function stripExistingFaviconTags(string $content): string
    {
        $patterns = [
            '/<link\b[^>]*\brel\s*=\s*["\'](?:icon|shortcut\s+icon|apple-touch-icon)["\'][^>]*>/i',
            '/<link\b[^>]*\brel\s*=\s*["\']manifest["\'][^>]*>/i',
            '/<meta\b[^>]*\bname\s*=\s*["\']theme-color["\'][^>]*>/i',
            '/<meta\b[^>]*\bname\s*=\s*["\']apple-mobile-web-app-title["\'][^>]*>/i',
        ];

        foreach ($patterns as $pattern) {
            $content = preg_replace($pattern, '', $content) ?? $content;
        }

        return $content;
    }

    /**
     * Inject page-level CSS (from page builder) before </head>.
     */
    protected function applyPageCss(Response $response): Response
    {
        if (in_array($this->pageCss, [null, '', '0'], true)) {
            return $response;
        }

        $contentType = $response->headers->get('Content-Type');

        if ($contentType && ! str_contains(strtolower($contentType), 'text/html')) {
            return $response;
        }

        $content = $response->getContent();

        if (! is_string($content) || trim($content) === '') {
            return $response;
        }

        // Skip if already injected
        if (stripos($content, 'pagebuilder-styles') !== false) {
            return $response;
        }

        $cssTag = '<style id="pagebuilder-styles">'.$this->pageCss.'</style>';

        // Inject before </head> - use str_replace to avoid backreference issues with $ in CSS
        $headPos = stripos($content, '</head>');
        if ($headPos !== false) {
            $updatedContent = substr($content, 0, $headPos).$cssTag.substr($content, $headPos);
            $response->setContent($updatedContent);
        }

        return $response;
    }

    /**
     * Inject page-level JS (from page builder) before </body>.
     * In editor mode, JS is NOT wrapped in script tags to prevent execution.
     */
    protected function applyPageJs(Response $response): Response
    {
        if (in_array($this->pageJs, [null, '', '0'], true)) {
            return $response;
        }

        $contentType = $response->headers->get('Content-Type');

        if ($contentType && ! str_contains(strtolower($contentType), 'text/html')) {
            return $response;
        }

        $content = $response->getContent();

        if (! is_string($content) || trim($content) === '') {
            return $response;
        }

        // Skip if already injected
        if (stripos($content, 'pagebuilder-scripts') !== false) {
            return $response;
        }

        // Always use executable script tag - JS should run in both editor preview and frontend
        $jsTag = '<script id="pagebuilder-scripts">'.$this->pageJs.'</script>';

        // Inject before </body> - use substr to avoid backreference issues with $ in JS
        $bodyPos = stripos($content, '</body>');
        if ($bodyPos !== false) {
            $updatedContent = substr($content, 0, $bodyPos).$jsTag.substr($content, $bodyPos);
            $response->setContent($updatedContent);
        }

        return $response;
    }

    /**
     * Inject custom CSS link before </head>.
     * Uses a linked stylesheet with cache-busting version parameter.
     */
    protected function applyCustomCss(Response $response): Response
    {
        $contentType = $response->headers->get('Content-Type');

        if ($contentType && ! str_contains(strtolower($contentType), 'text/html')) {
            return $response;
        }

        $content = $response->getContent();

        if (! is_string($content) || trim($content) === '') {
            return $response;
        }

        // Skip if already injected
        if (stripos($content, 'theme-custom-css') !== false) {
            return $response;
        }

        $cssLink = $this->renderCustomCssLink();
        if ($cssLink === '') {
            return $response;
        }

        // Inject before </head> - use substr to avoid backreference issues
        $headPos = stripos($content, '</head>');
        if ($headPos !== false) {
            $updatedContent = substr($content, 0, $headPos).$cssLink.substr($content, $headPos);
            $response->setContent($updatedContent);
        }

        return $response;
    }

    /**
     * Inject custom JS from theme options before </body>.
     * Placed after theme JS so custom scripts can use theme functions.
     */
    protected function applyCustomJs(Response $response): Response
    {
        $contentType = $response->headers->get('Content-Type');

        if ($contentType && ! str_contains(strtolower($contentType), 'text/html')) {
            return $response;
        }

        $content = $response->getContent();

        if (! is_string($content) || trim($content) === '') {
            return $response;
        }

        // Skip if already injected
        if (stripos($content, 'theme-custom-js') !== false) {
            return $response;
        }

        $customJs = $this->renderCustomJs();
        if ($customJs === '') {
            return $response;
        }

        // Inject before </body> - use substr to avoid backreference issues
        $bodyPos = stripos($content, '</body>');
        if ($bodyPos !== false) {
            $updatedContent = substr($content, 0, $bodyPos).$customJs.substr($content, $bodyPos);
            $response->setContent($updatedContent);
        }

        return $response;
    }

    /**
     * Render custom CSS link tag with cache-busting version.
     */
    protected function renderCustomCssLink(): string
    {
        $activeTheme = Theme::getActiveTheme();

        if (! $activeTheme) {
            return '';
        }

        $themeDirectory = $activeTheme['directory'];
        $cacheKey = 'theme_css_version_'.$themeDirectory;

        // Use rememberForever to avoid cache miss on every request
        // Value is updated in clearThemeCache() when theme options change
        $version = Cache::rememberForever($cacheKey, fn (): string => '1');
        $cssUrl = url('/themes/'.$themeDirectory.'/_customizer.css').'?v='.$version;

        return "\n<link id=\"theme-custom-css\" href=\"{$cssUrl}\" rel=\"stylesheet\">\n";
    }

    /**
     * Render custom JS from theme options.
     */
    protected function renderCustomJs(): string
    {
        $customJs = theme_get_option('custom_js', '');

        if (empty($customJs)) {
            return '';
        }

        // Decode from base64
        $decoded = base64_decode($customJs, true);
        if ($decoded !== false) {
            $customJs = $decoded;
        }

        return "\n<script id=\"theme-custom-js\">\n".$customJs."\n</script>\n";
    }

    protected function applyAdminBar(Response $response): Response
    {
        $contentType = $response->headers->get('Content-Type');

        if ($contentType && ! str_contains(strtolower($contentType), 'text/html')) {
            return $response;
        }

        $content = $response->getContent();

        if (! is_string($content) || trim($content) === '') {
            return $response;
        }

        $response->setContent($this->injectAdminBar($content));

        return $response;
    }

    protected function injectAdminBar(string $content): string
    {
        $user = Auth::user();

        if (! $user) {
            return $content;
        }

        if (! $user->can('edit_pages') && ! $user->can('edit_posts')) {
            return $content;
        }

        // Don't inject admin bar in iframe/editor contexts.
        // Theme customizer uses ?customizer_preview=1.
        // Builder/editor uses ?editor_preview=1.
        // Only check query params - don't check Referer as it persists when opening in new tab
        if (
            request()->has('customizer_preview') ||
            request()->has('editor_preview') ||
            request()->routeIs('cms.builder.*')
        ) {
            return $content;
        }

        if ($this->containsAdminBar($content)) {
            return $content;
        }

        $adminBarMarkup = $this->renderAdminBar();

        if ($adminBarMarkup === '') {
            return $content;
        }

        // Inject before </body> - use substr to avoid backreference issues with $ in CSS/JS
        $bodyPos = stripos($content, '</body>');
        if ($bodyPos !== false) {
            return substr($content, 0, $bodyPos).$adminBarMarkup.substr($content, $bodyPos);
        }

        return $content.$adminBarMarkup;
    }

    protected function containsAdminBar(string $content): bool
    {
        return (bool) preg_match('/id\s*=\s*[\'\"]admin_bar[\'\"]/i', $content);
    }

    protected function renderAdminBar(): string
    {
        if ($this->adminBarMarkup !== null) {
            return $this->adminBarMarkup;
        }

        try {
            $component = resolve(AdminBarViewComponent::class);
            $this->adminBarMarkup = $component->render()->render();
        } catch (Throwable $throwable) {
            Log::warning('Admin bar auto-injection failed', [
                'error' => $throwable->getMessage(),
            ]);
            $this->adminBarMarkup = '';
        }

        return $this->adminBarMarkup;
    }

    /**
     * Apply HTML minification to reduce response size.
     * Only minifies HTML responses.
     */
    protected function applyHtmlMinification(Response $response): Response
    {
        $contentType = $response->headers->get('Content-Type');

        // Only minify HTML responses
        if ($contentType && ! str_contains(strtolower($contentType), 'text/html')) {
            return $response;
        }

        $content = $response->getContent();

        if (! is_string($content) || trim($content) === '') {
            if (config('app.debug')) {
                $response->headers->set('X-HTML-Minified', 'no');
            }

            return $response;
        }

        $minifiedApplied = false;

        if (HtmlMinifier::isEnabled()) {
            try {
                $minified = HtmlMinifier::minify($content);
                $response->setContent($minified);
                $minifiedApplied = true;
            } catch (Throwable $e) {
                // Log error but don't fail the request - return original content
                Log::warning('HTML minification failed', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (config('app.debug')) {
            $response->headers->set('X-HTML-Minified', $minifiedApplied ? 'yes' : 'no');
        }

        return $response;
    }

    /**
     * Resolve the asset path for a theme, cascading through parent themes
     *
     * Child theme assets take precedence over parent theme assets.
     * If asset not found in child, look in parent, then grandparent, etc.
     *
     * @param  string  $theme  The theme directory name
     * @param  string  $asset  The asset path relative to theme
     * @return string|null The full path to the asset, or null if not found
     */
    protected function resolveThemeAssetPath(string $theme, string $asset): ?string
    {
        // Get theme hierarchy (current theme + all parents)
        $themeHierarchy = Theme::getThemeHierarchy($theme);

        foreach ($themeHierarchy as $themeDir) {
            $themePath = Theme::getThemesPath().'/'.$themeDir;
            $assetPath = $themePath.'/'.$asset;

            if (file_exists($assetPath)) {
                return $assetPath;
            }
        }

        return null;
    }
}
