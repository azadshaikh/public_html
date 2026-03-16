<?php

return [
    'name' => 'CMS',

    /*
    |--------------------------------------------------------------------------
    | Post Statuses (Single Source of Truth)
    |--------------------------------------------------------------------------
    |
    | Define all post statuses here. Used for:
    | - Validation rules (in:draft,published,...)
    | - Select options in forms
    | - Status tabs in datagrid
    | - Badge colors and labels
    |
    | Note: 'trash' is NOT a status - it's handled via soft deletes (deleted_at).
    |
    */
    'post_status' => [
        'draft' => ['value' => 'draft',          'label' => 'Draft',          'color' => 'warning', 'icon' => 'ri-file-line'],
        'published' => ['value' => 'published',      'label' => 'Published',      'color' => 'success', 'icon' => 'ri-checkbox-circle-line'],
        // 'pending_review' => ['value' => 'pending_review', 'label' => 'Pending Review', 'color' => 'warning', 'icon' => 'ri-hourglass-line'],
        'scheduled' => ['value' => 'scheduled',      'label' => 'Scheduled',      'color' => 'info',    'icon' => 'ri-time-line'],
    ],
    'post_visibility' => [
        'public' => ['value' => 'public', 'label' => 'Public'],
        'private' => ['value' => 'private', 'label' => 'Private'],
        'password' => ['value' => 'password', 'label' => 'Password Protected'],
    ],
    'design_formats' => [
        'builder' => ['value' => 'builder', 'label' => 'Builder'],
        'editor' => ['value' => 'editor', 'label' => 'Visual Editor'],
    ],
    'template_types' => [
        'common' => ['label' => 'All Contents', 'value' => 'common'],
        'pages' => ['label' => 'Page', 'value' => 'page'],
        'posts' => ['label' => 'Post', 'value' => 'post'],
    ],
    'layout_types' => [
        'page' => ['label' => 'Page', 'value' => 'page'],
        'post' => ['label' => 'Post', 'value' => 'post'],
        'category' => ['label' => 'Category', 'value' => 'category'],
        'tag' => ['label' => 'Tag', 'value' => 'tag'],
    ],
    'version_types' => [
        'major' => ['label' => 'Major', 'value' => 'major'],
        'minor' => ['label' => 'Minor', 'value' => 'minor'],
        'patch' => ['label' => 'Patch', 'value' => 'patch'],
    ],
    'design_types' => [
        'block' => ['label' => 'Block', 'value' => 'block'],
        'section' => ['label' => 'Section', 'value' => 'section'],
        'component' => ['label' => 'Component', 'value' => 'component'],
    ],
    'block_types' => [
        'static' => ['label' => 'Static', 'value' => 'static'],
        'dynamic' => ['label' => 'Dynamic', 'value' => 'dynamic'],
        'interactive' => ['label' => 'Interactive', 'value' => 'interactive'],
    ],
    'design_systems' => [
        'bootstrap' => ['label' => 'Bootstrap', 'value' => 'bootstrap'],
        'tailwind' => ['label' => 'Tailwind CSS', 'value' => 'tailwind'],
        'bulma' => ['label' => 'Bulma', 'value' => 'bulma'],
        'foundation' => ['label' => 'Foundation', 'value' => 'foundation'],
        'materialize' => ['label' => 'Materialize', 'value' => 'materialize'],
        'semantic' => ['label' => 'Semantic UI', 'value' => 'semantic'],
        'custom' => ['label' => 'Custom CSS', 'value' => 'custom'],
    ],
    'categories' => [
        'header' => ['label' => 'Header', 'value' => 'header'],
        'footer' => ['label' => 'Footer', 'value' => 'footer'],
        'hero' => ['label' => 'Hero Section', 'value' => 'hero'],
        'content' => ['label' => 'Content', 'value' => 'content'],
        'sidebar' => ['label' => 'Sidebar', 'value' => 'sidebar'],
        'call_to_action' => ['label' => 'Call to Action', 'value' => 'call_to_action'],
        'testimonial' => ['label' => 'Testimonial', 'value' => 'testimonial'],
        'pricing' => ['label' => 'Pricing', 'value' => 'pricing'],
        'contact' => ['label' => 'Contact', 'value' => 'contact'],
        'gallery' => ['label' => 'Gallery', 'value' => 'gallery'],
        'custom' => ['label' => 'Custom', 'value' => 'custom'],
    ],
    // Alias for backward compatibility
    'design_block_categories' => [
        'header' => 'Header',
        'footer' => 'Footer',
        'hero' => 'Hero Section',
        'content' => 'Content',
        'sidebar' => 'Sidebar',
        'call_to_action' => 'Call to Action',
        'testimonial' => 'Testimonial',
        'pricing' => 'Pricing',
        'contact' => 'Contact',
        'gallery' => 'Gallery',
        'custom' => 'Custom',
    ],

    /*
    |--------------------------------------------------------------------------
    | HTML Minification
    |--------------------------------------------------------------------------
    |
    | Enable HTML minification for frontend responses. This reduces response
    | size by removing unnecessary whitespace and comments from HTML output.
    |
    | HTML minification is always enabled for frontend responses.
    |
    */
    'html_minification' => [
        'enabled' => config('astero.html_minification_enabled', true),
    ],
];
