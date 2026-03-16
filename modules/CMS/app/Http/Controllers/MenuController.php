<?php

declare(strict_types=1);

namespace Modules\CMS\Http\Controllers;

use App\Enums\ActivityAction;
use App\Jobs\RecacheApplication;
use App\Scaffold\ScaffoldController;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Modules\CMS\Definitions\MenuDefinition;
use Modules\CMS\Models\CmsPost;
use Modules\CMS\Models\Menu;
use Modules\CMS\Services\MenuService;

/**
 * MenuController - extends ScaffoldController for CRUD operations
 *
 * Custom overrides for:
 * - index: Adds theme location assignments data
 * - create/store: Simple form
 * - edit: Menu builder interface
 * - Custom actions: duplicate, saveAll
 */
class MenuController extends ScaffoldController implements HasMiddleware
{
    protected string $activityLogModule = 'CMS';

    protected string $activityEntityAttribute = 'name';

    public function __construct(private readonly MenuService $menuService)
    {
        // No parent constructor call - ScaffoldController doesn't have one
    }

    // =============================================================================
    // MIDDLEWARE
    // =============================================================================

    public static function middleware(): array
    {
        return [
            ...(new MenuDefinition)->getMiddleware(),
            new Middleware('crud.exceptions'),
            // Custom endpoints
            new Middleware('permission:add_menus', only: ['duplicate']),
            new Middleware('permission:edit_menus', only: ['saveAll']),
        ];
    }

    // =============================================================================
    // INDEX - Custom to add location assignments
    // =============================================================================

    public function index(Request $request): Response|RedirectResponse
    {
        $this->enforcePermission('view');

        $data = $this->menuService->getData($request);
        $locations = Menu::getAvailableLocations();

        $menus = Menu::query()->containers()
            ->with([
                'allItems' => function ($query): void {
                    $query->select(['id', 'parent_id', 'title', 'sort_order', 'type']);
                },
            ])
            ->orderBy('name')
            ->get();

        return Inertia::render($this->inertiaPage().'/index', [
            'config' => $this->menuService->getScaffoldDefinition()->toInertiaConfig(),
            ...$data,
            'menus' => $menus,
            'locations' => $locations,
            'locationAssignments' => $this->summarizeLocations($locations, $menus),
        ]);
    }

    // =============================================================================
    // CREATE - Simple form
    // =============================================================================

    public function create(): Response
    {
        $this->enforcePermission('add');

        $locations = Menu::getAvailableLocations();

        $assignedMenus = Menu::query()->containers()
            ->whereIn('location', array_keys($locations))
            ->where('is_active', true)
            ->get()
            ->keyBy('location');

        return Inertia::render($this->inertiaPage().'/create', [
            'locations' => $locations,
            'assignedMenus' => $assignedMenus,
            'statusOptions' => $this->menuService->getStatusOptions(),
            'locationOptions' => $this->menuService->getLocationOptions(),
        ]);
    }

    // =============================================================================
    // SHOW - Redirects to edit (menu builder uses edit as detail view)
    // =============================================================================

    public function show(int|string $id): Response
    {
        return $this->edit($id);
    }

    // =============================================================================
    // EDIT - Menu builder interface
    // =============================================================================

    public function edit(int|string $id): Response
    {
        $this->enforcePermission('edit');

        $menu = Menu::query()->containers()->findOrFail($id);

        $pagesql = CmsPost::query()->published()->where('type', 'page');

        $not_ids = [];
        if (! empty(setting('cms_default_pages_home_page', ''))) {
            $not_ids[] = setting('cms_default_pages_home_page', '');
        }

        if (! empty(setting('cms_default_pages_blogs_page', ''))) {
            $not_ids[] = setting('cms_default_pages_blogs_page', '');
        }

        if ($not_ids !== []) {
            $pagesql->whereNotIn('id', $not_ids);
        }

        $pages = $pagesql->orderBy('title', 'ASC')->get();
        $categories = CmsPost::query()->published()->where('type', 'category')->orderBy('title', 'ASC')->get();
        $tags = CmsPost::query()->published()->where('type', 'tag')->orderBy('title', 'ASC')->get();

        $itemTypes = Menu::getAvailableTypes();
        $itemTargets = Menu::getAvailableTargets();
        $locations = Menu::getAvailableLocations();
        $menuSettings = Menu::getThemeMenuSettings();

        return Inertia::render($this->inertiaPage().'/edit', [
            'menu' => [
                'id' => $menu->id,
                'name' => $menu->name,
                'slug' => $menu->slug,
                'location' => $menu->location ?? '',
                'description' => $menu->description ?? '',
                'is_active' => $menu->is_active,
                'all_items' => $this->getAllMenuItemsFlat($menu),
            ],
            'pages' => $pages->map(fn ($p) => ['id' => $p->id, 'title' => $p->title, 'slug' => $p->slug])->values()->all(),
            'categories' => $categories->map(fn ($c) => ['id' => $c->id, 'title' => $c->title, 'slug' => $c->slug])->values()->all(),
            'tags' => $tags->map(fn ($t) => ['id' => $t->id, 'title' => $t->title, 'slug' => $t->slug])->values()->all(),
            'itemTypes' => $itemTypes,
            'itemTargets' => $itemTargets,
            'locations' => $locations,
            'menuSettings' => $menuSettings,
            'statusOptions' => $this->menuService->getStatusOptions(),
            'locationOptions' => $this->menuService->getLocationOptions(),
        ]);
    }

    /**
     * Get all menu items as a flat list using a PostgreSQL recursive CTE (supports any depth).
     *
     * @return array<int, array<string, mixed>>
     */
    private function getAllMenuItemsFlat(Menu $menu): array
    {
        $results = DB::select("
            WITH RECURSIVE menu_tree AS (
                SELECT id, parent_id, title, COALESCE(url, '') AS url, type,
                       COALESCE(target, '_self') AS target, COALESCE(icon, '') AS icon,
                       COALESCE(css_classes, '') AS css_classes, COALESCE(link_title, '') AS link_title,
                       COALESCE(link_rel, '') AS link_rel, COALESCE(description, '') AS description,
                       object_id, sort_order, is_active
                FROM cms_menus
                WHERE parent_id = :menuId AND type != 'container' AND deleted_at IS NULL
                UNION ALL
                SELECT m.id, m.parent_id, m.title, COALESCE(m.url, ''), m.type,
                       COALESCE(m.target, '_self'), COALESCE(m.icon, ''), COALESCE(m.css_classes, ''),
                       COALESCE(m.link_title, ''), COALESCE(m.link_rel, ''), COALESCE(m.description, ''),
                       m.object_id, m.sort_order, m.is_active
                FROM cms_menus m
                INNER JOIN menu_tree mt ON m.parent_id = mt.id
                WHERE m.type != 'container' AND m.deleted_at IS NULL
            )
            SELECT * FROM menu_tree ORDER BY sort_order
        ", ['menuId' => $menu->id]);

        return collect($results)->map(fn ($item) => [
            'id' => $item->id,
            'parent_id' => $item->parent_id,
            'title' => $item->title,
            'url' => $item->url,
            'type' => $item->type,
            'target' => $item->target,
            'icon' => $item->icon,
            'css_classes' => $item->css_classes,
            'link_title' => $item->link_title,
            'link_rel' => $item->link_rel,
            'description' => $item->description,
            'object_id' => $item->object_id,
            'sort_order' => $item->sort_order,
            'is_active' => (bool) $item->is_active,
        ])->all();
    }

    // =============================================================================
    // CUSTOM ACTIONS
    // =============================================================================

    /**
     * Duplicate a menu with all its items (but without the location)
     */
    public function duplicate(Menu $menu): RedirectResponse
    {
        try {
            DB::beginTransaction();

            // Create the duplicate menu container without location
            $duplicatedMenu = Menu::query()->create([
                'type' => Menu::TYPE_CONTAINER,
                'name' => $menu->name.' (Copy)',
                'slug' => Str::slug($menu->name.' Copy '.time()),
                'location' => $this->normalizeLocationValue(null),
                'description' => $menu->description,
                'is_active' => $menu->is_active,
            ]);

            // Get all menu items (direct children of this container)
            /** @var \Illuminate\Database\Eloquent\Collection<int, Menu> $menuItems */
            $menuItems = $menu->allItems()->orderBy('sort_order')->get();
            $oldToNewIds = [];

            // First pass: duplicate all top-level items (parent is the container)
            foreach ($menuItems->where('parent_id', $menu->id) as $item) {
                $newItem = Menu::query()->create([
                    'type' => $item->type,
                    'parent_id' => $duplicatedMenu->id,
                    'name' => $item->name,
                    'title' => $item->title,
                    'link_title' => $item->link_title,
                    'link_rel' => $item->link_rel,
                    'icon' => $item->icon,
                    'url' => $item->getRawOriginal('url'),
                    'object_id' => $item->object_id,
                    'target' => $item->target,
                    'css_classes' => $item->getRawOriginal('css_classes'),
                    'description' => $item->description,
                    'sort_order' => $item->sort_order,
                    'is_active' => $item->is_active,
                ]);
                $oldToNewIds[$item->id] = $newItem->id;
            }

            // Second pass: duplicate all child items (items whose parent is another item)
            foreach ($menuItems as $item) {
                if ($item->parent_id !== $menu->id && isset($oldToNewIds[$item->parent_id])) {
                    $newParentId = $oldToNewIds[$item->parent_id];
                    $newItem = Menu::query()->create([
                        'type' => $item->type,
                        'parent_id' => $newParentId,
                        'name' => $item->name,
                        'title' => $item->title,
                        'link_title' => $item->link_title,
                        'link_rel' => $item->link_rel,
                        'icon' => $item->icon,
                        'url' => $item->getRawOriginal('url'),
                        'object_id' => $item->object_id,
                        'target' => $item->target,
                        'css_classes' => $item->getRawOriginal('css_classes'),
                        'description' => $item->description,
                        'sort_order' => $item->sort_order,
                        'is_active' => $item->is_active,
                    ]);
                    $oldToNewIds[$item->id] = $newItem->id;
                }
            }

            DB::commit();

            // Dispatch job to rebuild all caches asynchronously (non-blocking)
            dispatch(new RecacheApplication('Menu duplicate: '.$duplicatedMenu->name));

            $this->logActivity($duplicatedMenu, ActivityAction::DUPLICATE, 'Menu '.$menu->name.' duplicated successfully as '.$duplicatedMenu->name.'!');

            return to_route('cms.appearance.menus.index')
                ->with('success', sprintf("Menu '%s' duplicated successfully as '%s'!", $menu->name, $duplicatedMenu->name));
        } catch (Exception $exception) {
            DB::rollBack();

            return to_route('cms.appearance.menus.index')
                ->with('error', 'Failed to duplicate menu: '.$exception->getMessage());
        }
    }

    /**
     * Save all menu changes at once (unified save)
     */
    public function saveAll(Request $request, Menu $menu): JsonResponse
    {
        $payload = $request->all();
        if (isset($payload['settings']) && is_array($payload['settings'])) {
            $payload['settings']['location'] = $this->normalizeLocationValue($payload['settings']['location'] ?? null);
        }

        // Manual validation to return JSON errors instead of HTML redirects
        $validator = Validator::make($payload, [
            'settings' => 'required|array',
            'settings.name' => 'required|string|max:255',
            'settings.location' => 'nullable|string|max:100|unique:cms_menus,location,'.$menu->id,
            'settings.is_active' => 'required|boolean',
            'settings.description' => 'nullable|string|max:1000',
            'items' => 'required|array',
            'items.new' => 'nullable|array',
            'items.updated' => 'nullable|array',
            'items.deleted' => 'nullable|array',
            'items.order' => 'nullable|array',
        ], [
            'settings.required' => 'Menu settings are required.',
            'settings.name.required' => 'Menu name is required.',
            'settings.name.max' => 'Menu name cannot exceed 255 characters.',
            'settings.location.max' => 'Menu location cannot exceed 100 characters.',
            'settings.location.unique' => 'This location is already assigned to another menu.',
            'settings.is_active.required' => 'Menu status is required.',
            'settings.is_active.boolean' => 'Menu status must be active or inactive.',
            'settings.description.max' => 'Menu description cannot exceed 1000 characters.',
            'items.required' => 'Menu items data is required.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
                'details' => $validator->errors()->first(),
            ], 422);
        }

        $data = $validator->validated();
        $newItemIds = [];

        try {
            DB::beginTransaction();

            // 1. Update Menu Container Settings
            $menu->update([
                'name' => $data['settings']['name'],
                'location' => $this->normalizeLocationValue($data['settings']['location'] ?? null),
                'is_active' => $data['settings']['is_active'],
                'description' => $data['settings']['description'],
            ]);

            // 2. Handle Deletions
            if (! empty($data['items']['deleted'])) {
                $deletedIds = collect($data['items']['deleted'])->pluck('id');
                // Delete menu items (not containers) that belong to this menu
                Menu::query()->menuItems()
                    ->whereIn('id', $deletedIds)
                    ->where(function ($query) use ($menu): void {
                        // Items directly under this container or under other items
                        $query->where('parent_id', $menu->id)
                            ->orWhereIn('parent_id', function ($subquery) use ($menu): void {
                                $subquery->select('id')
                                    ->from('cms_menus')
                                    ->where('parent_id', $menu->id);
                            });
                    })
                    ->forceDelete();
            }

            // 3. Handle New Items
            if (! empty($data['items']['new'])) {
                $newItems = $data['items']['new'];

                // First pass: create items with parent_id pointing to container or existing items
                foreach ($newItems as $itemData) {
                    $parentId = $itemData['parent_id'] ?? null;

                    // If no parent or parent is the menu container
                    if (empty($parentId) || $parentId === $menu->id || $parentId > 0) {
                        $this->validateMenuItemData($itemData);
                        $newItem = $this->createMenuItem($itemData, $parentId ?: $menu->id);
                        $newItemIds[$itemData['id']] = $newItem->id;
                    }
                }

                // Second pass: create items that were children of other new items
                foreach ($newItems as $itemData) {
                    $parentId = $itemData['parent_id'] ?? null;
                    if (! empty($parentId) && $parentId < 0) {
                        // Parent was also a new item, find its new real ID
                        $realParentId = $newItemIds[$parentId] ?? null;
                        if ($realParentId) {
                            $this->validateMenuItemData($itemData);
                            $newItem = $this->createMenuItem($itemData, $realParentId);
                            $newItemIds[$itemData['id']] = $newItem->id;
                        }
                    }
                }
            }

            // 4. Handle Updates
            if (! empty($data['items']['updated'])) {
                foreach ($data['items']['updated'] as $itemData) {
                    $this->validateMenuItemData($itemData);
                    $item = Menu::query()->menuItems()->find($itemData['id']);
                    if ($item) {
                        $item->update($this->prepareItemData($itemData));
                    }
                }
            }

            // 5. Handle Re-ordering
            if (! empty($data['items']['order'])) {
                foreach ($data['items']['order'] as $orderData) {
                    $itemId = $orderData['id'];
                    // If the item was just created, find its new ID
                    if ($itemId < 0) {
                        $itemId = $newItemIds[$itemId] ?? null;
                    }

                    if ($itemId) {
                        $parentId = $orderData['parent_id'];

                        // Normalize parent_id
                        if (empty($parentId) || $parentId === $menu->id) {
                            $parentId = $menu->id;
                        } elseif ($parentId < 0) {
                            $parentId = $newItemIds[$parentId] ?? $menu->id;
                        }

                        Menu::query()->where('id', $itemId)->update([
                            'sort_order' => $orderData['sort_order'],
                            'parent_id' => $parentId,
                        ]);
                    }
                }
            }

            DB::commit();
        } catch (Exception $exception) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to save menu: '.$exception->getMessage(),
            ], 500);
        }

        // Dispatch job to rebuild all caches asynchronously (non-blocking)
        dispatch(new RecacheApplication('Menu update: '.$menu->name));

        $this->logActivity($menu, ActivityAction::UPDATE, 'Menu updated successfully');

        return response()->json([
            'success' => true,
            'message' => 'Menu saved successfully!',
            'newItemIds' => $newItemIds,
        ]);
    }

    protected function service(): MenuService
    {
        return $this->menuService;
    }

    protected function inertiaPage(): string
    {
        return 'cms/menus';
    }

    /**
     * Customize redirect after store - go to edit (menu builder) instead of show
     */
    protected function getAfterStoreRedirectUrl(Model $model): string
    {
        return route('cms.appearance.menus.edit', $model);
    }

    /**
     * Customize create success message
     */
    protected function buildCreateSuccessMessage(Model $model): string
    {
        return 'Menu created successfully! You can now add menu items.';
    }

    // =============================================================================
    // SCAFFOLD SIDE EFFECT HOOKS - Dispatch RecacheApplication for frontend cache
    // =============================================================================

    /**
     * Called after menu creation - dispatch recache for frontend
     */
    protected function handleCreationSideEffects(Model $model): void
    {
        dispatch(new RecacheApplication('Menu create: '.$model->getAttribute('name')));
    }

    /**
     * Called after menu deletion - dispatch recache for frontend
     */
    protected function handleDeletionSideEffects(Model $model): void
    {
        dispatch(new RecacheApplication('Menu delete: '.$model->getAttribute('name')));
    }

    /**
     * Called after menu restoration - dispatch recache for frontend
     */
    protected function handleRestorationSideEffects(Model $model): void
    {
        dispatch(new RecacheApplication('Menu restore: '.$model->getAttribute('name')));
    }

    /**
     * Called after bulk action - dispatch recache for frontend
     */
    protected function handleBulkActionSideEffects(string $action, array $ids): void
    {
        dispatch(new RecacheApplication('Menu bulk action: '.$action));
    }

    // =============================================================================
    // HELPER METHODS
    // =============================================================================

    /**
     * Summarize location assignments for index view
     */
    protected function summarizeLocations(array $locations, Collection $menus): array
    {
        $menusByLocation = $menus->keyBy('location');

        return collect($locations)
            ->map(function ($label, $key) use ($menusByLocation): array {
                $menu = $menusByLocation->get($key);

                return [
                    'key' => $key,
                    'label' => $label,
                    'menu' => $menu,
                    'items_count' => $menu?->allItems?->count() ?? 0,
                    'status' => $menu ? 'assigned' : 'unassigned',
                ];
            })
            ->values()
            ->all();
    }

    /**
     * The location column is NOT NULL. Persist blank location as empty string.
     */
    protected function normalizeLocationValue(?string $location): string
    {
        if (blank($location)) {
            return '';
        }

        return $location;
    }

    /**
     * Create a new menu item
     */
    private function createMenuItem(array $itemData, int $parentId): Menu
    {
        $preparedData = $this->prepareItemData($itemData);
        $preparedData['parent_id'] = $parentId;
        $preparedData['type'] = $itemData['type'] ?? Menu::TYPE_CUSTOM;

        return Menu::query()->create($preparedData);
    }

    /**
     * Prepares menu item data for create/update.
     */
    private function prepareItemData(array $itemData): array
    {
        $title = $itemData['title'] ?? '';

        return [
            'name' => $title,
            'title' => $title,
            'link_title' => $itemData['link_title'] ?? '',
            'link_rel' => $itemData['link_rel'] ?? '',
            'icon' => $itemData['icon'] ?? '',
            'type' => $itemData['type'] ?? Menu::TYPE_CUSTOM,
            'url' => $itemData['url'] ?? '#',
            'object_id' => $itemData['object_id'] ?? null,
            'target' => $itemData['target'] ?? '_self',
            'css_classes' => $itemData['css_classes'] ?? '',
            'description' => $itemData['description'] ?? '',
            'sort_order' => $itemData['sort_order'] ?? 0,
            'is_active' => $itemData['is_active'] ?? true,
        ];
    }

    /**
     * Validate menu item data
     */
    private function validateMenuItemData(array $itemData): void
    {
        throw_if(empty($itemData['title']), InvalidArgumentException::class, 'Menu item title is required');

        $validTypes = array_keys(Menu::getAvailableTypes());
        throw_if(empty($itemData['type']) || ! in_array($itemData['type'], $validTypes), InvalidArgumentException::class, 'Invalid menu item type');

        $validTargets = array_keys(Menu::getAvailableTargets());
        throw_if(! empty($itemData['target']) && ! in_array($itemData['target'], $validTargets), InvalidArgumentException::class, 'Invalid menu item target');

        throw_if(isset($itemData['is_active']) && ! is_bool($itemData['is_active']), InvalidArgumentException::class, 'Invalid menu item active status');

        throw_if(isset($itemData['object_id']) && ! empty($itemData['object_id']) && ! is_numeric($itemData['object_id']), InvalidArgumentException::class, 'Invalid menu item object ID');

        throw_if(isset($itemData['link_title']) && strlen($itemData['link_title']) > 255, InvalidArgumentException::class, 'Link title cannot exceed 255 characters');

        throw_if(isset($itemData['link_rel']) && strlen($itemData['link_rel']) > 100, InvalidArgumentException::class, 'Link rel cannot exceed 100 characters');

        throw_if(isset($itemData['icon']) && strlen($itemData['icon']) > 100, InvalidArgumentException::class, 'Icon class cannot exceed 100 characters');
    }
}
