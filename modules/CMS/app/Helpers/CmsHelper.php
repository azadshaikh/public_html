<?php

use Illuminate\Support\Collection;
use Modules\CMS\Services\CmsPostCacheService;

if (! function_exists('get_popular_posts')) {
    function get_popular_posts(string $type = 'post', int $limit = 3): Collection
    {
        return resolve(CmsPostCacheService::class)->getPopularPosts($type, $limit);
    }
}

if (! function_exists('get_categories')) {
    function get_categories(string $type = 'category', int $limit = 6): Collection
    {
        return resolve(CmsPostCacheService::class)->getCategories($type, $limit);
    }
}

if (! function_exists('get_tags')) {
    function get_tags(string $type = 'tag', int $limit = 6): Collection
    {
        return resolve(CmsPostCacheService::class)->getTags($type, $limit);
    }
}
