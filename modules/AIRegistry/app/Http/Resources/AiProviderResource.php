<?php

declare(strict_types=1);

namespace Modules\AIRegistry\Http\Resources;

use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldResource;
use Modules\AIRegistry\Definitions\AiProviderDefinition;
use Modules\AIRegistry\Models\AiProvider;

/** @mixin AiProvider */
class AiProviderResource extends ScaffoldResource
{
    protected function definition(): ScaffoldDefinition
    {
        return new AiProviderDefinition;
    }

    protected function customFields(): array
    {
        $capabilities = $this->capabilities ?? [];

        return [
            'edit_url' => route('ai-registry.providers.edit', $this->id),
            'show_url' => route('ai-registry.providers.edit', $this->id),
            'slug' => $this->slug,
            'name' => $this->name,
            'docs_url' => $this->docs_url,
            'api_key_url' => $this->api_key_url,
            'capabilities' => collect($capabilities)->map(fn (string $c) => [
                'value' => $c,
                'label' => ucwords(str_replace('_', ' ', $c)),
                'class' => 'bg-info-subtle text-info',
            ])->values()->all(),
            'models_count' => $this->models_count ?? 0,
            'is_active' => $this->is_active,
            'is_active_label' => $this->is_active ? 'Active' : 'Inactive',
            'is_active_class' => $this->is_active
                ? 'bg-success-subtle text-success'
                : 'bg-secondary-subtle text-secondary',
        ];
    }
}
