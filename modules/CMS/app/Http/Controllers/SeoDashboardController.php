<?php

namespace Modules\CMS\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\View\View;
use Modules\CMS\Services\SitemapService;

/**
 * SEO Dashboard Controller
 *
 * Provides an overview of SEO status and quick access to SEO settings.
 */
class SeoDashboardController extends Controller
{
    public function __construct(
        private readonly SitemapService $sitemapService
    ) {}

    /**
     * Display the SEO Dashboard
     */
    public function index(): View
    {
        // Check permission
        abort_if(! auth()->user()->can('manage_cms_seo_settings') && ! auth()->user()->can('manage_seo_settings'), 401);

        // Get search engine visibility status
        $searchEngineEnabled = in_array(
            setting('seo_search_engine_visibility', 'false'),
            ['true', true, 1, '1'],
            true
        );

        // Get sitemap status
        $sitemapStatus = $this->sitemapService->getStatus();

        // Get quick stats
        $stats = [
            'robots_txt_exists' => file_exists(public_path('robots.txt')),
            'sitemap_exists' => $sitemapStatus['exists'] ?? false,
            'sitemap_last_generated' => $sitemapStatus['last_generated'] ?? null,
        ];

        // Quick links for the dashboard
        $quickLinks = [
            [
                'label' => 'Titles & Meta',
                'description' => 'Configure SEO titles and meta descriptions for all content types',
                'route' => 'seo.settings.titlesmeta',
                'icon' => 'ri-file-text-line',
                'color' => 'primary',
            ],
            [
                'label' => 'Local SEO',
                'description' => 'Set up your business information for local search',
                'route' => 'seo.settings.localseo',
                'icon' => 'ri-map-pin-line',
                'color' => 'success',
            ],
            [
                'label' => 'Social Media',
                'description' => 'Configure Open Graph and Twitter Card settings',
                'route' => 'seo.settings.socialmedia',
                'icon' => 'ri-share-line',
                'color' => 'info',
            ],
            [
                'label' => 'Sitemap',
                'description' => 'Manage XML sitemap generation and settings',
                'route' => 'seo.settings.sitemap',
                'icon' => 'ri-node-tree',
                'color' => 'warning',
            ],
            [
                'label' => 'Robots.txt',
                'description' => 'Edit your robots.txt file for crawler control',
                'route' => 'seo.settings.robots',
                'icon' => 'ri-robot-2-line',
                'color' => 'secondary',
            ],
            [
                'label' => 'Schema Markup',
                'description' => 'Configure structured data for rich search results',
                'route' => 'seo.settings.schema',
                'icon' => 'ri-code-s-slash-line',
                'color' => 'dark',
            ],
        ];

        /** @var view-string $view */
        $view = 'seo::dashboard';

        return view($view, [
            'page_title' => 'SEO Dashboard',
            'searchEngineEnabled' => $searchEngineEnabled,
            'sitemapStatus' => $sitemapStatus,
            'stats' => $stats,
            'quickLinks' => $quickLinks,
        ]);
    }
}
