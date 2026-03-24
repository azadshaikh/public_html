<?php

return [
    'sections' => [
        'ai_registry' => [
            'label' => 'AI Registry',
            'weight' => 85,
            'area' => 'top',
            'show_label' => true,
            'items' => [
                'ai_registry_providers' => [
                    'label' => 'Providers',
                    'route' => ['name' => 'ai-registry.providers.index', 'params' => ['all']],
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16"></path><path d="M4 12h16"></path><path d="M4 18h16"></path><path d="M8 4v4"></path><path d="M16 10v4"></path><path d="M12 16v4"></path></svg>',
                    'permission' => 'view_ai_providers',
                    'active_patterns' => ['ai-registry.providers.*'],
                ],
                'ai_registry_models' => [
                    'label' => 'Models',
                    'route' => ['name' => 'ai-registry.models.index', 'params' => ['all']],
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2 3 7l9 5 9-5-9-5Z"></path><path d="m3 17 9 5 9-5"></path><path d="m3 12 9 5 9-5"></path></svg>',
                    'permission' => 'view_ai_models',
                    'active_patterns' => ['ai-registry.models.*'],
                ],
            ],
        ],
    ],
];
