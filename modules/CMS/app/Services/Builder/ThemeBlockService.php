<?php

namespace Modules\CMS\Services\Builder;

use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\CMS\Models\Theme;
use Modules\CMS\Services\TwigService;

class ThemeBlockService
{
    public function __construct(
        protected TwigService $twigService
    ) {}

    /**
     * Get manifest of all blocks OR sections in a theme
     *
     * JSON schema files are OPTIONAL. If present, they can override:
     * - name: Custom display name (otherwise derived from filename)
     * - description: Block description
     * - preview: Preview image filename
     * - props: Property schema for future builder UI
     *
     * @param  string  $themeName  Theme directory name
     * @param  string  $type  'blocks' or 'sections'
     */
    public function getManifest(string $themeName, string $type = 'blocks'): array
    {
        $cacheKey = sprintf('theme_%s_manifest:%s', $type, $themeName);

        return Cache::rememberForever($cacheKey, function () use ($themeName, $type): array {
            $basePath = Theme::getThemesPath().sprintf('/%s/%s', $themeName, $type);
            $items = [];

            if (! is_dir($basePath)) {
                return ['theme' => $themeName, 'type' => $type, 'items' => []];
            }

            // Scan category folders
            foreach (glob($basePath.'/*', GLOB_ONLYDIR) as $categoryDir) {
                $category = basename($categoryDir);

                // Scan for .twig files (JSON is now optional)
                foreach (glob($categoryDir.'/*.twig') as $twigFile) {
                    $slug = pathinfo($twigFile, PATHINFO_FILENAME);

                    try {
                        // Auto-derive name from slug
                        // Handle all-uppercase parts (e.g., 'CTA-banner' -> 'CTA Banner')
                        $autoName = collect(explode('-', $slug))
                            ->map(fn ($part): string => $part === strtoupper($part) && strlen($part) > 1
                                ? $part  // Keep all-uppercase parts like "CTA", "FAQ"
                                : ucfirst(strtolower($part)))  // Normal title case
                            ->join(' ');

                        // Check for optional companion JSON for metadata overrides
                        $jsonPath = sprintf('%s/%s.json', $categoryDir, $slug);
                        $schema = [];
                        if (file_exists($jsonPath)) {
                            $jsonContent = file_get_contents($jsonPath);
                            $schema = json_decode($jsonContent, true) ?: [];
                            if (json_last_error() !== JSON_ERROR_NONE) {
                                Log::warning('Invalid JSON in theme block schema: '.$jsonPath);
                                $schema = [];
                            }
                        }

                        // Check for optional preview image (.webp with same basename)
                        $previewFile = null;
                        $previewPath = null;
                        foreach (['.webp', '.png', '.jpg'] as $ext) {
                            if (file_exists(sprintf('%s/%s%s', $categoryDir, $slug, $ext))) {
                                $previewFile = $slug.$ext;
                                $previewPath = sprintf('/themes/%s/%s/%s/%s', $themeName, $type, $category, $previewFile);
                                break;
                            }
                        }

                        // Note: HTML is NOT pre-rendered here for performance.
                        // The builder fetches HTML on-demand via the render endpoint.

                        $items[] = [
                            'slug' => sprintf('%s/%s', $category, $slug),
                            'id' => sprintf('theme-%s-%s', $category, $slug),
                            'category' => $category,
                            'name' => $schema['name'] ?? $autoName,
                            'description' => $schema['description'] ?? '',
                            'preview' => $schema['preview'] ?? $previewFile,
                            'image' => isset($schema['preview'])
                                ? sprintf('/themes/%s/%s/%s/%s', $themeName, $type, $category, $schema['preview'])
                                : $previewPath,
                            'props' => $schema['props'] ?? [],
                            'source' => 'theme',
                        ];
                    } catch (Exception $e) {
                        Log::error('Error loading theme block: '.$twigFile, ['error' => $e->getMessage()]);
                    }
                }
            }

            return [
                'theme' => $themeName,
                'type' => $type,
                'items' => $items,
            ];
        });
    }

    /**
     * Render a block/section for builder palette
     *
     * Twig templates should use |default() filters for all variables.
     * Optional JSON can provide demo data via 'demo' key.
     *
     * @param  string  $themeName  Theme directory name
     * @param  string  $type  'blocks' or 'sections'
     * @param  string  $slug  Category/slug (e.g., 'hero/hero-gradient')
     */
    public function renderForBuilder(string $themeName, string $type, string $slug): array
    {
        $cacheKey = sprintf('theme_%s_render:%s:%s', $type, $themeName, $slug);

        $html = Cache::rememberForever($cacheKey, function () use ($themeName, $type, $slug): string {
            $this->twigService->setTheme($themeName);

            $twigPath = Theme::getThemesPath().sprintf('/%s/%s/%s.twig', $themeName, $type, $slug);

            if (! file_exists($twigPath)) {
                return sprintf('<!-- Twig template not found: %s -->', $twigPath);
            }

            // Check for optional JSON with demo data
            $jsonPath = Theme::getThemesPath().sprintf('/%s/%s/%s.json', $themeName, $type, $slug);
            $demoData = ['is_builder' => true];

            if (file_exists($jsonPath)) {
                $schema = json_decode(file_get_contents($jsonPath), true) ?: [];

                // Extract defaults from props schema
                $defaults = $this->extractDefaults($schema['props'] ?? []);

                // Merge: defaults < demo data < is_builder flag
                $demoData = array_merge($defaults, $schema['demo'] ?? [], ['is_builder' => true]);
            }

            return $this->twigService->render(sprintf('%s/%s.twig', $type, $slug), $demoData);
        });

        return [
            'html' => $html,
            'cached' => true,
        ];
    }

    /**
     * Clear all caches for a theme (call when theme files change)
     */
    public function clearCache(string $themeName): void
    {
        Cache::forget('theme_blocks_manifest:'.$themeName);
        Cache::forget('theme_sections_manifest:'.$themeName);

        // Note: Individual render caches require full cache:clear or Redis prefix clear
    }

    /**
     * Extract default values from props schema
     */
    protected function extractDefaults(array $props): array
    {
        $defaults = [];
        foreach ($props as $key => $config) {
            $defaults[$key] = $config['default'] ?? '';
        }

        return $defaults;
    }
}
