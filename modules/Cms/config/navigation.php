<?php

return [
    'sections' => [
        'cms_content' => [
            'label' => 'CMS',
            'weight' => 140,
            'area' => 'cms',
            'show_label' => true,
            'items' => [
                'cms_pages' => [
                    'label' => 'Pages',
                    'route' => 'cms.index',
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="3" width="16" height="18" rx="2"></rect><path d="M8 7h8"></path><path d="M8 11h8"></path><path d="M8 15h5"></path></svg>',
                    'active_patterns' => ['cms.*'],
                ],
            ],
        ],
    ],
];
