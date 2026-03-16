<?php

return [
    'name' => 'ReleaseManager',
    'api' => [
        'release_key' => config('astero.release_api_key', ''),
    ],
    'status_options' => [
        ['label' => 'Draft', 'class' => 'bg-secondary-subtle text-secondary', 'value' => 'draft'],
        ['label' => 'Published', 'class' => 'bg-success-subtle text-success', 'value' => 'published'],
        ['label' => 'Deprecate', 'class' => 'bg-warning-subtle text-warning', 'value' => 'deprecate'],
    ],
    'template_types' => [
        'common' => ['label' => 'All Contents', 'value' => 'common'],
        'pages' => ['label' => 'Page', 'value' => 'page'],
        'posts' => ['label' => 'Post', 'value' => 'post'],
        // 'topbar' => ['label' => 'Topbar', 'value' => 'topbar'],
        // 'header' => ['label' => 'Header', 'value' => 'header'],
        // 'footer' => ['label' => 'Footer', 'value' => 'footer'],
        // 'breadcrumbs' => ['label' => 'Breadcrumbs', 'value' => 'breadcrumbs'],
        // 'layout' => ['label' => 'Layout', 'value' => 'layout'],
        // 'page_layout' => ['label' => 'Page Layout', 'value' => 'page_layout'],
        // 'post_layout' => ['label' => 'Post Layout', 'value' => 'post_layout'],
        // 'category_layout' => ['label' => 'Category Layout', 'value' => 'category_layout'],
        // 'tag_layout' => ['label' => 'Tag Layout', 'value' => 'tag_layout'],
        // 'author_layout' => ['label' => 'Author Layout', 'value' => 'author_layout'],
        // 'cookies' => ['label' => 'Cookies', 'value' => 'cookies']
    ],
    'layout_types' => [
        'page' => ['label' => 'Page', 'value' => 'page'],
        'post' => ['label' => 'Post', 'value' => 'post'],
        'category' => ['label' => 'Category', 'value' => 'category'],
        'tag' => ['label' => 'Tag', 'value' => 'tag'],
    ],
    'release_types' => [
        ['label' => 'Application', 'value' => 'application', 'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="4 17 10 11 4 5"/><line x1="12" y1="19" x2="20" y2="19"/></svg>'],
        ['label' => 'Module', 'value' => 'module', 'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="5" x="2" y="3" rx="1"/><path d="M4 8v11a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8"/><path d="M10 12h4"/></svg>'],
        // ['label' => 'Theme', 'value' => 'theme', 'icon' => 'ri-palette-line'],
    ],
    'version_types' => [
        ['label' => 'Major', 'value' => 'major'],
        ['label' => 'Minor', 'value' => 'minor'],
        ['label' => 'Patch', 'value' => 'patch'],
    ],
];
