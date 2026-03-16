<?php

namespace Modules\CMS\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\CMS\Services\SitemapService;

class SitemapController extends Controller
{
    public function __construct(protected SitemapService $sitemapService) {}

    /**
     * Display the sitemap index (serves pre-generated file).
     */
    public function index()
    {
        // Check if sitemap is enabled
        abort_unless($this->sitemapService->isEnabled(), 404);

        // Check search engine visibility
        abort_if(setting('seo_search_engine_visibility', '') === 'noindex', 404);

        $indexPath = public_path('sitemaps/sitemap-index.xml');

        // Generate if file doesn't exist
        // Still check after generation attempt
        if (! file_exists($indexPath)) {
            $this->sitemapService->generateAll();
            abort(404);
        }

        return response()
            ->file($indexPath, ['Content-Type' => 'text/xml']);
    }

    /**
     * Display a specific sitemap type.
     */
    public function show(string $type)
    {
        abort_unless($this->sitemapService->isEnabled(), 404);

        $config = $this->sitemapService->getTypeConfig($type);

        abort_unless((bool) $config, 404);

        abort_unless($this->sitemapService->isTypeEnabled($type), 404);

        $folder = $config['folder'];
        $sitemapPath = public_path(sprintf('sitemaps/%s/sitemap.xml', $folder));

        if (! file_exists($sitemapPath)) {
            // Generate on-the-fly if not exists
            $this->sitemapService->generate($type);
            abort(404);
        }

        return response()
            ->file($sitemapPath, ['Content-Type' => 'text/xml']);
    }
}
