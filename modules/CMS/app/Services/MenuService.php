<?php

declare(strict_types=1);

namespace Modules\CMS\Services;

use App\Contracts\ScaffoldServiceInterface;
use App\Scaffold\ScaffoldDefinition;
use App\Traits\Scaffoldable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Modules\CMS\Definitions\MenuDefinition;
use Modules\CMS\Http\Resources\MenuResource;
use Modules\CMS\Models\Menu;

class MenuService implements ScaffoldServiceInterface
{
    use Scaffoldable;

    // ================================================================
    // REQUIRED SCAFFOLDABLE METHODS
    // ================================================================

    public function getScaffoldDefinition(): ScaffoldDefinition
    {
        return new MenuDefinition;
    }

    /**
     * Override statistics for is_active field
     */
    public function getStatistics(): array
    {
        $modelClass = $this->getScaffoldDefinition()->getModelClass();

        return [
            'total' => $modelClass::containers()->count(),
            'active' => $modelClass::containers()->where('is_active', true)->count(),
            'inactive' => $modelClass::containers()->where('is_active', false)->count(),
            'trash' => $modelClass::containers()->onlyTrashed()->count(),
        ];
    }

    // ================================================================
    // STATUS OPTIONS (for forms)
    // ================================================================

    public function getStatusOptions(): array
    {
        return [
            ['value' => '1', 'label' => 'Active'],
            ['value' => '0', 'label' => 'Inactive'],
        ];
    }

    // ================================================================
    // LOCATION HELPERS
    // ================================================================

    /**
     * Get available location options for forms
     */
    public function getLocationOptions(): array
    {
        $locations = Menu::getAvailableLocations();

        return collect($locations)->map(fn ($label, $value): array => [
            'value' => $value,
            'label' => $label,
        ])->values()->all();
    }

    protected function getResourceClass(): ?string
    {
        return MenuResource::class;
    }

    // ================================================================
    // EAGER LOADING
    // ================================================================

    protected function getEagerLoadRelationships(): array
    {
        return [];
    }

    // ================================================================
    // QUERY CUSTOMIZATION
    // ================================================================

    protected function customizeListQuery(Builder $query, Request $request): void
    {
        // Only get containers (menu definitions, not items)
        // @phpstan-ignore-next-line method.notFound
        $query->containers();

        // Add items count
        $query->withCount('allItems');
    }

    /**
     * Override buildListQuery to handle is_active filtering instead of status
     */
    protected function buildListQuery(Request $request): Builder
    {
        $definition = $this->getScaffoldDefinition();
        $modelClass = $definition->getModelClass();
        $status = $request->route('status') ?? $request->input('status', 'all');

        // Start with base query
        $query = $modelClass::query();

        // Apply status-based filtering (using is_active boolean)
        $query = match ($status) {
            'active' => $query->where('is_active', true),
            'inactive' => $query->where('is_active', false),
            'trash' => $query->onlyTrashed(),
            default => $query->withoutTrashed(), // 'all' excludes trash
        };

        // Apply eager loading
        $eagerLoad = $this->getEagerLoadRelationships();
        if ($eagerLoad !== []) {
            $query->with($eagerLoad);
        }

        // Apply hook for customization
        $this->customizeListQuery($query, $request);

        // Apply search
        $this->applySearch($query, $request);

        // Apply filters
        $this->applyFilters($query, $request);

        // Apply sorting
        $this->applySorting($query, $request);

        return $query;
    }

    // ================================================================
    // DATA PREPARATION
    // ================================================================

    protected function prepareCreateData(array $data): array
    {
        return [
            'type' => Menu::TYPE_CONTAINER,
            'name' => $data['name'],
            'slug' => $data['slug'] ?? null,
            // Location uses empty string when not provided (DB column does not allow NULL)
            'location' => empty($data['location']) ? '' : $data['location'],
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ];
    }

    protected function prepareUpdateData(array $data): array
    {
        return $this->prepareCreateData($data);
    }

    // ================================================================
    // SEARCH CONFIGURATION
    // ================================================================

    protected function getSearchableColumns(): array
    {
        return ['name', 'description', 'location'];
    }

    // ================================================================
    // SORTING CONFIGURATION
    // ================================================================

    protected function getSortableColumnMap(): array
    {
        return [
            'name' => 'name',
            'location' => 'location',
            'items_count' => 'all_items_count',
            'updated_at' => 'updated_at',
            'created_at' => 'created_at',
        ];
    }
}
