<?php

/*
|--------------------------------------------------------------------------
| Platform Module Navigation Configuration
|--------------------------------------------------------------------------
|
| Migrated from resources/views/_partials/sidebar_nav.blade.php
| Uses the unified navigation schema (same rules as SEO/SaaS/ReleaseManager).
| Icons: Inline SVG (Lucide style).
|
*/

return [
    'sections' => [
        'platform' => [
            'label' => 'Platform',
            'weight' => 110,
            'area' => 'top',
            'show_label' => true,
            'items' => [
                // Websites (direct link - no submenu for status variations)
                'platform_websites' => [
                    'label' => 'Websites',
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"></circle><path d="M3 12h18"></path><path d="M12 3a15 15 0 0 1 0 18"></path><path d="M12 3a15 15 0 0 0 0 18"></path></svg>',
                    'route' => ['name' => 'platform.websites.index', 'params' => ['all']],
                    'permission' => 'view_websites',
                    'active_patterns' => ['platform.websites.*'],
                ],

                // Servers (direct link - providers now managed via Groups)
                'platform_servers' => [
                    'label' => 'Servers',
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="6" rx="2"></rect><rect x="3" y="14" width="18" height="6" rx="2"></rect><path d="M7 7h.01"></path><path d="M7 17h.01"></path><path d="M11 7h6"></path><path d="M11 17h6"></path></svg>',
                    'route' => ['name' => 'platform.servers.index', 'params' => ['all']],
                    'permission' => 'view_servers',
                    'active_patterns' => ['platform.servers.*'],
                ],

                // Agencies (direct link - no submenu since groups removed)
                'platform_agencies' => [
                    'label' => 'Agencies',
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"></path><path d="M5 21V7l7-4 7 4v14"></path><path d="M9 9h.01"></path><path d="M15 9h.01"></path><path d="M9 13h.01"></path><path d="M15 13h.01"></path><path d="M10 21v-4h4v4"></path></svg>',
                    'route' => ['name' => 'platform.agencies.index', 'params' => ['all']],
                    'permission' => 'view_agencies',
                    'active_patterns' => ['platform.agencies.*'],
                ],

                // Domains (moved to top level)
                'platform_domains' => [
                    'label' => 'Domains',
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12h20"></path><path d="M12 2a10 10 0 1 0 0 20"></path><path d="M12 2a15 15 0 0 1 4 10 15 15 0 0 1-4 10"></path><path d="M12 2a15 15 0 0 0-4 10 15 15 0 0 0 4 10"></path></svg>',
                    'route' => ['name' => 'platform.domains.index', 'params' => ['all']],
                    'permission' => 'view_domains',
                    'active_patterns' => ['platform.domains.*', 'platform.dns.*'],
                ],

                // SSL Certificates (moved to top level)
                'platform_ssl_certificates' => [
                    'label' => 'SSL Certificates',
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"></rect><path d="M8 11V8a4 4 0 1 1 8 0v3"></path><path d="M12 15v2"></path></svg>',
                    'route' => ['name' => 'platform.ssl-certificates.index', 'params' => ['all']],
                    'permission' => 'view_domains',
                    'active_patterns' => ['platform.ssl-certificates.*'],
                ],

                // TLDs
                'platform_tlds' => [
                    'label' => 'TLDs',
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41 11 23l-9-9V4h10l8.59 8.59a2 2 0 0 1 0 2.82Z"></path><path d="M7 9h.01"></path></svg>',
                    'route' => ['name' => 'platform.tlds.index', 'params' => ['all']],
                    'permission' => 'view_tlds',
                    'active_patterns' => ['platform.tlds.*'],
                ],

                // Providers (DNS, CDN, Server, Domain Registrar)
                'platform_providers' => [
                    'label' => 'Providers',
                    'route' => ['name' => 'platform.providers.index', 'params' => ['all']],
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 18a4 4 0 1 1 .8-7.92A5.5 5.5 0 0 1 17.5 8a4.5 4.5 0 1 1 .5 9H6Z"></path></svg>',
                    'permission' => 'view_providers',
                    'active_patterns' => ['platform.providers.*'],
                ],

                // Secrets
                'platform_secrets' => [
                    'label' => 'Secrets',
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12h16"></path><path d="M7 12a3 3 0 1 1 0-6h2"></path><path d="M17 12a3 3 0 1 0 0 6h-2"></path><path d="M12 8v8"></path></svg>',
                    'route' => ['name' => 'platform.secrets.index', 'params' => ['all']],
                    'permission' => 'view_secrets',
                    'active_patterns' => ['platform.secrets.*'],
                ],

                // Settings
                'platform_settings' => [
                    'label' => 'Settings',
                    'route' => ['name' => 'platform.settings.index'],
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06A1.65 1.65 0 0 0 15 19.4a1.65 1.65 0 0 0-1 .6 1.65 1.65 0 0 0-.33 1V21a2 2 0 1 1-4 0v-.09a1.65 1.65 0 0 0-.33-1A1.65 1.65 0 0 0 8.4 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.6 15a1.65 1.65 0 0 0-.6-1 1.65 1.65 0 0 0-1-.33H3a2 2 0 1 1 0-4h.09a1.65 1.65 0 0 0 1-.33A1.65 1.65 0 0 0 4.6 8.4a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 8.4 4.6a1.65 1.65 0 0 0 1-.6 1.65 1.65 0 0 0 .33-1V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 .33 1 1.65 1.65 0 0 0 1 .6 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 8.4a1.65 1.65 0 0 0 .6 1 1.65 1.65 0 0 0 1 .33H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1 .33 1.65 1.65 0 0 0-.6 1Z"></path></svg>',
                    'permission' => 'manage_platform_settings',
                    'active_patterns' => ['platform.settings.*'],
                ],
            ],
        ],
    ],

    'badge_functions' => [],
];
