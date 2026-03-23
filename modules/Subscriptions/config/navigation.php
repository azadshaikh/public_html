<?php

return [
    'sections' => [
        'subscriptions' => [
            'label' => 'Subscriptions',
            'weight' => 56,
            'area' => 'top',
            'show_label' => true,
            'items' => [
                'subscriptions_plans' => [
                    'label' => 'Plans',
                    'route' => 'subscriptions.plans.index',
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/><path d="M13 5v2"/><path d="M13 17v2"/><path d="M13 11v2"/></svg>',
                    'permission' => 'view_plans',
                    'active_patterns' => ['subscriptions.plans.*'],
                ],
                'subscriptions_subscriptions' => [
                    'label' => 'Subscriptions',
                    'route' => 'subscriptions.subscriptions.index',
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/><path d="m9 12 2 2 4-4"/></svg>',
                    'permission' => 'view_subscriptions',
                    'active_patterns' => ['subscriptions.subscriptions.*'],
                ],
            ],
        ],
    ],
];
