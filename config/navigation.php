<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configure navigation caching for better performance.
    | Cache TTL is in seconds (21600 = 6 hours).
    |
    */
    'cache_ttl' => env('NAVIGATION_CACHE_TTL', 21600), // 6 hours default
    'cache_enabled' => env('NAVIGATION_CACHE_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Navigation Sections Configuration
    |--------------------------------------------------------------------------
    |
    | Define the navigation structure with sections, items, submenus, and badges.
    | This configuration supports:
    | - Hierarchical navigation with submenus
    | - Permission-based filtering (use 'permission' key)
    | - Module-based visibility (use 'module' key - e.g., 'module' => 'cms')
    | - Static badges (dynamic badges are intentionally disabled)
    | - Active state detection
    | - Inline SVG icon support
    | - Link behaviors (new tab + hard reload)
    |
    | Module Visibility Example:
    | 'item_key' => [
    |     'label' => 'My Item',
    |     'route' => 'my.route',
    |     'module' => 'CMS',  // Only show if CMS module is active
    |     'permission' => 'view_something',
    | ]
    |
    | Link Behavior (NEW)
    |
    | 1) Open in new tab:
    | 'item_key' => [
    |     'label' => 'Laravel Log',
    |     'route' => 'log-viewer.index',
    |     'target' => '_blank',
    | ]
    |
    | 2) Hard reload (disable Unpoly and do full page load in same tab):
    | 'item_key' => [
    |     'label' => 'Laravel Tools',
    |     'route' => 'app.masters.laravel-tools.index',
    |     'hard_reload' => true,
    | ]
    |
    | Advanced: Raw link attributes
    | - Use 'attributes' to pass HTML attributes to the <a> tag.
    | - You can also force non-Unpoly behavior with: 'attributes' => ['up-follow' => false]
    |
    */
    'sections' => [
        'dashboard' => [
            'label' => 'Dashboards',
            'weight' => 100,
            'area' => 'top',
            'show_label' => true,
            'items' => [
                'dashboard' => [
                    'label' => 'Dashboard',
                    'route' => 'dashboard',
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="8" rx="1"></rect><rect x="14" y="3" width="7" height="5" rx="1"></rect><rect x="14" y="12" width="7" height="9" rx="1"></rect><rect x="3" y="15" width="7" height="6" rx="1"></rect></svg>',
                    'permission' => 'view_dashboard',
                    'active_patterns' => ['dashboard'],
                    'badge' => null,
                    'quick_open' => [
                        'aliases' => ['Home'],
                        'keywords' => ['overview', 'analytics', 'admin home'],
                    ],
                ],
            ],

        ],
        'manage' => [
            'label' => 'Manage',
            'weight' => 120,
            'area' => 'cms',
            'show_label' => true,
            'items' => [
                'users' => [
                    'label' => 'Users',
                    'route' => 'app.users.index',
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>',
                    'permission' => 'view_users',
                    'active_patterns' => ['app.users.*'],
                    'quick_open' => [
                        'aliases' => ['Members', 'Team'],
                        'keywords' => ['user management', 'manage users', 'staff'],
                    ],
                ],
                'roles' => [
                    'label' => 'Roles & Permissions',
                    'route' => 'app.roles.index',
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"></path><path d="m9 12 2 2 4-4"></path></svg>',
                    'permission' => 'view_roles',
                    'active_patterns' => ['app.roles.*'],
                    'badge' => null,
                    'quick_open' => [
                        'aliases' => ['Permissions', 'Access Control'],
                        'keywords' => ['rbac', 'user roles', 'permission management'],
                    ],
                ],
                'media_library' => [
                    'label' => 'Media Library',
                    'route' => ['name' => 'app.media-library.index', 'params' => ['all']],
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"></rect><circle cx="9" cy="9" r="2"></circle><path d="m21 15-3.1-3.1a2 2 0 0 0-2.8 0L6 21"></path></svg>',
                    'permission' => 'view_media',
                    'active_patterns' => ['app.media-library.*'],
                    'quick_open' => [
                        'aliases' => ['Media', 'Files'],
                        'keywords' => ['images', 'uploads', 'asset library'],
                    ],
                ],
                'seo_integrations' => [
                    'label' => 'seo::seo.seo_integrations',
                    'route' => 'cms.integrations.index',
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m16 18 6-6-6-6"></path><path d="m8 6-6 6 6 6"></path><path d="m14.5 4-5 16"></path></svg>',
                    'permission' => 'manage_integrations_seo_settings',
                    'module' => 'cms',
                    'active_patterns' => ['cms.integrations.*'],
                    'quick_open' => [
                        'aliases' => ['Integrations', 'SEO Integrations'],
                        'keywords' => ['webmaster tools', 'search integrations', 'third party integrations'],
                    ],
                ],
                'redirections' => [
                    'label' => 'seo::seo.redirections',
                    'route' => 'cms.redirections.index',
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 3h5v5"></path><path d="M4 20 21 3"></path><path d="M21 16v5h-5"></path><path d="M15 15 21 21"></path><path d="M4 4l5 5"></path></svg>',
                    'permission' => 'view_redirections',
                    'module' => 'cms',
                    'active_patterns' => ['cms.redirections.*'],
                    'quick_open' => [
                        'aliases' => ['Redirects'],
                        'keywords' => ['301 redirects', 'redirect rules', 'url forwarding'],
                    ],
                ],
                'cms_forms' => [
                    'label' => 'Forms',
                    'route' => ['name' => 'cms.form.index', 'params' => ['status' => 'all']],
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="3" width="12" height="12" rx="2"></rect><path d="m13 9 2 2 4-4"></path><rect x="3" y="9" width="12" height="12" rx="2"></rect><path d="m7 15 2 2 4-4"></path></svg>',
                    'permission' => 'view_cms_forms',
                    'module' => 'cms',
                    'active_patterns' => ['cms.form.*'],
                    'quick_open' => [
                        'aliases' => ['Form Builder'],
                        'keywords' => ['submissions', 'contact forms', 'forms'],
                    ],
                ],
                'seo_settings' => [
                    'label' => 'seo::seo.seo',
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>',
                    'permission' => null,
                    'module' => 'cms',
                    'active_patterns' => [
                        'seo.dashboard',
                        'seo.settings.titlesmeta*',
                        'seo.settings.localseo*',
                        'seo.settings.socialmedia*',
                        'seo.settings.schema*',
                        'seo.settings.sitemap*',
                        'seo.settings.robots*',
                    ],
                    'children' => [
                        'seo_dashboard' => [
                            'label' => 'Dashboard',
                            'route' => 'seo.dashboard',
                            'permission' => 'manage_cms_seo_settings',
                            'active_patterns' => ['seo.dashboard'],
                            'quick_open' => [
                                'aliases' => ['SEO Dashboard'],
                                'keywords' => ['seo overview', 'search performance', 'seo analytics'],
                            ],
                        ],
                        'seo_cms' => [
                            'label' => 'seo::seo.titles_meta',
                            'route' => 'seo.settings.titlesmeta',
                            'permission' => 'manage_cms_seo_settings',
                            'active_patterns' => [
                                'seo.settings.titlesmeta',
                                'seo.settings.titlesmeta.update',
                                'seo.settings.general.update',
                            ],
                            'quick_open' => [
                                'aliases' => ['Titles & Meta'],
                                'keywords' => ['meta titles', 'meta descriptions', 'seo titles'],
                            ],
                        ],
                        'seo_local_seo' => [
                            'label' => 'seo::seo.local_seo',
                            'route' => 'seo.settings.localseo',
                            'permission' => 'manage_seo_settings',
                            'active_patterns' => ['seo.settings.localseo', 'seo.settings.localseo.update'],
                            'quick_open' => [
                                'aliases' => ['Local SEO'],
                                'keywords' => ['business seo', 'location seo', 'local listings'],
                            ],
                        ],
                        'seo_social_media' => [
                            'label' => 'seo::seo.social_media',
                            'route' => 'seo.settings.socialmedia',
                            'permission' => 'manage_seo_settings',
                            'active_patterns' => ['seo.settings.socialmedia', 'seo.settings.socialmedia.update'],
                            'quick_open' => [
                                'aliases' => ['Social SEO'],
                                'keywords' => ['open graph', 'twitter cards', 'social sharing'],
                            ],
                        ],
                        'seo_schema' => [
                            'label' => 'seo::seo.schema',
                            'route' => 'seo.settings.schema',
                            'permission' => 'manage_seo_settings',
                            'active_patterns' => ['seo.settings.schema', 'seo.settings.schema.update'],
                            'quick_open' => [
                                'aliases' => ['Structured Data'],
                                'keywords' => ['schema markup', 'json ld', 'rich results'],
                            ],
                        ],
                        'seo_sitemap' => [
                            'label' => 'seo::seo.sitemap',
                            'route' => 'seo.settings.sitemap',
                            'permission' => 'manage_seo_settings',
                            'active_patterns' => ['seo.settings.sitemap', 'seo.settings.sitemap.update'],
                            'quick_open' => [
                                'aliases' => ['XML Sitemap'],
                                'keywords' => ['site map', 'crawl urls', 'sitemap settings'],
                            ],
                        ],
                        'seo_robots' => [
                            'label' => 'seo::seo.robots',
                            'route' => 'seo.settings.robots',
                            'permission' => 'manage_seo_settings',
                            'active_patterns' => ['seo.settings.robots', 'seo.settings.robots.update'],
                            'quick_open' => [
                                'aliases' => ['Robots.txt'],
                                'keywords' => ['crawl rules', 'indexing rules', 'robots settings'],
                            ],
                        ],
                    ],
                ],
                'settings' => [
                    'label' => 'Settings',
                    'route' => 'app.settings.index',
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.7 1.7 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.7 1.7 0 0 0-1.82-.33 1.7 1.7 0 0 0-1 1.54V21a2 2 0 1 1-4 0v-.09a1.7 1.7 0 0 0-1-1.54 1.7 1.7 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-1.54-1H3a2 2 0 1 1 0-4h.09a1.7 1.7 0 0 0 1.54-1 1.7 1.7 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.7 1.7 0 0 0 8.95 4.6a1.7 1.7 0 0 0 1-1.54V3a2 2 0 1 1 4 0v.09a1.7 1.7 0 0 0 1 1.54 1.7 1.7 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.7 1.7 0 0 0 19.4 9c.2.5.78 1 1.54 1H21a2 2 0 1 1 0 4h-.09c-.76 0-1.34.5-1.51 1z"></path></svg>',
                    'permission' => 'manage_system_settings',
                    'active_patterns' => ['app.settings.*'],
                    'quick_open' => [
                        'aliases' => ['System Settings'],
                        'keywords' => ['app settings', 'configuration', 'preferences'],
                    ],
                ],
                'logs' => [
                    'label' => 'Logs',
                    'route' => '#',
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><path d="M14 2v6h6"></path><path d="M16 13H8"></path><path d="M16 17H8"></path><path d="M10 9H8"></path></svg>',
                    'permission' => 'view_activity_logs',
                    'active_patterns' => ['app.logs.activity-logs.*', 'app.logs.login-attempts.*', 'app.logs.not-found-logs.*'],
                    'children' => [
                        'activity_logs' => [
                            'label' => 'Activity Logs',
                            'route' => 'app.logs.activity-logs.index',
                            'permission' => 'view_activity_logs',
                            'active_patterns' => ['app.logs.activity-logs.*'],
                            'quick_open' => [
                                'aliases' => ['Audit Log'],
                                'keywords' => ['audit trail', 'activity history'],
                            ],
                        ],
                        'login_attempts' => [
                            'label' => 'Login Attempts',
                            'route' => 'app.logs.login-attempts.index',
                            'permission' => 'view_login_attempts',
                            'active_patterns' => ['app.logs.login-attempts.*'],
                            'quick_open' => [
                                'aliases' => ['Sign-in Attempts'],
                                'keywords' => ['failed logins', 'auth logs', 'signin attempts'],
                            ],
                        ],
                        'not_found_logs' => [
                            'label' => '404 Logs',
                            'route' => 'app.logs.not-found-logs.index',
                            'permission' => 'view_not_found_logs',
                            'active_patterns' => ['app.logs.not-found-logs.*'],
                            'quick_open' => [
                                'aliases' => ['404 Errors'],
                                'keywords' => ['missing pages', 'not found errors', 'broken urls'],
                            ],
                        ],
                    ],
                ],
            ],
        ],
        'masters' => [
            'label' => 'Masters',
            'weight' => 300,
            'area' => 'modules',
            'show_label' => true,
            'role' => 'super_user',
            'items' => [
                'modules' => [
                    'label' => 'Modules',
                    'route' => 'app.masters.modules.index',
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14h-2a2 2 0 1 0 0 4h2v2a2 2 0 0 1-2 2h-3v-2a2 2 0 1 0-4 0v2H7a2 2 0 0 1-2-2v-3h2a2 2 0 1 0 0-4H5V7a2 2 0 0 1 2-2h2v2a2 2 0 1 0 4 0V5h4a2 2 0 0 1 2 2z"></path></svg>',
                    'active_patterns' => ['app.masters.modules.*'],
                    'badge' => [
                        'type' => 'static',
                        'value' => 'New',
                        'color' => 'success',
                    ],
                    'quick_open' => [
                        'aliases' => ['Extensions'],
                        'keywords' => ['plugins', 'addons', 'installed modules'],
                    ],
                ],
                'addresses' => [
                    'label' => 'Addresses',
                    'route' => 'app.masters.addresses.index',
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 0 1 16 0"></path><circle cx="12" cy="10" r="3"></circle></svg>',
                    'active_patterns' => ['app.masters.addresses.*'],
                    'quick_open' => [
                        'aliases' => ['Locations'],
                        'keywords' => ['address book', 'geo data', 'location records'],
                    ],
                ],
                'email' => [
                    'label' => 'Email Management',
                    'route' => 'app.masters.email.providers.index',
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="m3 7 9 6 9-6"></path></svg>',
                    'active_patterns' => ['app.masters.email.*'],
                    'quick_open' => [
                        'aliases' => ['Mail'],
                        'keywords' => ['smtp', 'email providers', 'email templates', 'mail logs'],
                    ],
                    'children' => [
                        'email_providers' => [
                            'label' => 'Email Providers',
                            'route' => 'app.masters.email.providers.index',
                            'active_patterns' => ['app.masters.email.providers.*'],
                            'quick_open' => [
                                'aliases' => ['SMTP Providers'],
                                'keywords' => ['mail providers', 'transactional email'],
                            ],
                        ],
                        'email_templates' => [
                            'label' => 'Email Templates',
                            'route' => 'app.masters.email.templates.index',
                            'active_patterns' => ['app.masters.email.templates.*'],
                            'quick_open' => [
                                'aliases' => ['Mail Templates'],
                                'keywords' => ['email content', 'template editor'],
                            ],
                        ],
                        'email_logs' => [
                            'label' => 'Email Logs',
                            'route' => ['name' => 'app.masters.email.logs.index', 'params' => ['status' => 'all']],
                            'active_patterns' => ['app.masters.email.logs.*'],
                            'quick_open' => [
                                'aliases' => ['Mail Logs'],
                                'keywords' => ['delivery logs', 'sent emails', 'email history'],
                            ],
                        ],
                    ],
                ],
                'settings' => [
                    'label' => 'Master Settings',
                    'route' => 'app.masters.settings.index',
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.7 1.7 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.7 1.7 0 0 0-1.82-.33 1.7 1.7 0 0 0-1 1.54V21a2 2 0 1 1-4 0v-.09a1.7 1.7 0 0 0-1-1.54 1.7 1.7 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-1.54-1H3a2 2 0 1 1 0-4h.09a1.7 1.7 0 0 0 1.54-1 1.7 1.7 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.7 1.7 0 0 0 8.95 4.6a1.7 1.7 0 0 0 1-1.54V3a2 2 0 1 1 4 0v.09a1.7 1.7 0 0 0 1 1.54 1.7 1.7 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.7 1.7 0 0 0-.33 1.82 1.7 1.7 0 0 0 1.54 1H21a2 2 0 1 1 0 4h-.09a1.7 1.7 0 0 0-1.51 1z"></path></svg>',
                    'active_patterns' => ['app.masters.settings.*'],
                    'quick_open' => [
                        'aliases' => ['System Masters'],
                        'keywords' => ['master settings', 'master data', 'global master config'],
                    ],
                ],
                'laravel_log' => [
                    'label' => 'Laravel Log',
                    'route' => 'log-viewer.index',
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 6h13"></path><path d="M8 12h13"></path><path d="M8 18h13"></path><path d="M3 6h.01"></path><path d="M3 12h.01"></path><path d="M3 18h.01"></path></svg>',
                    'target' => '_blank',
                    'active_patterns' => ['log-viewer.index'],
                    'quick_open' => [
                        'aliases' => ['Application Log'],
                        'keywords' => ['error logs', 'exception logs', 'log viewer'],
                    ],
                ],
                'laravel_jobs' => [
                    'label' => 'Queue Monitor',
                    'route' => 'app.masters.queue-monitor.index',
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 2 9 5-9 5-9-5z"></path><path d="m3 12 9 5 9-5"></path><path d="m3 17 9 5 9-5"></path></svg>',
                    'active_patterns' => ['app.masters.queue-monitor.*'],
                    'quick_open' => [
                        'aliases' => ['Jobs'],
                        'keywords' => ['queues', 'background jobs', 'failed jobs'],
                    ],
                ],
                'laravel_tools' => [
                    'label' => 'Laravel Tools',
                    'route' => 'app.masters.laravel-tools.index',
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"></rect><path d="m7 9 3 3-3 3"></path><path d="M13 15h4"></path></svg>',
                    'active_patterns' => ['app.masters.laravel-tools.*'],
                    'badge' => [
                        'type' => 'static',
                        'value' => 'New',
                        'color' => 'primary',
                    ],
                    'quick_open' => [
                        'aliases' => ['Dev Tools'],
                        'keywords' => ['artisan', 'routes', 'env', 'config', 'php'],
                    ],
                ],
            ],
        ],
        'account' => [
            'label' => 'Account',
            'weight' => 400,
            'area' => 'bottom',
            'show_label' => true,
            'items' => [
                'profile' => [
                    'label' => 'Profile',
                    'route' => 'app.profile',
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21a8 8 0 0 0-16 0"></path><circle cx="12" cy="7" r="4"></circle></svg>',
                    'permission' => null,
                    'active_patterns' => ['app.profile', 'app.profile.edit'],
                    'quick_open' => [
                        'aliases' => ['Account'],
                        'keywords' => ['my account', 'user profile'],
                    ],
                ],
                'security' => [
                    'label' => 'Security',
                    'route' => 'app.profile.security',
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"></path><path d="m9 12 2 2 4-4"></path></svg>',
                    'permission' => null,
                    'active_patterns' => ['app.profile.security', 'app.profile.security.*'],
                    'quick_open' => [
                        'aliases' => ['2FA'],
                        'keywords' => ['password', 'sessions', 'two factor', 'social logins'],
                    ],
                ],
                'notifications' => [
                    'label' => 'Notifications',
                    'route' => 'app.notifications.index',
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.268 21a2 2 0 0 0 3.464 0"></path><path d="M3.262 15.326A1 1 0 0 0 4 17h16a1 1 0 0 0 .74-1.673C19.41 13.956 18 12.499 18 8A6 6 0 0 0 6 8c0 4.499-1.411 5.956-2.738 7.326"></path></svg>',
                    'permission' => null,
                    'active_patterns' => ['app.notifications.*'],
                    'quick_open' => [
                        'aliases' => ['Alerts'],
                        'keywords' => ['notification center', 'email alerts', 'preferences'],
                    ],
                ],
                'logout' => [
                    'label' => 'Log out',
                    'route' => 'logout',
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m16 17 5-5-5-5"></path><path d="M21 12H9"></path><path d="M13 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h8"></path></svg>',
                    'permission' => null,
                    'active_patterns' => [],
                    'quick_open' => [
                        'priority' => 200,
                        'aliases' => ['Sign out'],
                        'keywords' => ['logout', 'end session'],
                    ],
                    'attributes' => [
                        'method' => 'post',
                        'as' => 'button',
                        'data-test' => 'sidebar-logout-button',
                    ],
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Badge Value Functions
    |--------------------------------------------------------------------------
    |
    | Define functions that can be called to generate dynamic badge values.
    | These functions should return a numeric value or string to display.
    |
    */
    // NOTE: Dynamic badge support disabled — badges are static to allow config caching.
    'badge_functions' => [],

    /*
    |--------------------------------------------------------------------------
    | UI Settings
    |--------------------------------------------------------------------------
    |
    | Configure the appearance and behavior of the navigation.
    |
    */
    'ui' => [
        'show_section_headers' => true,
        'show_badges' => true,
    ],
];
