<?php

use Modules\CMS\Models\CmsPost;

return [
    /*
    |--------------------------------------------------------------------------
    | Sitemap Types Configuration
    |--------------------------------------------------------------------------
    |
    | Each type defines how sitemaps are generated for different content types.
    | All types share a common structure with type-specific customizations.
    |
    */
    'types' => [
        'posts' => [
            'label' => 'Posts',
            'icon' => 'ri-article-line',
            'description' => 'Blog posts and articles',
            'folder' => 'posts',
            'priority' => '0.8',
            'changefreq' => 'weekly',
            'model' => CmsPost::class,
            'type_filter' => 'post',
            'enabled_key' => 'seo.sitemap.posts_enabled',
        ],
        'pages' => [
            'label' => 'Pages',
            'icon' => 'ri-file-text-line',
            'description' => 'Static pages',
            'folder' => 'pages',
            'priority' => '0.6',
            'changefreq' => 'monthly',
            'model' => CmsPost::class,
            'type_filter' => 'page',
            'enabled_key' => 'seo.sitemap.pages_enabled',
            'include_home' => true,
        ],
        'categories' => [
            'label' => 'Categories',
            'icon' => 'ri-folder-line',
            'description' => 'Category archive pages',
            'folder' => 'categories',
            'priority' => '0.7',
            'changefreq' => 'weekly',
            'model' => CmsPost::class,
            'type_filter' => 'category',
            'enabled_key' => 'seo.sitemap.categories_enabled',
        ],
        'tags' => [
            'label' => 'Tags',
            'icon' => 'ri-price-tag-3-line',
            'description' => 'Tag archive pages',
            'folder' => 'tags',
            'priority' => '0.5',
            'changefreq' => 'weekly',
            'model' => CmsPost::class,
            'type_filter' => 'tag',
            'enabled_key' => 'seo.sitemap.tags_enabled',
        ],
        'authors' => [
            'label' => 'Authors',
            'icon' => 'ri-user-line',
            'description' => 'Author profile pages',
            'folder' => 'authors',
            'priority' => '0.6',
            'changefreq' => 'monthly',
            'enabled_key' => 'seo.sitemap.authors_enabled',
            'custom_generator' => 'generateAuthorsSitemap',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Settings
    |--------------------------------------------------------------------------
    |
    | Default values used when generating sitemaps.
    |
    */
    'defaults' => [
        'priority' => '0.5',
        'changefreq' => 'weekly',
        'links_per_file' => 1000,
        'max_links_per_file' => 50000, // Per sitemap protocol spec
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Paths
    |--------------------------------------------------------------------------
    |
    | Where generated sitemap files are stored.
    |
    */
    'paths' => [
        'base' => 'sitemaps',
        'stylesheet' => 'css/sitemap.xsl',
    ],
];
