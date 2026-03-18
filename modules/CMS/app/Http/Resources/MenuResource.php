<?php

declare(strict_types=1);

namespace Modules\CMS\Http\Resources;

use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldResource;
use Illuminate\Support\Str;
use Modules\CMS\Definitions\MenuDefinition;
use Modules\CMS\Models\Menu;

/**
 * MenuResource - ScaffoldResource for Menu containers
 *
 * Model attributes are auto-included via getBaseAttributes().
 * Custom computed fields and badge classes defined here.
 *
 * @mixin Menu
 */
class MenuResource extends ScaffoldResource
{
    protected function definition(): ScaffoldDefinition
    {
        return new MenuDefinition;
    }

    protected function baseAttributeKeys(): ?array
    {
        return [
            'name',
            'description',
            'location',
            'items_count',
            'all_items_count',
            'is_active',
            'updated_at',
        ];
    }

    /**
     * Custom/computed fields for Menu
     */
    protected function customFields(): array
    {
        $itemsCount = $this->all_items_count ?? $this->items_count ?? $this->allItems->count();

        return [
            // Location display
            'location_label' => $this->getLocationLabel(),

            // Status based on is_active boolean
            'is_active' => (bool) $this->is_active,
            'is_active_label' => $this->is_active ? 'Active' : 'Inactive',
            'is_active_class' => $this->is_active
                ? 'bg-success-subtle text-success'
                : 'bg-secondary-subtle text-secondary',

            // Items count with badge styling
            'items_count' => $itemsCount,
            'items_count_label' => (string) $itemsCount,
            'items_count_class' => $itemsCount > 0
                ? 'bg-primary-subtle text-primary'
                : 'bg-secondary-subtle text-secondary',

            // Items preview for custom templates
            'items_preview' => $this->getItemsPreview(),

            // Date formatting
            'updated_at' => app_date_time_format($this->updated_at, 'date'),
            'created_at' => app_date_time_format($this->created_at, 'date'),
            'updated_at_for_humans' => optional($this->updated_at)?->diffForHumans(),
            'created_at_for_humans' => optional($this->created_at)?->diffForHumans(),

            // URLs for custom templates
            'edit_url' => route('cms.appearance.menus.edit', $this->resource),
            'show_url' => route('cms.appearance.menus.edit', $this->resource), // Menu uses edit as show
        ];
    }

    /**
     * Get location label from theme config
     */
    protected function getLocationLabel(): ?string
    {
        if (! $this->location) {
            return null;
        }

        $locations = Menu::getAvailableLocations();

        return $locations[$this->location] ?? Str::headline(str_replace('_', ' ', $this->location));
    }

    /**
     * Get preview of menu items for display
     */
    protected function getItemsPreview(): array
    {
        if (! $this->relationLoaded('allItems')) {
            return [];
        }

        return $this->allItems
            ->sortBy('sort_order')
            ->take(3)
            ->map(fn ($item): array => [
                'id' => $item->id,
                'title' => $item->title ?: ($item->page->title ?? 'Untitled Item'),
                'type' => $item->type,
            ])
            ->values()
            ->all();
    }
}
