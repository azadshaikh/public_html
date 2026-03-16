<?php

use Modules\CMS\Models\CmsPost;
use Modules\CMS\Models\Menu;
use Modules\CMS\Services\PermaLinkService;

if (! function_exists('get_menu')) {
    /**
     * Get a menu by location
     *
     * @param  string  $location  Menu location (primary, footer, sidebar, etc.)
     */
    function get_menu(string $location): ?Menu
    {
        return Menu::getByLocation($location);
    }
}

if (! function_exists('has_menu')) {
    /**
     * Check if a menu exists for a location
     *
     * @param  string  $location  Menu location
     */
    function has_menu(string $location): bool
    {
        $menu = get_menu($location);

        return $menu && $menu->hasItems();
    }
}

if (! function_exists('menu_breadcrumb')) {
    /**
     * Generate breadcrumb navigation based on current page
     *
     * @param  array  $attributes  HTML attributes for breadcrumb container
     * @param  string  $return_type  Return type - 'html' for HTML string, anything else for array
     * @return string|array Breadcrumb HTML or array based on return_type parameter
     */
    function menu_breadcrumb(array $attributes = [], $return_type = 'html'): string|array
    {
        $class = $attributes['class'] ?? 'breadcrumb';
        $separator = $attributes['separator'] ?? '<span class="separator"> / </span>';
        $breadcrumbs = [];
        $breadcrumbs_array = [];
        $breadcrumbs_array[] = [
            'label' => 'Home',
            'url' => route('home'),
        ];
        $breadcrumbs[] = '<a href="'.route('home').'">Home</a>';

        // Add current page to breadcrumb
        $currentRoute = request()->route();
        if ($currentRoute) {
            $routeName = $currentRoute->getName();
            switch ($routeName) {
                case 'page':
                case 'cms.view':
                    $parameters = request()->segments();
                    $permaLinkService = resolve(PermaLinkService::class);
                    $cms_breadcrumbs = $permaLinkService->generateCmsBreadcrumb($parameters);

                    foreach ($cms_breadcrumbs as $breadcrumb) {
                        $breadcrumbs_array[] = $breadcrumb;
                        if (! empty($breadcrumb['url'])) {
                            $breadcrumbs[] = '<a href="'.$breadcrumb['url'].'" class="text-capitalize">'.$breadcrumb['label'].'</a>';
                        } else {
                            $breadcrumbs[] = '<span class="text-capitalize">'.$breadcrumb['label'].'</span>';
                        }
                    }

                    break;

                case 'cms.builder.load':
                    $page = $currentRoute->parameter('page');
                    if ($page) {
                        // Handle case where $page might be an ID instead of a model
                        if (is_numeric($page) || is_string($page)) {
                            try {
                                $page = CmsPost::query()->find($page);
                            } catch (Exception) {
                                $page = null;
                            }
                        }

                        // Only proceed if $page is not null
                        if ($page) {
                            $breadcrumbs_array[] = [
                                'label' => 'Dashboard',
                                'url' => route('dashboard'),
                            ];
                            $breadcrumbs[] = '<a href="'.route('dashboard').'">Dashboard</a>';

                            if ($page->type === 'page') {
                                $breadcrumbs_array[] = [
                                    'label' => 'Pages',
                                    'url' => route('cms.pages.index', 'all'),
                                ];
                                $breadcrumbs[] = '<a href="'.route('cms.pages.index', 'all').'">Pages</a>';
                            } else {
                                $breadcrumbs_array[] = [
                                    'label' => 'Posts',
                                    'url' => route('cms.posts.index', 'all'),
                                ];
                                $breadcrumbs[] = '<a href="'.route('cms.posts.index', 'all').'">Posts</a>';
                            }

                            if (isset($page->title)) {
                                $breadcrumbs_array[] = [
                                    'label' => safe_content($page->title).' (Builder)',
                                    'url' => '',
                                ];
                                $breadcrumbs[] = '<span class="current">'.safe_content($page->title).' (Builder)</span>';
                            }
                        }
                    }

                    break;
                case 'archive':
                case 'blog':
                case 'posts':
                    $breadcrumbs_array[] = [
                        'label' => 'Blog',
                        'url' => '',
                    ];
                    $breadcrumbs[] = '<span class="current">Blog</span>';
                    break;

                case 'search':
                    $query = request()->query('q', '');
                    $breadcrumbs_array[] = [
                        'label' => 'Search'.($query ? ': '.safe_content($query) : ''),
                        'url' => '',
                    ];
                    $breadcrumbs[] = '<span class="current">Search'.($query ? ': '.safe_content($query) : '').'</span>';
                    break;

                case 'about':
                    $breadcrumbs_array[] = [
                        'label' => 'About',
                        'url' => '',
                    ];
                    $breadcrumbs[] = '<span class="current">About</span>';
                    break;

                case 'contact':
                    $breadcrumbs_array[] = [
                        'label' => 'Contact',
                        'url' => '',
                    ];
                    $breadcrumbs[] = '<span class="current">Contact</span>';
                    break;

                default:
                    // For other routes, try to generate breadcrumb from URL path
                    $path = request()->path();
                    if ($path !== '/' && $path !== 'home') {
                        $segments = explode('/', trim($path, '/'));
                        $lastSegment = end($segments);
                        $breadcrumbs[] = '<span class="current">'.safe_content(ucwords(str_replace(['-', '_'], ' ', $lastSegment))).'</span>';
                    }

                    break;
            }
        }

        if (count($breadcrumbs) <= 1) {
            return $return_type === 'html' ? '' : [];
        }

        if ($return_type === 'html') {
            $html = '<nav class="'.$class.'" aria-label="breadcrumb">';
            $html .= implode($separator, $breadcrumbs);

            return $html.'</nav>';
        }

        return $breadcrumbs_array;
    }
}
