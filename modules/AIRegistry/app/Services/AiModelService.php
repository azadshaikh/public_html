<?php

declare(strict_types=1);

namespace Modules\AIRegistry\Services;

use App\Contracts\ScaffoldServiceInterface;
use App\Scaffold\ScaffoldDefinition;
use App\Traits\Scaffoldable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Modules\AIRegistry\Definitions\AiModelDefinition;
use Modules\AIRegistry\Http\Resources\AiModelResource;
use Modules\AIRegistry\Models\AiModel;
use Modules\AIRegistry\Models\AiProvider;

class AiModelService implements ScaffoldServiceInterface
{
    use Scaffoldable;

    // ================================================================
    // REQUIRED METHODS
    // ================================================================

    public function getScaffoldDefinition(): ScaffoldDefinition
    {
        return new AiModelDefinition;
    }

    public function getDataGridConfig(): array
    {
        $config = $this->scaffold()->toDataGridConfig();
        $config['filters'] = $this->getFiltersConfig();

        return $config;
    }

    protected function getResourceClass(): ?string
    {
        return AiModelResource::class;
    }

    // ================================================================
    // STATISTICS (for tab counts)
    // ================================================================

    public function getStatistics(): array
    {
        return [
            'total' => AiModel::query()->whereNull('deleted_at')->count(),
            'active' => AiModel::query()->where('is_active', true)->whereNull('deleted_at')->count(),
            'inactive' => AiModel::query()->where('is_active', false)->whereNull('deleted_at')->count(),
            'trash' => AiModel::onlyTrashed()->count(),
        ];
    }

    // ================================================================
    // EAGER LOADING
    // ================================================================

    protected function getEagerLoadRelationships(): array
    {
        return [
            'provider:id,name,slug',
            'createdBy:id,first_name,last_name',
            'updatedBy:id,first_name,last_name',
        ];
    }

    // ================================================================
    // CUSTOM STATUS TAB HANDLING (boolean is_active)
    // ================================================================

    protected function applyStatusFilter(Builder $query, Request $request): void
    {
        $status = $request->input('status') ?? $request->route('status') ?? 'all';

        match ($status) {
            'active' => $query->where('is_active', true),
            'inactive' => $query->where('is_active', false),
            default => null,
        };
    }

    // ================================================================
    // CUSTOM FILTER HANDLING
    // ================================================================

    protected function applyFilters(Builder $query, Request $request): void
    {
        if ($request->filled('provider_id')) {
            $query->where('provider_id', $request->input('provider_id'));
        }

        if ($request->filled('capability')) {
            $query->whereJsonContains('capabilities', $request->input('capability'));
        }

        if ($request->filled('category')) {
            $query->whereJsonContains('categories', $request->input('category'));
        }

        if ($request->filled('is_moderated')) {
            $query->where('is_moderated', $request->boolean('is_moderated'));
        }
    }

    // ================================================================
    // DATA PREPARATION
    // ================================================================

    protected function prepareCreateData(array $data): array
    {
        $data['is_active'] ??= true;
        $data['capabilities'] = $data['capabilities'] ?? [];
        $data['input_modalities'] = $data['input_modalities'] ?? [];
        $data['output_modalities'] = $data['output_modalities'] ?? [];
        $data['supported_parameters'] = isset($data['supported_parameters']) && is_string($data['supported_parameters'])
            ? array_filter(array_map('trim', explode(',', $data['supported_parameters'])))
            : ($data['supported_parameters'] ?? []);

        return $data;
    }

    protected function prepareUpdateData(array $data): array
    {
        return $this->prepareCreateData($data);
    }

    // ================================================================
    // OPTIONS FOR FORMS
    // ================================================================

    /**
     * Convert a config map (['value' => 'Label', ...]) to select options format.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function configToOptions(string $configKey): array
    {
        return collect(config($configKey, []))
            ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function getInputModalityOptions(): array
    {
        return $this->configToOptions('airegistry::options.input_modalities');
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function getOutputModalityOptions(): array
    {
        return $this->configToOptions('airegistry::options.output_modalities');
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function getCategoryOptions(): array
    {
        return $this->configToOptions('airegistry::options.categories');
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function getCapabilityOptions(): array
    {
        return $this->configToOptions('airegistry::options.capabilities');
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function getActiveProviderOptions(): array
    {
        return AiProvider::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (AiProvider $p) => ['value' => (string) $p->id, 'label' => $p->name])
            ->values()
            ->all();
    }

    protected function getFiltersConfig(): array
    {
        return collect($this->scaffold()->filters())
            ->map(function ($filter): array {
                if ($filter->key === 'provider_id') {
                    $filter->options($this->getActiveProviderOptions());
                }

                if ($filter->key === 'capability') {
                    $filter->options($this->getCapabilityOptions());
                }

                return $filter->toArray();
            })
            ->toArray();
    }

    // ================================================================
    // CACHE INVALIDATION HOOK
    // ================================================================

    public function invalidateApiCache(): void
    {
        cache()->forget('ai_registry.api.v1.models');
    }

    protected function afterCreate(mixed $model, array $data): void
    {
        $this->invalidateApiCache();
    }

    protected function afterUpdate(mixed $model, array $data): void
    {
        $this->invalidateApiCache();
    }

    protected function afterDelete(mixed $model): void
    {
        $this->invalidateApiCache();
    }

    protected function afterRestore(mixed $model): void
    {
        $this->invalidateApiCache();
    }

    protected function afterForceDelete(mixed $model): void
    {
        $this->invalidateApiCache();
    }
}
