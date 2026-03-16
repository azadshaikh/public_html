<?php

namespace Modules\CMS\Services;

use App\Models\Settings;
use App\Models\User;
use App\Services\XmlGeneratorService;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\File;
use Modules\CMS\Models\CmsPost;

class SitemapService
{
    protected string $basePath;

    public function __construct(protected ?XmlGeneratorService $xmlGenerator = new XmlGeneratorService)
    {
        $this->basePath = public_path(config('cms.sitemap.paths.base', 'sitemaps'));
    }

    /**
     * Check if sitemap is enabled globally.
     */
    public function isEnabled(): bool
    {
        return (bool) setting('seo.sitemap.enabled', false);
    }

    /**
     * Check if a specific sitemap type is enabled.
     */
    public function isTypeEnabled(string $type): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $config = $this->getTypeConfig($type);
        if (! $config) {
            return false;
        }

        return (bool) setting($config['enabled_key'], true);
    }

    /**
     * Get all sitemap types from config.
     */
    public function getTypes(): array
    {
        return config('cms.sitemap.types', []);
    }

    /**
     * Get all enabled sitemap types.
     */
    public function getEnabledTypes(): array
    {
        return array_filter(
            array_keys($this->getTypes()),
            $this->isTypeEnabled(...)
        );
    }

    /**
     * Get configuration for a specific type.
     */
    public function getTypeConfig(string $type): ?array
    {
        return config('cms.sitemap.types.'.$type);
    }

    /**
     * Generate sitemap for a specific type.
     */
    public function generate(string $type): array
    {
        $config = $this->getTypeConfig($type);

        if (! $config) {
            return [
                'success' => false,
                'message' => 'Invalid sitemap type: '.$type,
                'count' => 0,
            ];
        }

        if (! $this->isTypeEnabled($type)) {
            return [
                'success' => false,
                'message' => ucfirst($type).' sitemap is disabled',
                'count' => 0,
            ];
        }

        // Clear existing files for this type
        $folder = $config['folder'];
        $this->clearFolder($folder);

        // Get data for sitemap
        $data = $this->getDataForType($type, $config);

        if ($data === []) {
            return [
                'success' => true,
                'message' => ucfirst($type).' sitemap generated (empty)',
                'count' => 0,
            ];
        }

        // Generate sitemap files
        $linksPerFile = (int) setting('seo.sitemap.links_per_file', 1000);
        $this->generateSitemapFiles($data, $config, $linksPerFile);

        return [
            'success' => true,
            'message' => ucfirst($type).' sitemap generated',
            'count' => count($data),
        ];
    }

    /**
     * Generate all enabled sitemaps.
     */
    public function generateAll(): array
    {
        $results = [];

        foreach ($this->getEnabledTypes() as $type) {
            $results[$type] = $this->generate($type);
        }

        // Generate sitemap index
        $this->generateIndex();

        // Update last generated timestamp
        $this->updateLastGeneratedTimestamp();

        return $results;
    }

    /**
     * Generate sitemap index file.
     */
    public function generateIndex(): bool
    {
        $sitemaps = [];

        foreach ($this->getEnabledTypes() as $type) {
            $config = $this->getTypeConfig($type);
            $folder = $config['folder'];
            $folderPath = $this->basePath.'/'.$folder;

            if (! is_dir($folderPath)) {
                continue;
            }

            $files = array_diff(scandir($folderPath), ['.', '..']);

            foreach ($files as $file) {
                if (pathinfo($file, PATHINFO_EXTENSION) === 'xml') {
                    $filePath = $folderPath.'/'.$file;
                    $sitemaps[] = [
                        'url' => url(sprintf('sitemaps/%s/%s', $folder, $file)),
                        'lastmod' => sitemap_date_time_format(filemtime($filePath)),
                    ];
                }
            }
        }

        // Generate index XML
        $xml = $this->buildIndexXml($sitemaps);
        $indexPath = $this->basePath.'/sitemap-index.xml';

        if (! is_dir($this->basePath)) {
            mkdir($this->basePath, 0755, true);
        }

        return (bool) file_put_contents($indexPath, $xml);
    }

    /**
     * Get sitemap status information.
     */
    public function getStatus(): array
    {
        $status = [
            'enabled' => $this->isEnabled(),
            'last_generated_at' => setting('seo.sitemap.last_generated_at'),
            'types' => [],
            'total_urls' => 0,
        ];

        foreach ($this->getTypes() as $type => $config) {
            $enabled = $this->isTypeEnabled($type);
            $count = $enabled ? $this->getUrlCountForType($type, $config) : 0;

            $status['types'][$type] = [
                'label' => $config['label'],
                'icon' => $config['icon'],
                'enabled' => $enabled,
                'count' => $count,
            ];

            $status['total_urls'] += $count;
        }

        return $status;
    }

    /**
     * Get sitemap index data for serving.
     */
    public function getSitemapIndex(): array
    {
        if (! $this->isEnabled()) {
            return [];
        }

        $sitemaps = [];

        foreach ($this->getEnabledTypes() as $type) {
            $config = $this->getTypeConfig($type);
            $folder = $config['folder'];
            $folderPath = $this->basePath.'/'.$folder;

            if (! is_dir($folderPath)) {
                continue;
            }

            $files = array_diff(scandir($folderPath), ['.', '..']);

            foreach ($files as $file) {
                if (pathinfo($file, PATHINFO_EXTENSION) === 'xml') {
                    $filePath = $folderPath.'/'.$file;
                    $sitemaps[] = [
                        'url' => url(sprintf('sitemaps/%s/%s', $folder, $file)),
                        'updated_at' => sitemap_date_time_format(filemtime($filePath)),
                    ];
                }
            }
        }

        return $sitemaps;
    }

    /**
     * Clear all generated sitemaps.
     */
    public function clearAll(): bool
    {
        if (is_dir($this->basePath)) {
            File::deleteDirectory($this->basePath);
        }

        return true;
    }

    /**
     * Get data for a specific sitemap type.
     */
    protected function getDataForType(string $type, array $config): array
    {
        // Check for custom generator
        if (! empty($config['custom_generator'])) {
            $method = $config['custom_generator'];

            return $this->$method($config);
        }

        // Standard CMS content
        return $this->getStandardData($type, $config);
    }

    /**
     * Get standard CMS content data.
     */
    protected function getStandardData(string $type, array $config): array
    {
        $noIndexRobots = ['noindex, follow', 'noindex, nofollow'];

        $queryParams = [
            'not_meta_robot' => $noIndexRobots,
            'status' => 'published',
        ];

        // Exclude home page from pages sitemap (it's handled separately)
        if ($type === 'pages' && ! empty($config['include_home'])) {
            $homePageId = setting('cms_default_pages_home_page', '');
            if (! empty($homePageId)) {
                $queryParams['not_ids'] = $homePageId;
            }
        }

        $data = CmsPost::getDataForSitemap($config['type_filter'], $queryParams);

        // Add home page for pages type
        if ($type === 'pages' && ! empty($config['include_home'])) {
            array_unshift($data, [
                'url' => url('/'),
                'updated_at' => Date::now(),
                'priority' => '1.0',
                'changefreq' => 'daily',
            ]);
        }

        return $data;
    }

    /**
     * Generate authors sitemap data.
     */
    protected function generateAuthorsSitemap(array $config): array
    {
        $authorsData = [];

        // Get users who have published posts
        $authorIds = CmsPost::query()->where('type', 'post')
            ->where('status', 'published')
            ->whereNotNull('author_id')
            ->distinct()
            ->pluck('author_id');

        if ($authorIds->isEmpty()) {
            return [];
        }

        $authors = User::query()->select('id', 'name', 'username', 'updated_at')
            ->whereIn('id', $authorIds)
            ->whereNotNull('username')
            ->get();

        foreach ($authors as $author) {
            $authorsData[] = [
                'url' => $this->buildAuthorUrl($author->username),
                'updated_at' => $author->updated_at,
            ];
        }

        return $authorsData;
    }

    /**
     * Build author URL.
     */
    protected function buildAuthorUrl(string $username): string
    {
        $cmsBase = setting('seo_cms_base', '');
        $authorBase = setting('seo_authors_author_base', 'author');

        $segments = array_filter([$cmsBase, $authorBase, $username]);

        return url('/'.implode('/', $segments));
    }

    /**
     * Generate sitemap XML files.
     */
    protected function generateSitemapFiles(array $data, array $config, int $linksPerFile): void
    {
        $folder = $config['folder'];
        $folderPath = $this->basePath.'/'.$folder;

        if (! is_dir($folderPath)) {
            mkdir($folderPath, 0755, true);
        }

        $xmlHeader = $this->xmlGenerator->buildXmlHeader(
            asset(config('cms.sitemap.paths.stylesheet', 'css/sitemap.xsl'))
        );
        $xmlFooter = $this->xmlGenerator->buildXmlFooter();

        $chunks = array_chunk($data, $linksPerFile);

        foreach ($chunks as $index => $chunk) {
            $xmlContent = $xmlHeader;

            foreach ($chunk as $item) {
                $xmlContent .= $this->xmlGenerator->buildUrlEntry(
                    $item['url'],
                    sitemap_date_time_format($item['updated_at'] ?? now()),
                    $item['changefreq'] ?? $config['changefreq'],
                    $item['priority'] ?? $config['priority']
                );
            }

            $xmlContent .= $xmlFooter;

            $filename = $index === 0 ? 'sitemap.xml' : sprintf('sitemap%d.xml', $index);
            file_put_contents($folderPath.'/'.$filename, $xmlContent);
        }
    }

    /**
     * Build sitemap index XML.
     */
    protected function buildIndexXml(array $sitemaps): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<?xml-stylesheet type="text/xsl" href="'.asset(config('cms.sitemap.paths.stylesheet', 'css/sitemap.xsl')).'?stype=root"?>'."\n";
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($sitemaps as $sitemap) {
            $xml .= '  <sitemap>'."\n";
            $xml .= '    <loc><![CDATA['.$sitemap['url'].']]></loc>'."\n";
            $xml .= '    <lastmod><![CDATA['.$sitemap['lastmod'].']]></lastmod>'."\n";
            $xml .= '  </sitemap>'."\n";
        }

        return $xml.'</sitemapindex>'."\n";
    }

    /**
     * Clear a specific folder.
     */
    protected function clearFolder(string $folder): void
    {
        $folderPath = $this->basePath.'/'.$folder;

        if (is_dir($folderPath)) {
            File::deleteDirectory($folderPath);
        }
    }

    /**
     * Get URL count for a type.
     */
    protected function getUrlCountForType(string $type, array $config): int
    {
        if (! empty($config['custom_generator'])) {
            // For authors, count users with posts
            if ($type === 'authors') {
                return CmsPost::query()->where('type', 'post')
                    ->where('status', 'published')
                    ->whereNotNull('author_id')
                    ->distinct('author_id')
                    ->count('author_id');
            }

            return 0;
        }

        // Standard CMS content
        $count = CmsPost::query()->where('type', $config['type_filter'])
            ->where('status', 'published')
            ->count();

        // Add 1 for home page in pages sitemap
        if ($type === 'pages' && ! empty($config['include_home'])) {
            $count++;
        }

        return $count;
    }

    /**
     * Update last generated timestamp.
     */
    protected function updateLastGeneratedTimestamp(): void
    {
        $timestamp = now()->toIso8601String();

        // Use Settings model directly
        Settings::query()->updateOrCreate(['group' => 'seo', 'key' => 'seo.sitemap.last_generated_at'], ['value' => $timestamp, 'type' => 'string']);

        // Note: Settings cache is automatically invalidated by SettingsObserver
    }
}
