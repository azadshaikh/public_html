<?php

return [
    'name' => 'CMS',
    'slug' => 'cms',
    'version' => '1.0.0',
    'description' => 'A sample content management plugin that lives entirely inside the plugins directory.',
    'features' => [
        'Content dashboard powered by Inertia and React',
        'Plugin routes, config, views, and migrations loaded through a dedicated provider',
        'Shared access to host layouts, UI components, auth, and other application services',
    ],
    'navigation' => [
        [
            'label' => 'Content dashboard',
            'href' => '/cms',
        ],
        [
            'label' => 'Main dashboard',
            'href' => '/dashboard',
        ],
    ],
];
