<?php

namespace Modules\CMS\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * MenuItem Model - Backward Compatibility Facade
 *
 * This class provides backward compatibility for code that still references MenuItem.
 * It extends Menu and applies a global scope to only return non-container items.
 *
 * @deprecated Use Menu model directly with ->items() scope for new code.
 * @see Menu
 */
class MenuItem extends Menu
{
    use HasFactory;

    /**
     * Create a new menu item under a container
     *
     * @deprecated Use Menu::create() with parent_id set to the container's ID
     */
    public static function createForMenu(int $menuId, array $attributes): self
    {
        $attributes['parent_id'] = $menuId;
        $attributes['type'] ??= Menu::TYPE_CUSTOM;

        // Set title from name if not provided
        if (empty($attributes['title']) && ! empty($attributes['name'])) {
            $attributes['title'] = $attributes['name'];
        }

        if (empty($attributes['name']) && ! empty($attributes['title'])) {
            $attributes['name'] = $attributes['title'];
        }

        return static::query()->create($attributes);
    }

    /**
     * Get the menu (container) this item belongs to
     *
     * @deprecated Use parent() relationship on Menu model
     */
    public function menu()
    {
        // Find the container by traversing up the hierarchy
        $parent = $this->parent;
        while ($parent && ! $parent->isContainer()) {
            $parent = $parent->parent;
        }

        return $parent;
    }

    /**
     * Get available menu item types
     *
     * @deprecated Use Menu::getAvailableTypes()
     */
    public static function getAvailableTypes(): array
    {
        return Menu::getAvailableTypes();
    }

    /**
     * Get available targets
     *
     * @deprecated Use Menu::getAvailableTargets()
     */
    public static function getAvailableTargets(): array
    {
        return Menu::getAvailableTargets();
    }

    /**
     * The "booted" method of the model.
     * Apply a global scope to only get menu items (not containers)
     */
    protected static function booted(): void
    {
        static::addGlobalScope('items_only', function (Builder $builder): void {
            $builder->where('type', '!=', Menu::TYPE_CONTAINER);
        });
    }

    /**
     * Backward compatible menu_id accessor
     *
     * @deprecated Use parent_id directly
     */
    protected function getMenuIdAttribute(): ?int
    {
        // Find the container ID by traversing up
        $current = $this;
        while ($current->parent_id) {
            $parent = Menu::query()->find($current->parent_id);
            if (! $parent) {
                break;
            }

            if ($parent->isContainer()) {
                return $parent->id;
            }

            $current = $parent;
        }

        return $this->parent_id;
    }
}
