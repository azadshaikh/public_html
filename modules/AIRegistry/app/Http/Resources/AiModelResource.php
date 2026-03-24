<?php

declare(strict_types=1);

namespace Modules\AIRegistry\Http\Resources;

use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldResource;
use Modules\AIRegistry\Definitions\AiModelDefinition;
use Modules\AIRegistry\Models\AiModel;

/** @mixin AiModel */
class AiModelResource extends ScaffoldResource
{
    protected function definition(): ScaffoldDefinition
    {
        return new AiModelDefinition;
    }

    protected function customFields(): array
    {
        $capabilities = $this->capabilities ?? [];
        $categories = $this->categories ?? [];

        return [
            'edit_url' => route('ai-registry.models.edit', $this->id),
            'show_url' => route('ai-registry.models.edit', $this->id),
            'provider_name' => $this->provider?->name ?? '—',
            'provider_name_label' => $this->provider?->name ?? '—',
            'provider_name_class' => 'bg-primary-subtle text-primary',
            'slug' => $this->slug,
            'name' => $this->name,
            'context_window_formatted' => $this->getFormattedContextWindow(),
            'input_cost_per_1m' => $this->input_cost_per_1m !== null
                ? '$'.number_format((float) $this->input_cost_per_1m, 4)
                : '—',
            'output_cost_per_1m' => $this->output_cost_per_1m !== null
                ? '$'.number_format((float) $this->output_cost_per_1m, 4)
                : '—',
            'capabilities' => collect($capabilities)->map(fn (string $c) => [
                'value' => $c,
                'label' => ucwords(str_replace('_', ' ', $c)),
                'class' => 'bg-info-subtle text-info',
            ])->values()->all(),
            'categories' => collect($categories)->map(fn (string $c) => [
                'value' => $c,
                'label' => ucfirst($c),
                'class' => 'bg-secondary-subtle text-secondary',
            ])->values()->all(),
            'is_moderated' => $this->is_moderated,
            'is_moderated_label' => $this->is_moderated === null ? '—' : ($this->is_moderated ? 'Yes' : 'No'),
            'is_moderated_class' => $this->is_moderated === null
                ? 'bg-secondary-subtle text-secondary'
                : ($this->is_moderated ? 'bg-warning-subtle text-warning' : 'bg-success-subtle text-success'),
            'is_active' => $this->is_active,
            'is_active_label' => $this->is_active ? 'Active' : 'Inactive',
            'is_active_class' => $this->is_active
                ? 'bg-success-subtle text-success'
                : 'bg-secondary-subtle text-secondary',
        ];
    }
}
