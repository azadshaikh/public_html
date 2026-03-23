<?php

return [
    'sections' => [
        'customers' => [
            'label' => 'Customers',
            'weight' => 50,
            'area' => 'top',
            'show_label' => true,
            'items' => [
                'customers_main' => [
                    'label' => 'Customers',
                    'route' => 'app.customers.index',
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
                    'permission' => 'view_customers',
                    'active_patterns' => ['app.customers.*'],
                ],
                'customer_contacts' => [
                    'label' => 'Contacts',
                    'route' => 'app.customers.contacts.index',
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 18a2 2 0 0 0-2-2H9a2 2 0 0 0-2 2"/><rect width="18" height="18" x="3" y="4" rx="2"/><circle cx="12" cy="10" r="2"/><line x1="8" x2="8" y1="2" y2="4"/><line x1="16" x2="16" y1="2" y2="4"/></svg>',
                    'permission' => 'view_customer_contacts',
                    'active_patterns' => ['app.customers.contacts.*'],
                ],
            ],
        ],
    ],
];
