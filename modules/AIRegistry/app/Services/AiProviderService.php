<?php

declare(strict_types=1);

namespace Modules\AIRegistry\Services;

use App\Contracts\ScaffoldServiceInterface;
use App\Scaffold\ScaffoldDefinition;
use App\Traits\Scaffoldable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Modules\AIRegistry\Definitions\AiProviderDefinition;
use Modules\AIRegistry\Http\Resources\AiProviderResource;
use Modules\AIRegistry\Models\AiProvider;

class AiProviderService implements ScaffoldServiceInterface
{
    use Scaffoldable;

    // ================================================================
    // REQUIRED METHODS
    // ================================================================

    public function getScaffoldDefinition(): ScaffoldDefinition
    {
        return new AiProviderDefinition;
    }

    protected function getResourceClass(): ?string
    {
        return AiProviderResource::class;
    }

    // ================================================================
    // STATISTICS (for tab counts)
    // ================================================================

    public function getStatistics(): array
    {
        return [
            'total' => AiProvider::query()->whereNull('deleted_at')->count(),
            'active' => AiProvider::query()->where('is_active', true)->whereNull('deleted_at')->count(),
            'inactive' => AiProvider::query()->where('is_active', false)->whereNull('deleted_at')->count(),
            'trash' => AiProvider::onlyTrashed()->count(),
        ];
    }

    // ================================================================
    // EAGER LOADING
    // ================================================================

    protected function getEagerLoadRelationships(): array
    {
        return [
            'createdBy:id,first_name,last_name',
            'updatedBy:id,first_name,last_name',
        ];
    }

    protected function customizeListQuery(Builder $query, Request $request): void
    {
        $query->withCount('models');
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
    // DATA PREPARATION
    // ================================================================

    protected function prepareCreateData(array $data): array
    {
        $data['is_active'] ??= true;
        $data['capabilities'] = $data['capabilities'] ?? [];

        return $data;
    }

    protected function prepareUpdateData(array $data): array
    {
        $data['capabilities'] = $data['capabilities'] ?? [];

        return $data;
    }

    // ================================================================
    // OPTIONS FOR FORMS
    // ================================================================

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function getCapabilityOptions(): array
    {
        return collect(config('airegistry::options.capabilities', []))
            ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function getFormProviderOptions(?int $selectedProviderId = null): array
    {
        return AiProvider::query()
            ->when(
                $selectedProviderId !== null,
                fn (Builder $query) => $query->where(function (Builder $nestedQuery) use ($selectedProviderId): void {
                    $nestedQuery
                        ->where('is_active', true)
                        ->orWhere('id', $selectedProviderId);
                }),
                fn (Builder $query) => $query->where('is_active', true)
            )
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (AiProvider $provider) => ['value' => (string) $provider->id, 'label' => $provider->name])
            ->values()
            ->all();
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
