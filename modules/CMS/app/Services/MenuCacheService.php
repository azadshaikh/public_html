<?php

namespace Modules\CMS\Services;

use App\Support\Cache\AbstractCacheService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Modules\CMS\Models\CmsPost;
use Modules\CMS\Models\Menu;

/**
 * Cache service for CMS menus.
 *
 * Caches menu containers and items for fast frontend rendering.
 * Automatically invalidated via MenuObserver when menus are modified.
 *
 * Uses two-tier caching (memory + persistent) for all cache keys.
 */
class MenuCacheService extends AbstractCacheService
{
    /**
     * Cache key prefix for menus by location
     */
    public const LOCATION_PREFIX = 'menu_';

    /**
     * Cache key prefix for menu items
     */
    public const ITEMS_PREFIX = 'menu_items_';

    /**
     * Cache key for all menu containers
     */
    public const CONTAINERS_KEY = 'menu_containers';

    /**
     * Get a menu by its location.
     *
     * Eager loads items with their children and page relationships for frontend rendering.
     */
    public function getByLocation(string $location): ?Menu
    {
        $cacheKey = self::LOCATION_PREFIX.$location;

        $cached = $this->remember($cacheKey, function () use ($location) {
            $menu = Menu::query()
                ->where('type', Menu::TYPE_CONTAINER)
                ->where('location', $location)
                ->where('is_active', true)
                ->with(['items.children.page', 'items.page'])
                ->first();

            // Cache a false sentinel so missing menus don't trigger a cache miss every request.
            return $menu ? $this->serializeMenu($menu) : false;
        });

        if ($cached === false) {
            return null;
        }

        if (is_array($cached)) {
            return $this->hydrateMenu($cached);
        }

        $this->forget($cacheKey);

        return Menu::query()
            ->where('type', Menu::TYPE_CONTAINER)
            ->where('location', $location)
            ->where('is_active', true)
            ->with(['items.children.page', 'items.page'])
            ->first();
    }

    /**
     * Get menu items for a specific menu container.
     *
     * Eager loads children and page relationships for hierarchical rendering.
     */
    public function getMenuItems(int $menuId): Collection
    {
        $cacheKey = self::ITEMS_PREFIX.$menuId;

        $cached = $this->remember($cacheKey, fn (): array => $this->serializeMenus(Menu::query()
            ->where('parent_id', $menuId)
            ->where('is_active', true)
            ->with(['children', 'page'])
            ->orderBy('sort_order')
            ->get()));

        if (is_array($cached)) {
            return $this->hydrateMenus($cached);
        }

        $this->forget($cacheKey);

        return Menu::query()
            ->where('parent_id', $menuId)
            ->where('is_active', true)
            ->with(['children', 'page'])
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Get hierarchical menu structure for a location.
     * Returns nested array suitable for frontend rendering.
     */
    public function getHierarchicalMenu(string $location): ?array
    {
        $menu = $this->getByLocation($location);

        if (! $menu instanceof Menu) {
            return null;
        }

        // Use the eager-loaded children tree from the menu items.
        return $this->buildHierarchy($menu->items);
    }

    /**
     * Invalidate cache for a specific menu location.
     */
    public function invalidateLocation(string $location): void
    {
        $this->forget(self::LOCATION_PREFIX.$location);
    }

    /**
     * Invalidate cache for a specific menu's items.
     */
    public function invalidateMenuItems(int $menuId): void
    {
        $this->forget(self::ITEMS_PREFIX.$menuId);
    }

    /**
     * Invalidate all menu caches.
     */
    public function invalidate(?string $reason = null): void
    {
        // Clear main cache
        parent::invalidate($reason);

        // Clear all location caches
        $locations = Menu::getAvailableLocations();
        foreach (array_keys($locations) as $location) {
            $this->forget(self::LOCATION_PREFIX.$location);
        }

        // Clear all menu items caches - get container IDs
        $containerIds = Menu::query()->where('type', Menu::TYPE_CONTAINER)->pluck('id');
        foreach ($containerIds as $id) {
            $this->forget(self::ITEMS_PREFIX.$id);
        }
    }

    /**
     * Invalidate caches related to a specific menu.
     */
    public function invalidateMenu(Menu $menu, ?string $originalLocation = null): void
    {
        // Invalidate main containers cache
        parent::invalidate('Menu changed: '.$menu->name);

        // Invalidate current location
        if ($menu->location) {
            $this->invalidateLocation($menu->location);
        }

        // Invalidate original location if it changed
        if ($originalLocation && $originalLocation !== $menu->location) {
            $this->invalidateLocation($originalLocation);
        }

        // Invalidate menu items
        if ($menu->isContainer()) {
            $this->invalidateMenuItems($menu->id);
        } elseif ($menu->parent_id) {
            // If it's a menu item, invalidate the parent container's items
            $this->invalidateMenuItems($menu->parent_id);
        }
    }

    protected function getCacheKey(): string
    {
        return self::CONTAINERS_KEY;
    }

    protected function getCacheTtl(): ?int
    {
        return null; // Cache forever - invalidated when menus change
    }

    /**
     * Load all menu containers from database
     */
    protected function loadFromSource(): mixed
    {
        return Menu::query()
            ->where('type', Menu::TYPE_CONTAINER)
            ->with(['items' => function ($query): void {
                $query->where('is_active', true)
                    ->orderBy('sort_order');
            },
            ])
            ->get();
    }

    /**
     * Build hierarchical structure from a tree of items.
     */
    protected function buildHierarchy(Collection $items): array
    {
        $result = [];

        foreach ($items as $item) {
            $itemArray = $item->toArray();
            $itemArray['children'] = $this->buildHierarchy($item->children ?? collect());
            $result[] = $itemArray;
        }

        return $result;
    }

    private function serializeMenu(Menu $menu): array
    {
        $payload = $menu->getAttributes();

        if ($menu->relationLoaded('children')) {
            $payload['children'] = $this->serializeMenus($menu->getRelation('children'));
        }

        if ($menu->relationLoaded('items')) {
            $payload['items'] = $this->serializeMenus($menu->getRelation('items'));
        }

        if ($menu->relationLoaded('page') && $menu->getRelation('page') instanceof CmsPost) {
            $payload['page'] = $menu->getRelation('page')->getAttributes();
        }

        return $payload;
    }

    private function serializeMenus(Collection $menus): array
    {
        return $menus->map(fn (Menu $menu): array => $this->serializeMenu($menu))->all();
    }

    private function hydrateMenus(array $payload): EloquentCollection
    {
        return new EloquentCollection(collect($payload)
            ->filter(fn (mixed $menu): bool => is_array($menu))
            ->map(fn (array $menu): Menu => $this->hydrateMenu($menu))
            ->values()
            ->all());
    }

    private function hydrateMenu(array $payload): Menu
    {
        $relations = [];

        if (array_key_exists('children', $payload) && is_array($payload['children'])) {
            $relations['children'] = $this->hydrateMenus($payload['children']);
        }

        if (array_key_exists('items', $payload) && is_array($payload['items'])) {
            $relations['items'] = $this->hydrateMenus($payload['items']);
        }

        if (array_key_exists('page', $payload) && is_array($payload['page'])) {
            $relations['page'] = $this->hydrateCmsPost($payload['page']);
        }

        unset($payload['children'], $payload['items'], $payload['page']);

        /** @var Menu $menu */
        $menu = Menu::query()->newModelInstance();
        $menu->setRawAttributes($payload, true);
        $menu->exists = true;
        $menu->wasRecentlyCreated = false;

        foreach ($relations as $relation => $value) {
            $menu->setRelation($relation, $value);
        }

        return $menu;
    }

    private function hydrateCmsPost(array $payload): CmsPost
    {
        /** @var CmsPost $post */
        $post = CmsPost::hydrate([$payload])->firstOrFail();

        return $post;
    }
}
