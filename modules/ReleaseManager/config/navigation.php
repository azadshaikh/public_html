<?php

/*
|--------------------------------------------------------------------------
| ReleaseManager Module Navigation Configuration
|--------------------------------------------------------------------------
|
| Migrated from resources/views/_partials/sidebar_nav.blade.php
| Uses the unified navigation schema (same rules as SEO/SaaS modules).
| Icons: prefer Bootstrap Icons; falls back to configured icon strings.
|
*/

$releaseTypes = config('releasemanager.release_types', []);
$releaseTypeMenus = [];
foreach ($releaseTypes as $data) {
    if (! is_array($data)) {
        continue;
    }

    $typeValue = $data['value'] ?? null;
    if (! $typeValue) {
        continue;
    }

    $typeIcon = $data['icon'] ?? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8v11a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8"/><path d="M10 12h4"/></svg>';
    $releaseTypeMenus['rm_type_'.$typeValue] = [
        'label' => $data['label'] ?? ucfirst($typeValue),
        'icon' => $typeIcon,
        'permission' => 'view_releases',
        'active_patterns' => [
            ['route' => 'releasemanager.releases.index', 'params' => ['type' => $typeValue]],
            ['route' => 'releasemanager.releases.create', 'params' => ['type' => $typeValue]],
            ['route' => 'releasemanager.releases.show', 'params' => ['type' => $typeValue]],
            ['route' => 'releasemanager.releases.edit', 'params' => ['type' => $typeValue]],
        ],
        'route' => ['name' => 'releasemanager.releases.index', 'params' => ['type' => $typeValue, 'status' => 'all']],
    ];
}

return [
    'sections' => [
        'releasemanager' => [
            'label' => 'Release Manager',
            'weight' => 220,
            'area' => 'modules',
            'show_label' => true,
            'items' => $releaseTypeMenus,
        ],
    ],

    'badge_functions' => [],
];
