<?php

namespace Modules\CMS\Models\Presenters;

use DateTimeInterface;
use Modules\CMS\Services\PermaLinkService;

trait CmsPostPresenter
{
    protected function getFeaturedImageUrlAttribute()
    {
        return $this->getFeaturedImageByConversion();
    }

    protected function getFeaturedImageThumbnailUrlAttribute()
    {
        return $this->getFeaturedImageByConversion('thumbnail');
    }

    protected function getStatusBadgeAttribute(): string
    {
        if ($this->trashed()) {
            return '<span class="badge text-bg-danger">Trash</span>';
        }

        $status = config('cms.post_status.'.$this->status);

        if ($status) {
            return '<span class="badge text-bg-'.$status['color'].'">'.$status['label'].'</span>';
        }

        return '<span class="badge text-bg-primary">Status:'.ucwords($this->status).'</span>';
    }

    protected function getPostCategoryTitleAttribute()
    {
        $category = $this->category;

        return $category ? $category->title : '-';
    }

    protected function getVisibilityBadgeAttribute(): string
    {
        $visibility = config('cms.post_visibility.'.$this->visibility);

        if ($visibility) {
            return '<span class="badge bg-'.$visibility['color'].'-subtle text-'.$visibility['color'].'">'.$visibility['label'].'</span>';
        }

        return '<span class="badge text-bg-secondary">'.ucwords($this->visibility).'</span>';
    }

    protected function getFeaturedBadgeAttribute(): string
    {
        if ($this->is_featured) {
            return '<span class="badge bg-warning-subtle text-warning">Featured</span>';
        }

        return '';
    }

    protected function getReviewPendingBadgeAttribute(): string
    {
        if ($this->review_pending) {
            return '<span class="badge bg-warning-subtle text-warning">Pending Review</span>';
        }

        return '<span class="badge bg-success-subtle text-success">Reviewed</span>';
    }

    protected function getCommentStatusBadgeAttribute(): string
    {
        if ($this->comment_status === 'open') {
            return '<span class="badge bg-success-subtle text-success">Open</span>';
        }

        return '<span class="badge text-bg-secondary">Closed</span>';
    }

    protected function getCachingBadgeAttribute(): string
    {
        if ($this->enable_caching) {
            return '<span class="badge bg-success-subtle text-success">Enabled</span>';
        }

        return '<span class="badge text-bg-secondary">Disabled</span>';
    }

    protected function getAuthorNameAttribute()
    {
        // If author is already loaded, use it
        if ($this->relationLoaded('author') && $this->author) {
            return $this->author->name;
        }

        // Fallback to createdBy if loaded (for tags/categories that don't have author_id)
        if ($this->relationLoaded('createdBy') && $this->createdBy) {
            return $this->createdBy->name;
        }

        return '-';
    }

    protected function getParentTitleAttribute()
    {
        $parent = $this->parent;

        return $parent ? $parent->title : '-';
    }

    protected function getCreatedAtFormattedAttribute()
    {
        return $this->created_at ? app_date_time_format($this->created_at, 'datetime') : '-';
    }

    protected function getUpdatedAtFormattedAttribute()
    {
        return $this->updated_at ? app_date_time_format($this->updated_at, 'datetime') : '-';
    }

    protected function getPublishedAtFormattedAttribute()
    {
        return $this->published_at ? app_date_time_format($this->published_at, 'datetime') : '-';
    }

    protected function getPublishedDateContextAttribute(): array
    {
        $status = $this->status ?? 'draft';

        $label = match ($status) {
            'published' => __('Published'),
            'scheduled' => __('Scheduled'),
            default => __('Last Modified'),
        };

        $timestamp = match ($status) {
            'published' => $this->published_at,
            'scheduled' => $this->published_at,
            default => $this->updated_at ?? $this->created_at,
        };

        if (! $timestamp instanceof DateTimeInterface) {
            $timestamp = $this->created_at;
        }

        if (! $timestamp instanceof DateTimeInterface) {
            return [
                'label' => $label,
                'display' => null,
                'timestamp' => null,
            ];
        }

        $dateDisplay = app_date_time_format($timestamp, 'date');
        $timeDisplay = app_date_time_format($timestamp, 'time');

        return [
            'label' => $label,
            'display' => trim($dateDisplay.' at '.$timeDisplay),
            'timestamp' => $timestamp->toIso8601String(),
        ];
    }

    protected function getPublishedDateAttribute()
    {
        return $this->published_at ? app_date_time_format($this->published_at, 'date') : '-';
    }

    protected function getReadingTimeAttribute(): string
    {
        if ($this->reading_seconds) {
            return $this->reading_seconds.' seconds';
        }

        if ($this->content) {
            $wordCount = str_word_count(strip_tags($this->content));
            $readingTime = max(1, (int) ceil($wordCount / 200)); // Average reading speed

            return $readingTime.' min read';
        }

        return '-';
    }

    protected function getCategoryPostCountAttribute()
    {
        // Check if already loaded via withCount
        if (array_key_exists('category_post_count', $this->attributes)) {
            return $this->attributes['category_post_count'];
        }

        // Also check posts_count (used by CategoryService: withCount('termPosts as posts_count'))
        if ($this->type === 'category' && array_key_exists('posts_count', $this->attributes)) {
            return $this->attributes['posts_count'];
        }

        return $this->termPosts()->where('term_type', 'category')->count();
    }

    protected function getTagPostCountAttribute()
    {
        // Check if already loaded via withCount
        if (array_key_exists('tag_post_count', $this->attributes)) {
            return $this->attributes['tag_post_count'];
        }

        // Also check posts_count (used by TagService: withCount('termPosts as posts_count'))
        if ($this->type === 'tag' && array_key_exists('posts_count', $this->attributes)) {
            return $this->attributes['posts_count'];
        }

        return $this->termPosts()->where('term_type', 'tag')->count();
    }

    // Alias for post count - returns correct count based on type (used in datagrids and forms)
    protected function getPostsCountAttribute()
    {
        // Check if already loaded via withCount (e.g., withCount('termPosts as posts_count'))
        if (array_key_exists('posts_count', $this->attributes)) {
            return $this->attributes['posts_count'];
        }

        return match ($this->type) {
            'category' => $this->category_post_count,
            'tag' => $this->tag_post_count,
            default => 0,
        };
    }

    // Alias for post count badge - returns correct badge based on type (used in datagrids)
    protected function getPostsCountBadgeAttribute()
    {
        return match ($this->type) {
            'category' => $this->category_post_count_badge,
            'tag' => $this->tag_post_count_badge,
            default => '<span class="badge bg-info">0</span>',
        };
    }

    protected function getRevisionsCountAttribute()
    {
        return $this->revisionHistory->count();
    }

    protected function getPermalinkUrlAttribute(): string
    {
        return resolve(PermaLinkService::class)->generatePostPermalink($this);
    }

    /**
     * Get featured image URL with specified conversion
     */
    private function getFeaturedImageByConversion(?string $conversion = null): string
    {
        if (! $this->featuredImage) {
            return get_placeholder_image_url();
        }

        // Use helper with intelligent fallback
        return get_media_url($this->featuredImage, $conversion ?: 'optimized');
    }
}
