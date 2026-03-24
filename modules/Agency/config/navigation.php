<?php

/*
|--------------------------------------------------------------------------
| Agency Module Navigation Configuration
|--------------------------------------------------------------------------
|
| Customer portal sidebar navigation for the Agency module.
| Uses role-based filtering to show only customer-relevant items.
| Icons: Inline SVG (Lucide style).
|
*/

return [
    'sections' => [
        'customer_portal' => [
            'label' => 'Customer Portal',
            'weight' => 10,
            'area' => 'top',
            'show_label' => false,
            'role' => ['super_user', 'administrator', 'customer'],
            'items' => [
                'agency_sites' => [
                    'label' => 'Sites',
                    'route' => 'agency.websites.index',
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="14" rx="2"></rect><path d="M8 20h8"></path><path d="M12 18v2"></path><path d="M7 8h10"></path><path d="M7 12h6"></path></svg>',
                    'role' => ['super_user', 'administrator', 'customer'],
                    'active_patterns' => ['agency.websites.*'],
                ],
                'agency_domains' => [
                    'label' => 'Domains',
                    'route' => 'agency.domains.index',
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"></circle><path d="M3 12h18"></path><path d="M12 3a15 15 0 0 1 0 18"></path><path d="M12 3a15 15 0 0 0 0 18"></path></svg>',
                    'role' => ['super_user', 'administrator', 'customer'],
                    'active_patterns' => ['agency.domains.*'],
                ],
                'agency_billing' => [
                    'label' => 'Billing',
                    'route' => 'agency.billing.index',
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/></svg>',
                    'role' => ['super_user', 'administrator', 'customer'],
                    'active_patterns' => ['agency.billing.*', 'agency.subscriptions.*', 'agency.invoices.*', 'agency.payments.*'],
                ],
                'agency_tickets' => [
                    'label' => 'Support',
                    'route' => 'agency.tickets.index',
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12a8 8 0 0 1 16 0v5"></path><path d="M18 19a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2h-2v7Z"></path><path d="M6 19a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h2v7Z"></path><path d="M8 21h8"></path></svg>',
                    'role' => ['super_user', 'administrator', 'customer'],
                    'active_patterns' => ['agency.tickets.*'],
                ],
            ],
        ],

        'admin' => [
            'label' => 'Agency',
            'weight' => 50,
            'area' => 'top',
            'show_label' => true,
            'role' => ['super_user', 'administrator'],
            'items' => [
                'agency_admin_websites' => [
                    'label' => 'Websites',
                    'route' => 'agency.admin.websites.index',
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="6" rx="2"></rect><rect x="3" y="14" width="18" height="6" rx="2"></rect><path d="M7 7h.01"></path><path d="M7 17h.01"></path><path d="M11 7h6"></path><path d="M11 17h6"></path></svg>',
                    'role' => ['super_user', 'administrator'],
                    'active_patterns' => ['agency.admin.websites.*'],
                ],
                'agency_admin_settings' => [
                    'label' => 'Settings',
                    'route' => ['name' => 'agency.admin.settings.index'],
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06A1.65 1.65 0 0 0 15 19.4a1.65 1.65 0 0 0-1 .6 1.65 1.65 0 0 0-.33 1V21a2 2 0 1 1-4 0v-.09a1.65 1.65 0 0 0-.33-1A1.65 1.65 0 0 0 8.4 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.6 15a1.65 1.65 0 0 0-.6-1 1.65 1.65 0 0 0-1-.33H3a2 2 0 1 1 0-4h.09a1.65 1.65 0 0 0 1-.33A1.65 1.65 0 0 0 4.6 8.4a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 8.4 4.6a1.65 1.65 0 0 0 1-.6 1.65 1.65 0 0 0 .33-1V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 .33 1 1.65 1.65 0 0 0 1 .6 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 8.4a1.65 1.65 0 0 0 .6 1 1.65 1.65 0 0 0 1 .33H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1 .33 1.65 1.65 0 0 0-.6 1Z"></path></svg>',
                    'permission' => 'manage_agency_settings',
                    'active_patterns' => ['agency.admin.settings.*'],
                ],
            ],
        ],
    ],
];
