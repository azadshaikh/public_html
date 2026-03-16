<?php

declare(strict_types=1);

namespace Modules\CMS\Http\Resources;

use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldResource;
use Modules\CMS\Definitions\FormDefinition;

class FormResource extends ScaffoldResource
{
    protected function definition(): ScaffoldDefinition
    {
        return new FormDefinition;
    }

    protected function customFields(): array
    {
        $isTrashed = $this->resource->deleted_at !== null;
        $status = (string) ($this->resource->status ?? 'draft');

        $statusLabel = $isTrashed
            ? 'Trash'
            : ($this->resource->status_label ?? ucfirst($status));

        $statusClass = $isTrashed
            ? 'bg-danger-subtle text-danger'
            : match ($status) {
                'published' => 'bg-success-subtle text-success',
                'draft' => 'bg-warning-subtle text-warning',
                default => 'bg-secondary-subtle text-secondary',
            };

        $template = (string) ($this->resource->template ?? 'default');
        $templateConfig = config('cms.forms.templates.'.$template, []);
        $templateLabel = $templateConfig['label'] ?? ucfirst(str_replace('_', ' ', $template));
        $templateClass = $this->mapTemplateClass($template);

        $isActive = (bool) ($this->resource->is_active ?? false);

        return [
            'show_url' => route($this->scaffold()->getRoutePrefix().'.show', $this->resource->getKey()),

            'created_at' => $this->resource->created_at
                ? app_date_time_format($this->resource->created_at, 'date')
                : '--',

            'status_label' => $statusLabel,
            'status_class' => $statusClass,

            'template_label' => $templateLabel,
            'template_class' => $templateClass,

            'is_active_label' => $isActive ? 'Active' : 'Inactive',
            'is_active_class' => $isActive
                ? 'bg-success-subtle text-success'
                : 'bg-danger-subtle text-danger',

            'conversion_rate_display' => $this->formatConversionRate(),
        ];
    }

    private function mapTemplateClass(string $template): string
    {
        $colors = [
            'default' => 'secondary',
            'contact' => 'primary',
            'newsletter' => 'info',
            'registration' => 'success',
            'feedback' => 'warning',
            'survey' => 'info',
            'quote' => 'primary',
            'booking' => 'success',
            'payment' => 'danger',
        ];

        $color = $colors[$template] ?? 'secondary';

        return sprintf('bg-%s-subtle text-%s', $color, $color);
    }

    private function formatConversionRate(): string
    {
        $rate = $this->resource->conversion_rate ?? null;
        if ($rate === null) {
            return '--';
        }

        return rtrim(rtrim(number_format((float) $rate, 1), '0'), '.').'%';
    }
}
