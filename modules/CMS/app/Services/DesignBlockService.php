<?php

declare(strict_types=1);

namespace Modules\CMS\Services;

use App\Contracts\ScaffoldServiceInterface;
use App\Scaffold\ScaffoldDefinition;
use App\Traits\Scaffoldable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Modules\CMS\Definitions\DesignBlockDefinition;
use Modules\CMS\Http\Resources\DesignBlockResource;

class DesignBlockService implements ScaffoldServiceInterface
{
    use Scaffoldable;

    public function getScaffoldDefinition(): ScaffoldDefinition
    {
        return new DesignBlockDefinition;
    }

    public function getStatusOptions(): array
    {
        return [
            ['value' => 'draft', 'label' => 'Draft'],
            ['value' => 'published', 'label' => 'Published'],
        ];
    }

    public function getDesignTypeOptions(): array
    {
        return $this->mapConfigOptions('cms.design_types');
    }

    public function getBlockTypeOptions(): array
    {
        return $this->mapConfigOptions('cms.block_types');
    }

    public function getCategoryOptions(): array
    {
        return $this->mapConfigOptions('cms.categories');
    }

    public function getDesignSystemOptions(): array
    {
        return $this->mapConfigOptions('cms.design_systems');
    }

    protected function getResourceClass(): ?string
    {
        return DesignBlockResource::class;
    }

    protected function getEagerLoadRelationships(): array
    {
        return [
            'createdBy:id,first_name,last_name',
            'updatedBy:id,first_name,last_name',
        ];
    }

    protected function applyFilters(Builder $query, Request $request): void
    {
        if ($request->filled('design_type')) {
            $query->whereJsonContains('metadata->design_type', $request->input('design_type'));
        }

        if ($request->filled('category_id')) {
            $query->whereJsonContains('metadata->category', $request->input('category_id'));
        }

        if ($request->filled('design_system')) {
            $query->whereJsonContains('metadata->design_system', $request->input('design_system'));
        }

        if ($request->filled('created_at_from')) {
            $query->whereDate('created_at', '>=', $request->input('created_at_from'));
        }

        if ($request->filled('created_at_to')) {
            $query->whereDate('created_at', '<=', $request->input('created_at_to'));
        }
    }

    protected function prepareCreateData(array $data): array
    {
        $metadata = [
            'design_type' => $data['design_type'] ?? 'section',
            'block_type' => 'static',
            'design_system' => $data['design_system'] ?? 'bootstrap',
            'category' => $data['category_id'] ?? 'hero',
            'preview_image_url' => $data['preview_image_url'] ?? null,
        ];

        $payload = [
            'title' => $data['title'],
            'excerpt' => $data['description'] ?? null,
            'content' => $data['html'] ?? null,
            'status' => $data['status'] ?? 'draft',
            'metadata' => $metadata,
            'attributes' => $data['attributes'] ?? null,
        ];

        // Only persist optional fields if they were actually submitted.
        // This prevents wiping existing values when inputs are removed from the form.
        if (array_key_exists('slug', $data)) {
            $payload['slug'] = $data['slug'] ?: null;
        }

        if (array_key_exists('css', $data)) {
            $payload['css'] = $data['css'];
        }

        if (array_key_exists('scripts', $data)) {
            $payload['js'] = $data['scripts'];
        }

        return $payload;
    }

    protected function prepareUpdateData(array $data): array
    {
        return $this->prepareCreateData($data);
    }

    private function mapConfigOptions(string $configKey): array
    {
        return collect(config($configKey, []))
            ->map(function (array $item, string $key): array {
                $value = $item['value'] ?? $key;
                $label = $item['label'] ?? ucfirst(str_replace('_', ' ', (string) $value));

                return [
                    'value' => $value,
                    'label' => $label,
                ];
            })
            ->filter(fn (array $option): bool => $option['value'] !== '')
            ->values()
            ->all();
    }
}
