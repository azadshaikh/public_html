<?php

namespace Modules\CMS\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use Modules\CMS\Models\Theme;
use Modules\CMS\Twig\Extensions\ComponentsExtension;
use Modules\CMS\Twig\Extensions\ThemeFiltersExtension;
use Modules\CMS\Twig\Extensions\ThemeFunctionsExtension;
use Modules\CMS\Twig\Extensions\ThemeUtilitiesExtension;
use Modules\CMS\Twig\Sandbox\LooseSecurityPolicy;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Twig\Environment;
use Twig\Extension\SandboxExtension;
use Twig\Loader\FilesystemLoader;

class TwigService
{
    protected Environment $twig;

    protected FilesystemLoader $loader;

    protected ?string $currentThemeDirectory = null;

    public function __construct()
    {
        $this->loader = new FilesystemLoader;
        $this->twig = new Environment($this->loader, $this->getEnvironmentOptions());

        $this->registerSecurityPolicy();
        $this->registerExtensions();
    }

    /**
     * Set the theme directory for template resolution
     * Supports child themes by adding parent theme as fallback template directory
     */
    public function setTheme(string $themeDirectory): void
    {
        $this->currentThemeDirectory = $themeDirectory;

        $themePath = Theme::getThemesPath().'/'.$themeDirectory;

        // Reset loader paths
        $this->loader->setPaths([]);

        // Set primary template directory to current theme
        $this->loader->addPath($themePath);

        // If this is a child theme, add parent theme(s) as fallback directories
        // This enables template inheritance - child templates override parent templates
        $themeHierarchy = Theme::getThemeHierarchy($themeDirectory);

        // Skip the first one (current theme) and add parents as fallback
        foreach (array_slice($themeHierarchy, 1) as $parentTheme) {
            $parentPath = Theme::getThemesPath().'/'.$parentTheme;
            if (is_dir($parentPath)) {
                $this->loader->addPath($parentPath);
            }
        }
    }

    /**
     * Render a template with context
     */
    public function render(string $template, array $context = []): string
    {
        try {
            // Check if template has extension, if not add .twig
            if (! str_contains($template, '.')) {
                $template .= '.twig';
            }

            return $this->twig->render($template, $context);
        } catch (Exception $exception) {
            Log::error('Twig template rendering failed', [
                'template' => $template,
                'theme' => $this->currentThemeDirectory,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]);

            // Rethrow so it's caught by error handlers
            throw $exception;
        }
    }

    /**
     * Check if a template exists
     */
    public function templateExists(string $template): bool
    {
        try {
            // Add .twig extension if not present
            if (! str_contains($template, '.')) {
                $template .= '.twig';
            }

            return $this->loader->exists($template);
        } catch (Exception) {
            return false;
        }
    }

    /**
     * Add a global variable
     */
    public function addGlobal(string $name, mixed $value): void
    {
        $this->twig->addGlobal($name, $value);
    }

    /**
     * Clear compiled templates cache
     */
    public function clearCache(): void
    {
        $cacheDir = storage_path('framework/twig/cache');

        if (! is_dir($cacheDir)) {
            return;
        }

        try {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($cacheDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($files as $file) {
                $realPath = $file->getRealPath();

                // Skip if we can't get real path
                if ($realPath === false) {
                    continue;
                }

                try {
                    if ($file->isDir()) {
                        rmdir($realPath);
                    } else {
                        unlink($realPath);
                    }
                } catch (Exception $e) {
                    Log::warning('Failed to delete Twig cache file', [
                        'file' => $realPath,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } catch (Exception $exception) {
            Log::error('Failed to clear Twig cache', [
                'cache_dir' => $cacheDir,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Get the underlying Twig instance (for advanced usage)
     */
    public function getTwig(): Environment
    {
        return $this->twig;
    }

    /**
     * Get current theme directory
     */
    public function getCurrentThemeDirectory(): ?string
    {
        return $this->currentThemeDirectory;
    }

    /**
     * Get Twig environment configuration options
     */
    protected function getEnvironmentOptions(): array
    {
        // Enable auto_reload in debug mode OR when in theme editor context
        // This ensures template changes are picked up immediately during editing
        $autoReload = config('app.debug', false) || $this->isThemeEditorContext();

        return [
            'cache' => storage_path('framework/twig/cache'),
            'auto_reload' => $autoReload,
            'strict_variables' => false,
            'debug' => config('app.debug', false),
            'autoescape' => 'html',
        ];
    }

    /**
     * Check if current request is from theme editor
     */
    protected function isThemeEditorContext(): bool
    {
        if (! app()->runningInConsole()) {
            $route = request()->route();
            if ($route) {
                $routeName = $route->getName() ?? '';

                return str_starts_with($routeName, 'cms.appearance.editor.');
            }
        }

        return false;
    }

    /**
     * Register security policy using Twig Sandbox
     */
    protected function registerSecurityPolicy(): void
    {
        // Define allowed tags
        $allowedTags = [
            'if', 'else', 'elseif', 'for', 'set', 'block', 'extends',
            'include', 'embed', 'use', 'macro', 'import', 'from',
            'verbatim', 'spaceless', 'apply', 'autoescape', 'do',
        ];

        // Define allowed filters (built-in + custom)
        $allowedFilters = [
            // Built-in Twig filters
            'abs', 'batch', 'capitalize', 'column', 'convert_encoding',
            'country_name', 'currency_name', 'currency_symbol', 'data_uri',
            'date', 'date_modify', 'default', 'escape', 'e', 'filter',
            'first', 'format', 'format_currency', 'format_date', 'format_datetime',
            'format_number', 'format_time', 'html_to_markdown', 'inky_to_html',
            'inline_css', 'join', 'json_encode', 'keys', 'language_name',
            'last', 'length', 'locale_name', 'lower', 'map', 'markdown_to_html',
            'merge', 'nl2br', 'number_format', 'raw', 'reduce', 'replace',
            'reverse', 'round', 'slice', 'slug', 'sort', 'spaceless', 'split',
            'striptags', 'timezone_name', 'title', 'trim', 'upper', 'url_encode',
            // Custom filters
            'str_replace', 'limit', 'excerpt', 'snake', 'camel', 'kebab', 'studly',
            'starts_with', 'ends_with', 'contains', 'extension', 'basename', 'dirname',
            'file_size', 'money', 'currency', 'pluralize', 'json_decode', 'md5',
            'base64_encode', 'time_ago', 'count_words', 'truncate',
            // ThemeUtilitiesExtension filters
            'esc_html', 'esc_attr', 'esc_url', 'esc_js', 'reading_time', 'word_count', 'truncate_words',
        ];

        // Define allowed functions (custom theme functions)
        $allowedFunctions = [
            'theme_option', 'theme_uri', 'theme_asset', 'theme_url',
            'url', 'route', 'csrf_token', 'csrf_field', 'locale', 'asset',
            'setting', 'is_auth', 'auth_user', 'session', 'session_has', 'old',
            'trans', 'format_date', 'breadcrumb', 'current_year',
            'get_popular_posts', 'get_categories', 'get_tags',
            'render_widget_area', 'has_widgets', 'has_menu',
            'responsive_image', 'form_response', 'seo_meta', 'menu', 'widget',
            'admin_bar', // integrations removed - auto-injected by SeoMetaComponent (head) and ThemeService (footer)
            // ThemeUtilitiesExtension - Context Detection
            'is_single', 'is_page', 'is_archive', 'is_author', 'is_search', 'is_home', 'is_404',
            // ThemeUtilitiesExtension - Security Utilities
            'esc_html', 'esc_attr', 'esc_url', 'esc_js', 'nonce_field', 'verify_nonce',
            'sanitize_html', 'is_admin', 'can',
            // ThemeUtilitiesExtension - Development Utilities
            'placeholder_image', 'svg_icon', 'time_ago', 'reading_time', 'word_count',
            'truncate_words', 'asset_version', 'json_encode',
            // ThemeUtilitiesExtension - Image Utilities
            'image_url', 'srcset', 'lazy_image',
            // ThemeUtilitiesExtension - Pagination
            'paginate_links',
            // Twig built-in functions
            'range', 'cycle', 'constant', 'random', 'date', 'include',
            'source', 'max', 'min', 'dump', 'block', 'parent', 'attribute',
        ];

        // Define allowed methods on objects (empty = allow all)
        $allowedMethods = [];

        // Define allowed properties on objects (empty = allow all)
        $allowedProperties = [];

        $policy = new LooseSecurityPolicy($allowedTags, $allowedFilters, $allowedMethods, $allowedProperties, $allowedFunctions);
        $sandbox = new SandboxExtension($policy, true); // true = sandboxed globally

        $this->twig->addExtension($sandbox);
    }

    /**
     * Register custom extensions
     */
    protected function registerExtensions(): void
    {
        $this->twig->addExtension(new ThemeFunctionsExtension);
        $this->twig->addExtension(new ThemeFiltersExtension);
        $this->twig->addExtension(new ComponentsExtension);
        $this->twig->addExtension(new ThemeUtilitiesExtension);
    }
}
