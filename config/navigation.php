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
    | - Icon integration
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
                    'icon' => 'ri-dashboard-line',
                    'permission' => 'view_dashboard',
                    'active_patterns' => ['dashboard'],
                    'badge' => null,
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
                    'icon' => 'ri-team-line',
                    'permission' => 'view_users',
                    'active_patterns' => ['app.users.*'],
                ],
                'roles' => [
                    'label' => 'Roles & Permissions',
                    'route' => 'app.roles.index',
                    'icon' => 'ri-shield-check-line',
                    'permission' => 'view_roles',
                    'active_patterns' => ['app.roles.*'],
                    'badge' => null,
                ],
                'media_library' => [
                    'label' => 'Media Library',
                    'route' => ['name' => 'app.media-library.index', 'params' => ['all']],
                    'icon' => 'ri-gallery-line',
                    'permission' => 'view_media',
                    'active_patterns' => ['app.media-library.*'],
                ],
                'seo_integrations' => [
                    'label' => 'seo::seo.seo_integrations',
                    'route' => 'cms.integrations.index',
                    'icon' => 'ri-code-s-slash-fill',
                    'permission' => 'manage_integrations_seo_settings',
                    'module' => 'cms',
                    'active_patterns' => ['cms.integrations.*'],
                ],
                'redirections' => [
                    'label' => 'seo::seo.redirections',
                    'route' => 'cms.redirections.index',
                    'icon' => 'ri-shuffle-line',
                    'permission' => 'view_redirections',
                    'module' => 'cms',
                    'active_patterns' => ['cms.redirections.*'],
                ],
                'cms_forms' => [
                    'label' => 'Forms',
                    'route' => ['name' => 'cms.form.index', 'params' => ['status' => 'all']],
                    'icon' => 'ri-checkbox-multiple-line',
                    'permission' => 'view_cms_forms',
                    'module' => 'cms',
                    'active_patterns' => ['cms.form.*'],
                ],
                'seo_settings' => [
                    'label' => 'seo::seo.seo',
                    'icon' => 'ri-search-line',
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
                        'seo.settings.importexport*',
                        'seo.settings.export*',
                        'seo.settings.import*',
                    ],
                    'children' => [
                        'seo_dashboard' => [
                            'label' => 'Dashboard',
                            'route' => 'seo.dashboard',
                            'permission' => 'manage_cms_seo_settings',
                            'active_patterns' => ['seo.dashboard'],
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
                        ],
                        'seo_local_seo' => [
                            'label' => 'seo::seo.local_seo',
                            'route' => 'seo.settings.localseo',
                            'permission' => 'manage_seo_settings',
                            'active_patterns' => ['seo.settings.localseo', 'seo.settings.localseo.update'],
                        ],
                        'seo_social_media' => [
                            'label' => 'seo::seo.social_media',
                            'route' => 'seo.settings.socialmedia',
                            'permission' => 'manage_seo_settings',
                            'active_patterns' => ['seo.settings.socialmedia', 'seo.settings.socialmedia.update'],
                        ],
                        'seo_schema' => [
                            'label' => 'seo::seo.schema',
                            'route' => 'seo.settings.schema',
                            'permission' => 'manage_seo_settings',
                            'active_patterns' => ['seo.settings.schema', 'seo.settings.schema.update'],
                        ],
                        'seo_sitemap' => [
                            'label' => 'seo::seo.sitemap',
                            'route' => 'seo.settings.sitemap',
                            'permission' => 'manage_seo_settings',
                            'active_patterns' => ['seo.settings.sitemap', 'seo.settings.sitemap.update'],
                        ],
                        'seo_robots' => [
                            'label' => 'seo::seo.robots',
                            'route' => 'seo.settings.robots',
                            'permission' => 'manage_seo_settings',
                            'active_patterns' => ['seo.settings.robots', 'seo.settings.robots.update'],
                        ],
                        'seo_import_export' => [
                            'label' => 'seo::seo.import_export',
                            'route' => 'seo.settings.importexport',
                            'permission' => 'manage_seo_settings',
                            'active_patterns' => ['seo.settings.importexport', 'seo.settings.export', 'seo.settings.import'],
                        ],
                    ],
                ],
                'settings' => [
                    'label' => 'Settings',
                    'route' => 'app.settings.index',
                    'icon' => 'ri-settings-3-line',
                    'permission' => 'manage_system_settings',
                    'active_patterns' => ['app.settings.*'],
                ],
                'logs' => [
                    'label' => 'Logs',
                    'route' => '#',
                    'icon' => 'ri-file-text-line',
                    'permission' => 'view_activity_logs',
                    'active_patterns' => ['app.logs.activity-logs.*', 'app.logs.login-attempts.*', 'app.logs.not-found-logs.*'],
                    'children' => [
                        'activity_logs' => [
                            'label' => 'Activity Logs',
                            'route' => 'app.logs.activity-logs.index',
                            'permission' => 'view_activity_logs',
                            'active_patterns' => ['app.logs.activity-logs.*'],
                        ],
                        'login_attempts' => [
                            'label' => 'Login Attempts',
                            'route' => 'app.logs.login-attempts.index',
                            'permission' => 'view_login_attempts',
                            'active_patterns' => ['app.logs.login-attempts.*'],
                        ],
                        'not_found_logs' => [
                            'label' => '404 Logs',
                            'route' => 'app.logs.not-found-logs.index',
                            'permission' => 'view_not_found_logs',
                            'active_patterns' => ['app.logs.not-found-logs.*'],
                        ],
                    ],
                ],
            ],
        ],
        'chatbot' => [
            'label' => 'AI Assistant',
            'weight' => 200,
            'area' => 'top',
            'show_label' => true,
            'items' => [
                'chatbot_chat' => [
                    'label' => 'Chat',
                    'route' => 'app.chatbot.new',
                    'icon' => 'ri-chat-ai-line',
                    'permission' => 'use_chatbot',
                    'module' => 'ChatBot',
                    'active_patterns' => ['app.chatbot.new', 'app.chatbot.index', 'app.chatbot.show', 'app.chatbot.destroy'],
                ],
                'chatbot_settings' => [
                    'label' => 'ChatBot Settings',
                    'route' => 'app.chatbot.settings.index',
                    'icon' => 'ri-settings-4-line',
                    'permission' => 'manage_chatbot_settings',
                    'module' => 'ChatBot',
                    'active_patterns' => ['app.chatbot.settings.*'],
                ],
            ],
        ],
        'ai_registry' => [
            'label' => 'AI Registry',
            'weight' => 210,
            'area' => 'top',
            'show_label' => true,
            'module' => 'AIRegistry',
            'items' => [
                'ai_providers' => [
                    'label' => 'Providers',
                    'route' => 'ai-registry.providers.index',
                    'icon' => 'ri-cpu-line',
                    'permission' => 'view_ai_providers',
                    'module' => 'AIRegistry',
                    'active_patterns' => ['ai-registry.providers.*'],
                ],
                'ai_models_list' => [
                    'label' => 'Models',
                    'route' => 'ai-registry.models.index',
                    'icon' => 'ri-robot-line',
                    'permission' => 'view_ai_models',
                    'module' => 'AIRegistry',
                    'active_patterns' => ['ai-registry.models.*'],
                ],
            ],
        ],
        'masters' => [
            'label' => 'Masters',
            'weight' => 300,
            'area' => 'modules',
            'show_label' => true,
            'items' => [
                'modules' => [
                    'label' => 'Modules',
                    'route' => 'app.masters.modules.index',
                    'icon' => 'ri-puzzle-2-line',
                    'permission' => 'view_modules',
                    'active_patterns' => ['app.masters.modules.*'],
                    'badge' => [
                        'type' => 'static',
                        'value' => 'New',
                        'color' => 'success',
                    ],
                ],
                'groups' => [
                    'label' => 'Groups',
                    'route' => 'app.masters.groups.index',
                    'icon' => 'ri-folder-line',
                    'permission' => 'view_groups',
                    'active_patterns' => ['app.masters.groups.*'],
                ],
                'addresses' => [
                    'label' => 'Addresses',
                    'route' => 'app.masters.addresses.index',
                    'icon' => 'ri-map-pin-line',
                    'permission' => 'view_addresses',
                    'active_patterns' => ['app.masters.addresses.*'],
                ],
                'email' => [
                    'label' => 'Email Management',
                    'route' => 'app.masters.email.providers.index',
                    'icon' => 'ri-mail-line',
                    'permission' => 'view_email_providers',
                    'active_patterns' => ['app.masters.email.*'],
                    'children' => [
                        'email_providers' => [
                            'label' => 'Email Providers',
                            'route' => 'app.masters.email.providers.index',
                            'permission' => 'view_email_providers',
                            'active_patterns' => ['app.masters.email.providers.*'],
                        ],
                        'email_templates' => [
                            'label' => 'Email Templates',
                            'route' => 'app.masters.email.templates.index',
                            'permission' => 'view_email_templates',
                            'active_patterns' => ['app.masters.email.templates.*'],
                        ],
                        'email_logs' => [
                            'label' => 'Email Logs',
                            'route' => ['name' => 'app.masters.email.logs.index', 'params' => ['status' => 'all']],
                            'permission' => 'view_email_logs',
                            'active_patterns' => ['app.masters.email.logs.*'],
                        ],
                    ],
                ],
                'settings' => [
                    'label' => 'Master Settings',
                    'route' => 'app.masters.settings.index',
                    'icon' => 'ri-tools-line',
                    'permission' => 'manage_master_settings',
                    'active_patterns' => ['app.masters.settings.*'],
                ],
                'laravel_log' => [
                    'label' => 'Laravel Log',
                    'route' => 'log-viewer.index',
                    'icon' => 'ri-file-list-3-line',
                    'target' => '_blank',
                    'permission' => 'view_laravel_log',
                    'active_patterns' => ['log-viewer.index'],
                ],
                'laravel_jobs' => [
                    'label' => 'Queue Monitor',
                    'route' => 'app.masters.queue-monitor.index',
                    'icon' => 'ri-stack-line',
                    'permission' => 'manage_settings',
                    'active_patterns' => ['app.masters.queue-monitor.*'],
                ],
                'laravel_tools' => [
                    'label' => 'Laravel Tools',
                    'route' => 'app.masters.laravel-tools.index',
                    'icon' => 'ri-terminal-box-line',
                    'hard_reload' => true,
                    'permission' => 'manage_settings',
                    'active_patterns' => ['app.masters.laravel-tools.*'],
                    'badge' => [
                        'type' => 'static',
                        'value' => 'New',
                        'color' => 'primary',
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
                    'icon' => 'ri-user-line',
                    'permission' => null,
                    'active_patterns' => ['app.profile', 'app.profile.edit'],
                ],
                'security' => [
                    'label' => 'Security',
                    'route' => 'app.profile.security',
                    'icon' => 'ri-shield-check-line',
                    'permission' => null,
                    'active_patterns' => ['app.profile.security', 'app.profile.security.*'],
                ],
                'notifications' => [
                    'label' => 'Notifications',
                    'route' => 'app.notifications.index',
                    'icon' => 'ri-notification-3-line',
                    'permission' => null,
                    'active_patterns' => ['app.notifications.*'],
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
