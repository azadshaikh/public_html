<?php

/*
|--------------------------------------------------------------------------
| CMS Module Navigation Configuration
|--------------------------------------------------------------------------
|
| Migrated from resources/views/_partials/sidebar_nav.blade.php
| Uses the unified navigation schema (same as SEO/SaaS/etc).
| Icons: Inline SVG (Lucide style).
|
*/

return [
    'sections' => [
        // CMS main section
        'cms' => [
            'label' => 'CMS',
            'weight' => 100,
            'area' => 'cms',
            'show_label' => true,
            'items' => [

                // Posts
                'cms_posts' => [
                    'label' => 'Blog',
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="9" y1="3" x2="9" y2="21"/></svg>',
                    'permission' => null, // emulate canany; children gated
                    'active_patterns' => ['cms.posts.*', 'cms.categories.*', 'cms.tags.*'],
                    'default_open' => true,
                    'children' => [
                        'cms_posts' => [
                            'label' => 'Posts',
                            'route' => ['name' => 'cms.posts.index', 'params' => ['all']],
                            'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><line x1="10" y1="13" x2="16" y2="13"/><line x1="10" y1="17" x2="14" y2="17"/></svg>',
                            'permission' => 'view_posts',
                            'active_patterns' => ['cms.posts.*'],
                        ],
                        'cms_posts_create' => [
                            'label' => 'Create Post',
                            'route' => 'cms.posts.create',
                            'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14"/><path d="M5 12h14"/><path d="M3 12a9 9 0 1 0 18 0a9 9 0 1 0-18 0"/></svg>',
                            'permission' => 'add_posts',
                            'active_patterns' => ['cms.posts.create'],
                            'sidebar_visible' => false,
                            'quick_open' => [
                                'priority' => 250,
                                'description' => 'Write and publish a new blog post',
                                'aliases' => ['New Post', 'Add Post'],
                                'keywords' => ['create blog post', 'new blog post', 'write post', 'publish post'],
                            ],
                        ],
                        'cms_categories' => [
                            'label' => 'Categories',
                            'route' => ['name' => 'cms.categories.index', 'params' => ['all']],
                            'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"/></svg>',
                            'permission' => 'view_categories',
                            'active_patterns' => ['cms.categories.*'],
                        ],
                        'cms_tags' => [
                            'label' => 'Tags',
                            'route' => ['name' => 'cms.tags.index', 'params' => ['status' => 'all']],
                            'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.586 2.586A2 2 0 0 0 11.172 2H4a2 2 0 0 0-2 2v7.172a2 2 0 0 0 .586 1.414l8.704 8.704a2.426 2.426 0 0 0 3.42 0l6.58-6.58a2.426 2.426 0 0 0 0-3.42z"/><circle cx="7.5" cy="7.5" r=".5" fill="currentColor"/></svg>',
                            'permission' => 'view_tags',
                            'active_patterns' => ['cms.tags.*'],
                        ],
                    ],
                ],

                // Pages
                'cms_pages' => [
                    'label' => 'Pages',
                    'route' => ['name' => 'cms.pages.index', 'params' => ['all']],
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="8" y="2" width="12" height="18" rx="2"/><path d="M4 4v16a2 2 0 0 0 2 2h12"/></svg>',
                    'permission' => 'view_pages',
                    'active_patterns' => ['cms.pages.*'],
                ],
                'cms_pages_create' => [
                    'label' => 'Create Page',
                    'route' => 'cms.pages.create',
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14"/><path d="M5 12h14"/><path d="M5 3h10a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"/></svg>',
                    'permission' => 'add_pages',
                    'active_patterns' => ['cms.pages.create'],
                    'sidebar_visible' => false,
                    'quick_open' => [
                        'priority' => 250,
                        'description' => 'Create a new website page',
                        'aliases' => ['New Page', 'Add Page'],
                        'keywords' => ['create page', 'new page', 'add page', 'create website page'],
                    ],
                ],

                // Design Blocks (stored in cms_posts with type='design_block')
                'cms_design_blocks' => [
                    'label' => 'Design Blocks',
                    'route' => ['name' => 'cms.designblock.index', 'params' => ['status' => 'all']],
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19.439 7.85c-.049.322.059.648.289.878l1.568 1.568c.47.47.706 1.087.706 1.704s-.235 1.233-.706 1.704l-1.611 1.611a.98.98 0 0 1-.837.276c-.47-.07-.802-.48-.968-.925a2.501 2.501 0 1 0-3.214 3.214c.446.166.855.497.925.968a.979.979 0 0 1-.276.837l-1.61 1.61a2.404 2.404 0 0 1-1.705.707 2.402 2.402 0 0 1-1.704-.706l-1.568-1.568a1.026 1.026 0 0 0-.877-.29c-.493.074-.84.504-1.02.968a2.5 2.5 0 1 1-3.237-3.237c.464-.18.894-.527.967-1.02a1.026 1.026 0 0 0-.289-.877l-1.568-1.568A2.402 2.402 0 0 1 1.998 12c0-.617.236-1.234.706-1.704L4.23 8.77c.24-.24.581-.353.917-.303.515.077.877.528 1.073 1.01a2.5 2.5 0 1 0 3.259-3.259c-.482-.196-.933-.558-1.01-1.073-.05-.336.062-.676.303-.917l1.525-1.525A2.402 2.402 0 0 1 12 1.998c.617 0 1.234.236 1.704.706l1.568 1.568c.23.23.556.338.877.29.493-.074.84-.504 1.02-.968a2.5 2.5 0 1 1 3.237 3.237c-.464.18-.894.527-.967 1.02Z"/></svg>',
                    'permission' => 'view_design_blocks',
                    'active_patterns' => ['cms.designblock.*'],
                ],

            ],
        ],
        // Appearance separated into its own section
        'appearance' => [
            'label' => 'Appearance',
            'weight' => 101,
            'area' => 'cms',
            'show_label' => true,
            'items' => [
                'cms_themes' => [
                    'label' => 'Themes',
                    'route' => 'cms.appearance.themes.index',
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="13.5" cy="6.5" r=".5" fill="currentColor"/><circle cx="17.5" cy="10.5" r=".5" fill="currentColor"/><circle cx="8.5" cy="7.5" r=".5" fill="currentColor"/><circle cx="6.5" cy="12.5" r=".5" fill="currentColor"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z"/></svg>',
                    'permission' => 'view_themes',
                    'active_patterns' => ['cms.appearance.themes.*'],
                ],
                'cms_menus' => [
                    'label' => 'Menus',
                    'route' => 'cms.appearance.menus.index',
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="6" x2="20" y2="6"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="18" x2="20" y2="18"/></svg>',
                    'permission' => 'view_menus',
                    'active_patterns' => ['cms.appearance.menus.*'],
                ],
                'cms_widgets' => [
                    'label' => 'Widgets',
                    'route' => 'cms.appearance.widgets.index',
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>',
                    'permission' => 'view_widgets',
                    'active_patterns' => ['cms.appearance.widgets.*'],
                ],
            ],
        ],

        // CMS Settings section
        'cms_settings' => [
            'label' => 'Settings',
            'weight' => 102,
            'area' => 'cms',
            'show_label' => true,
            'items' => [
                'cms_default_pages' => [
                    'label' => 'Default Pages',
                    'route' => 'cms.settings.default-pages',
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><circle cx="12" cy="15" r="2"/><path d="M12 13v-1"/><path d="M12 17v1"/><path d="M14 15h1"/><path d="M10 15H9"/></svg>',
                    'permission' => 'manage_default_pages',
                    'active_patterns' => ['cms.settings.default-pages*'],
                ],
            ],
        ],
    ],

    'badge_functions' => [],
];
