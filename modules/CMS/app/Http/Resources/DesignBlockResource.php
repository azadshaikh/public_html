<?php

declare(strict_types=1);

namespace Modules\CMS\Http\Resources;

use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldResource;
use Modules\CMS\Definitions\DesignBlockDefinition;

class DesignBlockResource extends ScaffoldResource
{
    protected function definition(): ScaffoldDefinition
    {
        return new DesignBlockDefinition;
    }

    protected function customFields(): array
    {
        $isTrashed = $this->resource->deleted_at !== null;
        $status = (string) ($this->resource->status ?? 'draft');

        $designType = (string) ($this->resource->design_type ?? '');
        $blockType = (string) ($this->resource->block_type ?? '');

        $previewImageUrl = $this->resource->getPreviewImageUrl()
            ?? get_placeholder_image_url();

        return [
            'edit_url' => route($this->scaffold()->getRoutePrefix().'.edit', $this->resource->getKey()),

            'preview_image_url' => $previewImageUrl,

            'design_type' => $designType,
            'design_type_label' => $this->mapConfigLabel('cms.design_types', $designType),
            'design_type_class' => $this->mapDesignTypeClass($designType),

            'block_type' => $blockType,
            'block_type_label' => $this->mapConfigLabel('cms.block_types', $blockType),
            'block_type_class' => $this->mapBlockTypeClass($blockType),

            'category_id' => $this->resource->category_id,
            'category_name' => $this->resource->category_name ?? 'Uncategorized',

            'status_label' => $isTrashed
                ? 'Trash'
                : ($this->resource->status_label ?? ucfirst(str_replace('_', ' ', $status))),
            'status_class' => $isTrashed
                ? 'bg-danger-subtle text-danger'
                : $this->mapStatusClass($status),

            'created_at' => $this->resource->created_at
                ? app_date_time_format($this->resource->created_at, 'date')
                : '--',
        ];
    }

    private function mapConfigLabel(string $configKey, string $value): string
    {
        $config = config($configKey, []);
        $label = $config[$value]['label'] ?? null;

        if ($label) {
            return $label;
        }

        return $value !== '' ? ucfirst(str_replace('_', ' ', $value)) : '—';
    }

    private function mapStatusClass(string $status): string
    {
        return match ($status) {
            'published' => 'bg-success-subtle text-success',
            'draft' => 'bg-warning-subtle text-warning',
            default => 'bg-secondary-subtle text-secondary',
        };
    }

    private function mapDesignTypeClass(string $designType): string
    {
        return match ($designType) {
            'section' => 'bg-primary-subtle text-primary',
            'block' => 'bg-info-subtle text-info',
            'component' => 'bg-success-subtle text-success',
            default => 'bg-secondary-subtle text-secondary',
        };
    }

    private function mapBlockTypeClass(string $blockType): string
    {
        return match ($blockType) {
            'static' => 'bg-secondary-subtle text-secondary',
            'dynamic' => 'bg-warning-subtle text-warning',
            'interactive' => 'bg-info-subtle text-info',
            default => 'bg-secondary-subtle text-secondary',
        };
    }
}
