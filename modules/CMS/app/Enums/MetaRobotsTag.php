<?php

namespace Modules\CMS\Enums;

enum MetaRobotsTag: string
{
    case INDEX_FOLLOW = 'index, follow';
    case INDEX_NOFOLLOW = 'index, nofollow';
    case NOINDEX_FOLLOW = 'noindex, follow';
    case NOINDEX_NOFOLLOW = 'noindex, nofollow';

    /**
     * Get all available options for forms/settings
     */
    public static function options(): array
    {
        return [
            self::INDEX_FOLLOW->value => 'Index, Follow',
            self::INDEX_NOFOLLOW->value => 'Index, NoFollow',
            self::NOINDEX_FOLLOW->value => 'NoIndex, Follow',
            self::NOINDEX_NOFOLLOW->value => 'NoIndex, NoFollow',
        ];
    }

    /**
     * Get label for display
     */
    public function label(): string
    {
        return match ($this) {
            self::INDEX_FOLLOW => 'Index, Follow',
            self::INDEX_NOFOLLOW => 'Index, NoFollow',
            self::NOINDEX_FOLLOW => 'NoIndex, Follow',
            self::NOINDEX_NOFOLLOW => 'NoIndex, NoFollow',
        };
    }

    /**
     * Check if this tag allows indexing
     */
    public function allowsIndexing(): bool
    {
        return str_starts_with($this->value, 'index');
    }

    /**
     * Check if this tag allows following
     */
    public function allowsFollowing(): bool
    {
        return str_ends_with($this->value, 'follow');
    }

    /**
     * Get default robots tag for new content
     */
    public static function default(): self
    {
        return self::INDEX_FOLLOW;
    }
}
