<?php

namespace Modules\CMS\Services;

use App\Support\Cache\AbstractCacheService;
use Illuminate\Support\Facades\Log;
use Modules\CMS\Models\Theme;

/**
 * Cache service for theme options.
 *
 * Caches theme customizer options for fast frontend rendering.
 * Automatically invalidated when theme options are updated.
 *
 * Uses two-tier caching (memory + persistent) for all cache keys.
 */
class ThemeOptionsCacheService extends AbstractCacheService
{
    /**
     * Cache key prefix for theme options
     */
    public const PREFIX = 'cms_theme_options_';

    /**
     * Cache key prefix for theme templates
     */
    public const TEMPLATES_PREFIX = 'app_theme_templates_v1_';

    /**
     * Currently active theme directory
     */
    protected ?string $activeThemeDirectory = null;

    /**
     * Set the theme directory (for working with non-active themes)
     */
    public function setThemeDirectory(string $directory): self
    {
        $this->activeThemeDirectory = $directory;
        $this->clearMemoryCache();

        return $this;
    }

    /**
     * Get a specific theme option.
     */
    public function getOption(string $key, mixed $default = null): mixed
    {
        $options = $this->getCached();

        return $options[$key] ?? $default;
    }

    /**
     * Get all theme options.
     */
    public function getOptions(): array
    {
        return $this->getCached();
    }

    /**
     * Get cached theme templates.
     *
     * Uses two-tier caching for template file lists.
     */
    public function getThemeTemplates(string $directory, callable $loader): array
    {
        $cacheKey = self::TEMPLATES_PREFIX.$directory;

        return $this->remember($cacheKey, $loader);
    }

    /**
     * Invalidate templates cache for a theme.
     */
    public function invalidateTemplates(string $directory): void
    {
        $this->forget(self::TEMPLATES_PREFIX.$directory);
    }

    /**
     * Invalidate cache for a specific theme.
     */
    public function invalidateTheme(string $directory, ?string $reason = null): void
    {
        $this->forget(self::PREFIX.$directory);

        if ($this->shouldLog()) {
            Log::debug('Theme options cache invalidated', [
                'theme' => $directory,
                'reason' => $reason ?? 'manual invalidation',
            ]);
        }
    }

    /**
     * Invalidate cache for the active theme.
     */
    public function invalidate(?string $reason = null): void
    {
        $this->invalidateTheme($this->getActiveThemeDirectory(), $reason);
    }

    /**
     * Invalidate all theme caches.
     */
    public function invalidateAll(?string $reason = null): void
    {
        // Get all theme directories
        $themes = Theme::getAllThemes();

        foreach ($themes as $theme) {
            if (isset($theme['directory'])) {
                $this->forget(self::PREFIX.$theme['directory']);
            }
        }

        if ($this->shouldLog()) {
            Log::debug('All theme options caches invalidated', [
                'reason' => $reason ?? 'manual invalidation',
            ]);
        }
    }

    protected function getCacheKey(): string
    {
        return self::PREFIX.$this->getActiveThemeDirectory();
    }

    protected function getCacheTtl(): ?int
    {
        return null; // Cache forever - invalidated when theme options change
    }

    /**
     * Get the active theme directory
     */
    protected function getActiveThemeDirectory(): string
    {
        if ($this->activeThemeDirectory === null) {
            $activeTheme = Theme::getActiveTheme();
            $this->activeThemeDirectory = $activeTheme['directory'] ?? 'default';
        }

        return $this->activeThemeDirectory;
    }

    /**
     * Load theme options from source (JSON file)
     */
    protected function loadFromSource(): mixed
    {
        $directory = $this->getActiveThemeDirectory();
        $optionsFile = Theme::getThemesPath().'/'.$directory.'/config/options.json';

        if (! file_exists($optionsFile)) {
            return [];
        }

        $content = file_get_contents($optionsFile);
        $options = json_decode($content, true) ?: [];

        // Sanitize all options before caching
        $sanitizedOptions = [];
        foreach ($options as $optionKey => $optionValue) {
            $sanitizedOptions[$optionKey] = ContentSanitizer::sanitizeThemeOption($optionKey, $optionValue);
        }

        return $sanitizedOptions;
    }
}
