<?php

namespace Modules\CMS\Http\Controllers;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;
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
    public function index(): Response
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
            'sitemap_exists' => ($sitemapStatus['total_urls'] ?? 0) > 0,
            'sitemap_last_generated' => $sitemapStatus['last_generated_at'] ?? null,
        ];

        // Quick links for the dashboard
        $quickLinks = [
            [
                'key' => 'titlesmeta',
                'label' => 'Titles & Meta',
                'description' => 'Configure SEO titles and meta descriptions for all content types',
                'href' => route('seo.settings.titlesmeta'),
            ],
            [
                'key' => 'localseo',
                'label' => 'Local SEO',
                'description' => 'Set up your business information for local search',
                'href' => route('seo.settings.localseo'),
            ],
            [
                'key' => 'socialmedia',
                'label' => 'Social Media',
                'description' => 'Configure Open Graph and Twitter Card settings',
                'href' => route('seo.settings.socialmedia'),
            ],
            [
                'key' => 'sitemap',
                'label' => 'Sitemap',
                'description' => 'Manage XML sitemap generation and settings',
                'href' => route('seo.settings.sitemap'),
            ],
            [
                'key' => 'robots',
                'label' => 'Robots.txt',
                'description' => 'Edit your robots.txt file for crawler control',
                'href' => route('seo.settings.robots'),
            ],
            [
                'key' => 'schema',
                'label' => 'Schema Markup',
                'description' => 'Configure structured data for rich search results',
                'href' => route('seo.settings.schema'),
            ],
        ];

        return Inertia::render('seo/dashboard', [
            'searchEngineEnabled' => $searchEngineEnabled,
            'sitemapStatus' => $sitemapStatus,
            'stats' => $stats,
            'quickLinks' => $quickLinks,
            'titlesMetaHref' => route('seo.settings.titlesmeta', ['section' => 'general']),
        ]);
    }
}
