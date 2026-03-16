<?php

namespace Modules\CMS\Repositories;

use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Modules\CMS\Models\Theme;
use Modules\CMS\Services\ThemeOptionsCacheService;

class ThemeRepository
{
    /**
     * Get the currently active theme
     */
    public function getActiveTheme(): ?array
    {
        return Theme::getActiveTheme();
    }

    /**
     * Get all available themes
     */
    public function getAllThemes(): Collection
    {
        return collect(Theme::getAllThemes());
    }

    /**
     * Get theme information by directory name
     */
    public function getThemeInfo(string $directory): ?array
    {
        return Theme::getThemeInfo($directory);
    }

    /**
     * Activate a theme by directory name
     */
    public function activateTheme(string $directory): bool
    {
        // Validate theme exists
        if (! $this->themeExists($directory)) {
            return false;
        }

        // Validate theme before activation
        if (! $this->isThemeValid($directory)) {
            return false;
        }

        return Theme::activateTheme($directory);
    }

    /**
     * Check if a theme exists
     */
    public function themeExists(string $directory): bool
    {
        $themePath = Theme::getThemesPath().'/'.$directory;

        return File::isDirectory($themePath) && File::exists($themePath.'/manifest.json');
    }

    /**
     * Check if a theme is valid for activation
     */
    public function isThemeValid(string $directory): bool
    {
        $themeInfo = $this->getThemeInfo($directory);

        if (! $themeInfo) {
            return false;
        }

        // Check required files exist
        $themePath = Theme::getThemesPath().'/'.$directory;
        $requiredFiles = ['manifest.json'];

        foreach ($requiredFiles as $file) {
            if (! File::exists($themePath.'/'.$file)) {
                return false;
            }
        }

        // Validate manifest structure
        $manifest = json_decode(File::get($themePath.'/manifest.json'), true);

        return ! (! $manifest || ! isset($manifest['name']) || ! isset($manifest['version']));
    }

    /**
     * Get template hierarchy for a given context
     */
    public function getTemplateHierarchy(string $context, array $params = []): array
    {
        return Theme::getTemplateHierarchy($context, $params);
    }

    /**
     * Get template file for a given context
     */
    public function getTemplate(string $context, array $params = []): ?string
    {
        return Theme::getTemplate($context, $params);
    }

    /**
     * Get theme templates list
     *
     * Uses ThemeOptionsCacheService for two-tier caching.
     */
    public function getThemeTemplates(string $directory): array
    {
        return resolve(ThemeOptionsCacheService::class)->getThemeTemplates($directory, function () use ($directory): array {
            $themePath = Theme::getThemesPath().'/'.$directory;

            if (! File::isDirectory($themePath)) {
                return [];
            }

            $templates = [];
            $files = File::glob($themePath.'/templates/*.tpl');

            foreach ($files as $file) {
                $filename = basename($file);
                $templates[] = [
                    'file' => $filename,
                    'name' => str_replace('.blade.php', '', $filename),
                    'path' => $file,
                    'type' => $this->getTemplateType($filename),
                    'size' => File::size($file),
                    'modified' => File::lastModified($file),
                ];
            }

            return $templates;
        });
    }

    /**
     * Create a new theme from data
     */
    public function createTheme(array $themeData): bool
    {
        // @phpstan-ignore-next-line staticMethod.notFound
        return Theme::createTheme($themeData);
    }

    /**
     * Delete a theme by directory name
     */
    public function deleteTheme(string $directory): bool
    {
        // Prevent deletion of active theme
        $activeTheme = $this->getActiveTheme();
        if ($activeTheme && $activeTheme['directory'] === $directory) {
            return false;
        }

        $themePath = Theme::getThemesPath().'/'.$directory;

        if (! File::isDirectory($themePath)) {
            return false;
        }

        try {
            File::deleteDirectory($themePath);

            // Clear cache using ThemeOptionsCacheService (two-tier invalidation)
            resolve(ThemeOptionsCacheService::class)->invalidateTheme($directory, 'theme deleted');
            resolve(ThemeOptionsCacheService::class)->invalidateTemplates($directory);

            return true;
        } catch (Exception $exception) {
            Log::error('Failed to delete theme: '.$directory, ['error' => $exception->getMessage()]);

            return false;
        }
    }

    /**
     * Get themes filtered by criteria
     */
    public function getThemesFiltered(array $filters = []): Collection
    {
        $themes = $this->getAllThemes();

        // Filter by tags
        if (isset($filters['tags']) && ! empty($filters['tags'])) {
            $themes = $themes->filter(function (array $theme) use ($filters): bool {
                $themeTags = $theme['tags'] ?? [];

                return array_intersect($filters['tags'], $themeTags) !== [];
            });
        }

        // Filter by author
        if (isset($filters['author']) && ! empty($filters['author'])) {
            $themes = $themes->filter(function (array $theme) use ($filters): bool {
                $author = $theme['author']['name'] ?? '';

                return stripos($author, (string) $filters['author']) !== false;
            });
        }

        // Filter by support features
        if (isset($filters['supports']) && ! empty($filters['supports'])) {
            $themes = $themes->filter(function (array $theme) use ($filters): bool {
                $supports = $theme['supports'] ?? [];
                foreach ($filters['supports'] as $feature) {
                    if (! isset($supports[$feature]) || ! $supports[$feature]) {
                        return false;
                    }
                }

                return true;
            });
        }

        // Sort by criteria
        if (isset($filters['sort_by'])) {
            return match ($filters['sort_by']) {
                'name' => $themes->sortBy('name'),
                'author' => $themes->sortBy('author.name'),
                'version' => $themes->sortBy('version'),
                default => $themes->sortByDesc('updated_at'),
            };
        }

        return $themes;
    }

    /**
     * Get theme statistics
     */
    public function getThemeStats(string $directory): array
    {
        $this->getThemeInfo($directory);
        $templates = $this->getThemeTemplates($directory);
        $themePath = Theme::getThemesPath().'/'.$directory;

        $stats = [
            'templates_count' => count($templates),
            'total_size' => 0,
            'has_config' => File::exists($themePath.'/config/config.json'),
            'has_styles' => File::exists($themePath.'/style.css'),
            'has_screenshot' => false,
            'last_modified' => null,
        ];

        // Calculate total size and find last modified
        $lastModified = 0;
        if (File::isDirectory($themePath)) {
            $files = File::allFiles($themePath);
            foreach ($files as $file) {
                $stats['total_size'] += $file->getSize();
                $modified = $file->getMTime();
                if ($modified > $lastModified) {
                    $lastModified = $modified;
                }
            }
        }

        $stats['last_modified'] = $lastModified > 0 ? date('Y-m-d H:i:s', $lastModified) : null;
        $stats['total_size_formatted'] = $this->formatBytes($stats['total_size']);

        // Check for screenshot
        $screenshotExtensions = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
        foreach ($screenshotExtensions as $ext) {
            if (File::exists($themePath.'/screenshot.'.$ext)) {
                $stats['has_screenshot'] = true;
                break;
            }
        }

        return $stats;
    }

    /**
     * Get template type from filename
     */
    private function getTemplateType(string $filename): string
    {
        $name = str_replace('.blade.php', '', $filename);

        $types = [
            'index' => 'home',
            'home' => 'home',
            'front-page' => 'home',
            'page' => 'page',
            'post' => 'post',
            'single' => 'post', // Backward compatibility
            'archive' => 'archive',
            'category' => 'archive',
            'tag' => 'archive',
            'search' => 'search',
            '404' => 'error',
            'error' => 'error',
        ];

        return $types[$name] ?? 'custom';
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision).' '.$units[$i];
    }
}
