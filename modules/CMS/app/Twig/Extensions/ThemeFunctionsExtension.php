<?php

namespace Modules\CMS\Twig\Extensions;

use DateTime;
use DateTimeInterface;
use Modules\CMS\Twig\Security\SafeUserProxy;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Theme-related Twig functions
 * These wrap Laravel theme helper functions for use in Twig templates
 */
class ThemeFunctionsExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            // Theme functions
            new TwigFunction('theme_option', $this->themeOption(...)),
            new TwigFunction('theme_uri', $this->themeUri(...)),
            new TwigFunction('theme_asset', $this->themeAsset(...)),
            new TwigFunction('theme_url', $this->themeUrl(...)),

            // Laravel helpers
            new TwigFunction('url', $this->url(...)),
            new TwigFunction('route', $this->route(...)),
            new TwigFunction('csrf_token', $this->csrfToken(...)),
            new TwigFunction('csrf_field', $this->csrfField(...), ['is_safe' => ['html']]),
            new TwigFunction('locale', $this->locale(...)),
            new TwigFunction('asset', $this->asset(...)),
            new TwigFunction('setting', $this->setting(...)),

            // Auth functions
            new TwigFunction('is_auth', $this->isAuth(...)),
            new TwigFunction('auth_user', $this->authUser(...)),

            // Session functions
            new TwigFunction('session', $this->session(...)),
            new TwigFunction('session_has', $this->sessionHas(...)),
            new TwigFunction('old', $this->old(...)),

            // Translation
            new TwigFunction('trans', $this->trans(...)),

            // Date/Time
            new TwigFunction('format_date', $this->formatDate(...)),
            new TwigFunction('current_year', $this->currentYear(...)),

            // Navigation
            new TwigFunction('breadcrumb', $this->breadcrumb(...), ['is_safe' => ['html']]),
            new TwigFunction('has_menu', $this->hasMenu(...)),

            // Content queries
            new TwigFunction('get_popular_posts', $this->getPopularPosts(...)),
            new TwigFunction('get_categories', $this->getCategories(...)),
            new TwigFunction('get_tags', $this->getTags(...)),

            // Widgets
            new TwigFunction('render_widget_area', $this->renderWidgetArea(...), ['is_safe' => ['html']]),
            new TwigFunction('has_widgets', $this->hasWidgets(...)),
        ];
    }

    /**
     * Get theme option value
     * Usage: {{ theme_option('site_title', 'Default Title') }}
     */
    public function themeOption(string $key, mixed $default = null): mixed
    {
        if ($key === '' || $key === '0') {
            return '';
        }

        return theme_get_option($key, $default);
    }

    /**
     * Get theme directory URI
     * Usage: {{ theme_uri() }}
     */
    public function themeUri(): string
    {
        return theme_get_template_directory_uri();
    }

    /**
     * Get theme asset URL
     * Usage: {{ theme_asset('css/style.css') }}
     */
    public function themeAsset(string $path = ''): string
    {
        if ($path === '' || $path === '0') {
            return '';
        }

        return theme_asset($path);
    }

    /**
     * Get theme URL
     * Usage: {{ theme_url('about') }}
     */
    public function themeUrl(string $path = ''): string
    {
        return url($path);
    }

    /**
     * Generate Laravel URL
     * Usage: {{ url('/contact') }}
     */
    public function url(string $path = '/'): string
    {
        return url($path);
    }

    /**
     * Generate named route URL
     * Usage: {{ route('home', {'id': 1}) }}
     */
    public function route(string $name, array $parameters = []): string
    {
        if ($name === '' || $name === '0') {
            return '';
        }

        return route($name, $parameters);
    }

    /**
     * Get CSRF token
     * Usage: {{ csrf_token() }}
     */
    public function csrfToken(): string
    {
        return csrf_token();
    }

    /**
     * Get CSRF field HTML
     * Usage: {{ csrf_field() }}
     */
    public function csrfField(): string
    {
        return csrf_field();
    }

    /**
     * Get current locale
     * Usage: {{ locale() }}
     */
    public function locale(): string
    {
        return str_replace('_', '-', app()->getLocale());
    }

    /**
     * Get asset URL
     * Usage: {{ asset('images/logo.png') }}
     */
    public function asset(string $path = ''): string
    {
        if ($path === '' || $path === '0') {
            return '';
        }

        return asset($path);
    }

    /**
     * Get setting value
     * Usage: {{ setting('site_name', 'My Site') }}
     */
    public function setting(string $key, mixed $default = null): mixed
    {
        if ($key === '' || $key === '0') {
            return '';
        }

        return setting($key, $default);
    }

    /**
     * Check if user is authenticated
     * Usage: {% if is_auth() %}
     */
    public function isAuth(): bool
    {
        return auth()->check();
    }

    /**
     * Get authenticated user (wrapped in SafeUserProxy for security)
     * Usage: {{ auth_user().name }}
     * Note: Returns a SafeUserProxy that only exposes safe properties/methods
     *       Returns null when user is not authenticated
     */
    public function authUser(): ?SafeUserProxy
    {
        $user = auth()->user();

        if ($user === null) {
            return null;
        }

        return new SafeUserProxy($user);
    }

    /**
     * Get session value
     * Usage: {{ session('success') }}
     */
    public function session(string $key, string $default = ''): mixed
    {
        if ($key === '' || $key === '0') {
            return $default;
        }

        return session($key, $default);
    }

    /**
     * Check if session has a key
     * Usage: {% if session_has('success') %}
     */
    public function sessionHas(string $key): bool
    {
        if ($key === '' || $key === '0') {
            return false;
        }

        return session()->has($key);
    }

    /**
     * Get old input value
     * Usage: {{ old('email', '') }}
     * Note: Returns mixed since Laravel's old() can return arrays for array inputs
     */
    public function old(string $key, mixed $default = ''): mixed
    {
        if ($key === '' || $key === '0') {
            return $default;
        }

        $value = old($key, $default);

        // Handle array values gracefully - return as-is or convert to string
        if (is_array($value)) {
            return $value;
        }

        return $value ?? $default;
    }

    /**
     * Get translation
     * Usage: {{ trans('messages.welcome') }}
     */
    public function trans(string $key, array $replace = []): string
    {
        if ($key === '' || $key === '0') {
            return '';
        }

        return __($key, $replace);
    }

    /**
     * Format date
     * Usage: {{ format_date(post.created_at, 'Y-m-d') }}
     */
    public function formatDate(mixed $date, string $format = 'Y-m-d H:i:s'): string
    {
        if (! $date) {
            return '';
        }

        if (is_string($date)) {
            $date = new DateTime($date);
        }

        if ($date instanceof DateTimeInterface) {
            return $date->format($format);
        }

        return '';
    }

    /**
     * Get current year
     * Usage: {{ current_year() }}
     */
    public function currentYear(): string
    {
        return date('Y');
    }

    /**
     * Render breadcrumb navigation
     * Usage: {{ breadcrumb({'class': 'breadcrumb mb-0'}) }}
     */
    public function breadcrumb(array $options = []): string
    {
        return menu_breadcrumb($options);
    }

    /**
     * Check if a menu location has a menu
     * Usage: {% if has_menu('primary') %}
     */
    public function hasMenu(string $location): bool
    {
        if ($location === '' || $location === '0') {
            return false;
        }

        return has_menu($location);
    }

    /**
     * Get popular posts
     * Usage: {% set posts = get_popular_posts('post', 3) %}
     */
    public function getPopularPosts(string $type = 'post', int $limit = 3): array
    {
        return get_popular_posts($type, $limit)->all();
    }

    /**
     * Get categories
     * Usage: {% set categories = get_categories('category', 6) %}
     */
    public function getCategories(string $type = 'category', int $limit = 6): array
    {
        return get_categories($type, $limit)->all();
    }

    /**
     * Get tags
     * Usage: {% set tags = get_tags('tag', 6) %}
     */
    public function getTags(string $type = 'tag', int $limit = 6): array
    {
        return get_tags($type, $limit)->all();
    }

    /**
     * Render widget area
     * Usage: {{ render_widget_area('sidebar-main') }}
     */
    public function renderWidgetArea(string $areaId): string
    {
        if ($areaId === '' || $areaId === '0') {
            return '';
        }

        return render_widget_area($areaId);
    }

    /**
     * Check if widget area has widgets
     * Usage: {% if has_widgets('sidebar-main') %}
     */
    public function hasWidgets(string $areaId): bool
    {
        if ($areaId === '' || $areaId === '0') {
            return false;
        }

        return has_widgets($areaId);
    }
}
