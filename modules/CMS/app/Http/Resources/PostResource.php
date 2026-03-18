<?php

declare(strict_types=1);

namespace Modules\CMS\Http\Resources;

use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldResource;
use Modules\CMS\Definitions\PostDefinition;

class PostResource extends ScaffoldResource
{
    protected function definition(): ScaffoldDefinition
    {
        return new PostDefinition;
    }

    protected function baseAttributeKeys(): ?array
    {
        return [
            'title',
            'status',
            'is_featured',
        ];
    }

    protected function includesFormattedDates(): bool
    {
        return false;
    }

    protected function includesEnumFields(): bool
    {
        return false;
    }

    protected function includesStatusFields(): bool
    {
        return false;
    }

    protected function includesAuditFields(): bool
    {
        return false;
    }

    protected function includesDetailedSoftDeleteFields(): bool
    {
        return false;
    }

    protected function includesActions(): bool
    {
        return false;
    }

    protected function customFields(): array
    {
        $isTrashed = $this->resource->deleted_at !== null;

        $categories = $this->resource->categories
            ->map(fn ($cat): array => [
                'id' => $cat->id,
                'title' => $cat->title,
            ])
            ->toArray();

        $publishedAtFormatted = $this->resource->published_at
            ? app_date_time_format($this->resource->published_at, 'datetime')
            : null;

        $displayDate = $this->resource->status === 'published' && $publishedAtFormatted
            ? $publishedAtFormatted
            : app_date_time_format($this->resource->updated_at, 'datetime');

        return [
            'edit_url' => route($this->scaffold()->getRoutePrefix().'.edit', $this->resource->getKey()),
            'permalink_url' => $this->resource->permalink_url,

            'is_featured' => (bool) $this->resource->is_featured,
            'author_name' => $this->resource->author_name,
            'featured_image_url' => $this->resource->featuredImage
                ? get_media_url($this->resource->featuredImage, 'thumbnail', usePlaceholder: false)
                : null,

            'categories' => $categories,
            'published_at_formatted' => $publishedAtFormatted,
            'display_date' => $displayDate,

            'status_label' => $isTrashed
                ? 'Trash'
                : (config('cms.post_status.'.$this->resource->status.'.label', ucfirst(str_replace('_', ' ', (string) $this->resource->status)))),
        ];
    }
}
