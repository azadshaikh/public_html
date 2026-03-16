<?php

declare(strict_types=1);

namespace Modules\CMS\Http\Resources;

use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldResource;
use Modules\CMS\Definitions\PageDefinition;

class PageResource extends ScaffoldResource
{
    protected function definition(): ScaffoldDefinition
    {
        return new PageDefinition;
    }

    protected function customFields(): array
    {
        $isTrashed = $this->resource->deleted_at !== null;

        $publishedAtIso = $this->resource->published_at
            ? $this->resource->published_at->toISOString()
            : null;

        $publishedAtFormatted = $this->resource->published_at
            ? app_date_time_format($this->resource->published_at, 'datetime')
            : null;

        $updatedAtFormatted = app_date_time_format($this->resource->updated_at, 'datetime');

        $displayDate = $this->resource->status === 'published' && $publishedAtFormatted
            ? $publishedAtFormatted
            : $updatedAtFormatted;

        // Parent page info
        $parentName = $this->resource->parent->title ?? null;

        return [
            'edit_url' => route($this->scaffold()->getRoutePrefix().'.edit', $this->resource->getKey()),
            'permalink_url' => $this->resource->permalink_url,

            // Data for title template
            'title' => $this->resource->title,
            'title_with_meta' => $this->resource->title,
            'author_name' => $this->resource->author_name,
            'featured_image_url' => $this->resource->featuredImage
                ? get_media_url($this->resource->featuredImage, 'thumbnail', usePlaceholder: false)
                : null,

            // Parent info for display
            'parent_name' => $parentName,
            'parent_display' => $parentName ?? '—',

            // Dates for conditional display
            'published_at' => $publishedAtIso,
            'published_at_formatted' => $publishedAtFormatted,
            'updated_at_formatted' => $updatedAtFormatted,
            'display_date' => $displayDate,

            // Badge renderer expects status_label + status_class
            'status_label' => $isTrashed
                ? 'Trash'
                : (config('cms.post_status.'.$this->resource->status.'.label', ucfirst(str_replace('_', ' ', (string) $this->resource->status)))),

            'status_class' => $isTrashed
                ? 'bg-danger-subtle text-danger'
                : $this->mapStatusToClass((string) $this->resource->status),
        ];
    }

    private function mapStatusToClass(string $status): string
    {
        $color = config('cms.post_status.'.$status.'.color');

        if (is_string($color) && $color !== '') {
            return sprintf('bg-%s-subtle text-%s', $color, $color);
        }

        return match ($status) {
            'published' => 'bg-success-subtle text-success',
            'draft' => 'bg-warning-subtle text-warning',
            'scheduled' => 'bg-info-subtle text-info',
            'pending_review' => 'bg-warning-subtle text-warning',
            default => 'bg-secondary-subtle text-secondary',
        };
    }
}
