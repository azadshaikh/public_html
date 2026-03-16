<?php

declare(strict_types=1);

namespace Modules\CMS\Http\Resources;

use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldResource;
use Modules\CMS\Definitions\TagDefinition;

class TagResource extends ScaffoldResource
{
    protected function definition(): ScaffoldDefinition
    {
        return new TagDefinition;
    }

    protected function customFields(): array
    {
        $isTrashed = $this->resource->deleted_at !== null;

        $createdAtFormatted = $this->resource->created_at
            ? app_date_time_format($this->resource->created_at, 'datetime')
            : null;

        $updatedAtFormatted = app_date_time_format($this->resource->updated_at, 'datetime');

        $displayDate = $this->resource->status === 'published' && $this->resource->published_at
            ? app_date_time_format($this->resource->published_at, 'datetime')
            : $updatedAtFormatted;

        return [
            'edit_url' => route($this->scaffold()->getRoutePrefix().'.edit', $this->resource->getKey()),
            'permalink_url' => $this->resource->permalink_url,

            // Data for title template
            'title' => $this->resource->title,
            'title_with_meta' => $this->resource->title,
            'slug' => $this->resource->slug,

            // Posts count - already loaded via withCount in TagService
            'posts_count' => $this->resource->posts_count ?? 0,

            // Dates for conditional display
            'created_at' => $createdAtFormatted,
            'updated_at_formatted' => $updatedAtFormatted,
            'display_date' => $displayDate,

            // Badge renderer expects status_label + status_class
            'status_label' => $isTrashed
                ? 'Trash'
                : ucfirst(str_replace('_', ' ', (string) $this->resource->status)),
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
            default => 'bg-secondary-subtle text-secondary',
        };
    }
}
