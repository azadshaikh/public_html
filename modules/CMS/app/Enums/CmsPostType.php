<?php

namespace Modules\CMS\Enums;

enum CmsPostType: string
{
    case POST = 'post';
    case PAGE = 'page';
    case CATEGORY = 'category';
    case TAG = 'tag';

    public function label(): string
    {
        return match ($this) {
            self::POST => 'Post',
            self::PAGE => 'Page',
            self::CATEGORY => 'Category',
            self::TAG => 'Tag',
        };
    }

    public function pluralLabel(): string
    {
        return match ($this) {
            self::POST => 'Posts',
            self::PAGE => 'Pages',
            self::CATEGORY => 'Categories',
            self::TAG => 'Tags',
        };
    }
}
