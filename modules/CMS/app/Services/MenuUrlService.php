<?php

namespace Modules\CMS\Services;

use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\CMS\Models\Menu;

class MenuUrlService
{
    public function __construct(
        private readonly PermaLinkService $permalinkService
    ) {}

    /**
     * Update all menu item URLs that have object_id
     * This is called when SEO CMS settings are updated to regenerate permalink URLs
     * Optimized to prevent N+1 queries with proper eager loading
     */
    public function updateMenuItemUrls(): bool
    {
        DB::beginTransaction();

        try {
            // Get all menu items that have object_id (linked to CMS objects)
            // Eager load page relationship with only necessary columns
            $menuItems = Menu::query()->menuItems()
                ->whereNotNull('object_id')
                ->where('object_id', '>', 0)
                ->whereIn('type', [Menu::TYPE_PAGE, Menu::TYPE_CATEGORY, Menu::TYPE_TAG])
                ->with(['page' => function ($query): void {
                    $query->select('id', 'title', 'slug', 'type', 'parent_id', 'category_id', 'published_at');
                },
                ])
                ->withTrashed()
                ->get();

            $updatedCount = 0;
            $toDelete = [];
            $toRestore = [];
            $bulkUpdates = [];

            // Process all items and prepare batch operations
            foreach ($menuItems as $menuItem) {
                if (is_null($menuItem->page)) {
                    $toDelete[] = $menuItem->id;

                    continue;
                }

                if ($menuItem->trashed()) {
                    $toRestore[] = $menuItem->id;
                }

                $newUrl = $this->generateUpdatedUrl($menuItem);

                if ($newUrl && $newUrl !== $menuItem->getRawOriginal('url')) {
                    $bulkUpdates[$menuItem->id] = $newUrl;
                }
            }

            // Perform batch operations
            if ($toDelete !== []) {
                Menu::query()->whereIn('id', $toDelete)->delete();
            }

            if ($toRestore !== []) {
                Menu::withTrashed()->whereIn('id', $toRestore)->restore();
            }

            // Batch update URLs using case statement for better performance
            if ($bulkUpdates !== []) {
                $cases = [];
                $ids = [];
                $params = [];

                foreach ($bulkUpdates as $id => $url) {
                    $cases[] = 'WHEN id = ? THEN ?';
                    $params[] = $id;
                    $params[] = $url;
                    $ids[] = $id;
                }

                $idsPlaceholder = implode(',', array_fill(0, count($ids), '?'));
                $sql = 'UPDATE cms_menus SET url = CASE '.implode(' ', $cases).sprintf(' END WHERE id IN (%s)', $idsPlaceholder);
                DB::update($sql, array_merge($params, $ids));

                $updatedCount = count($bulkUpdates);
            }

            DB::commit();

            if ($updatedCount > 0) {
                Artisan::call('optimize:clear');
                Artisan::call('optimize');
            }

            Log::info('Menu item URLs updated successfully', [
                'total_items_checked' => $menuItems->count(),
                'items_updated' => $updatedCount,
                'items_deleted' => count($toDelete),
                'items_restored' => count($toRestore),
            ]);

            return true;
        } catch (Exception $exception) {
            DB::rollBack();

            Log::error('Failed to update menu item URLs: '.$exception->getMessage(), [
                'trace' => $exception->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Update URLs for specific menu items by their IDs
     * Optimized to prevent N+1 queries with batch operations
     */
    public function updateSpecificMenuItems(array $menuItemIds): bool
    {
        try {
            DB::beginTransaction();

            $menuItems = Menu::query()->menuItems()
                ->whereIn('id', $menuItemIds)
                ->whereNotNull('object_id')
                ->where('object_id', '>', 0)
                ->with(['page' => function ($query): void {
                    $query->select('id', 'title', 'slug', 'type', 'parent_id', 'category_id', 'published_at');
                },
                ])
                ->withTrashed()
                ->get();

            $toDelete = [];
            $toRestore = [];
            $bulkUpdates = [];

            foreach ($menuItems as $menuItem) {
                if (is_null($menuItem->page)) {
                    $toDelete[] = $menuItem->id;

                    continue;
                }

                if ($menuItem->trashed()) {
                    $toRestore[] = $menuItem->id;
                }

                $newUrl = $this->generateUpdatedUrl($menuItem);

                if ($newUrl && $newUrl !== $menuItem->getRawOriginal('url')) {
                    $bulkUpdates[$menuItem->id] = $newUrl;
                }
            }

            // Perform batch operations
            if ($toDelete !== []) {
                Menu::query()->whereIn('id', $toDelete)->delete();
            }

            if ($toRestore !== []) {
                Menu::withTrashed()->whereIn('id', $toRestore)->restore();
            }

            $updatedCount = 0;
            if ($bulkUpdates !== []) {
                $cases = [];
                $ids = [];
                $params = [];

                foreach ($bulkUpdates as $id => $url) {
                    $cases[] = 'WHEN id = ? THEN ?';
                    $params[] = $id;
                    $params[] = $url;
                    $ids[] = $id;
                }

                $idsPlaceholder = implode(',', array_fill(0, count($ids), '?'));
                $sql = 'UPDATE cms_menus SET url = CASE '.implode(' ', $cases).sprintf(' END WHERE id IN (%s)', $idsPlaceholder);
                DB::update($sql, array_merge($params, $ids));

                $updatedCount = count($bulkUpdates);
            }

            DB::commit();

            Log::info('Specific menu item URLs updated successfully', [
                'requested_ids' => $menuItemIds,
                'items_found' => $menuItems->count(),
                'items_updated' => $updatedCount,
            ]);

            return true;
        } catch (Exception $exception) {
            DB::rollBack();

            Log::error('Failed to update specific menu item URLs: '.$exception->getMessage(), [
                'menu_item_ids' => $menuItemIds,
                'trace' => $exception->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Update menu item URL by object ID
     * Optimized to prevent N+1 queries with batch operations
     */
    public function updateMenuItemUrlByObjectId(int $objectId): int
    {
        $menuItems = Menu::query()->menuItems()
            ->where('object_id', $objectId)
            ->whereIn('type', [Menu::TYPE_PAGE, Menu::TYPE_CATEGORY, Menu::TYPE_TAG])
            ->with(['page' => function ($query): void {
                $query->select('id', 'title', 'slug', 'type', 'parent_id', 'category_id', 'published_at');
            },
            ])
            ->withTrashed()
            ->get();

        if ($menuItems->isEmpty()) {
            return 0;
        }

        $toDelete = [];
        $toRestore = [];
        $bulkUpdates = [];

        foreach ($menuItems as $menuItem) {
            if (is_null($menuItem->page)) {
                $toDelete[] = $menuItem->id;

                continue;
            }

            if ($menuItem->trashed()) {
                $toRestore[] = $menuItem->id;
            }

            $newUrl = $this->generateUpdatedUrl($menuItem);
            if ($newUrl && $newUrl !== $menuItem->getRawOriginal('url')) {
                $bulkUpdates[$menuItem->id] = $newUrl;
            }
        }

        // Perform batch operations
        $updatedCount = 0;

        if ($toDelete !== []) {
            Menu::query()->whereIn('id', $toDelete)->delete();
            $updatedCount += count($toDelete);
        }

        if ($toRestore !== []) {
            Menu::withTrashed()->whereIn('id', $toRestore)->restore();
        }

        if ($bulkUpdates !== []) {
            $cases = [];
            $ids = [];
            $params = [];

            foreach ($bulkUpdates as $id => $url) {
                $cases[] = 'WHEN id = ? THEN ?';
                $params[] = $id;
                $params[] = $url;
                $ids[] = $id;
            }

            $idsPlaceholder = implode(',', array_fill(0, count($ids), '?'));
            $sql = 'UPDATE cms_menus SET url = CASE '.implode(' ', $cases).sprintf(' END WHERE id IN (%s)', $idsPlaceholder);
            DB::update($sql, array_merge($params, $ids));

            $updatedCount += count($bulkUpdates);
        }

        if ($updatedCount > 0) {
            Artisan::call('optimize:clear');
            Artisan::call('optimize');
        }

        return $updatedCount;
    }

    /**
     * Force delete menu item URL by object ID
     */
    public function forceDeleteMenuItemUrlByObjectId(int $objectId): int
    {
        $updatedCount = 0;
        $menuItems = Menu::query()->menuItems()
            ->where('object_id', $objectId)
            ->whereIn('type', [Menu::TYPE_PAGE, Menu::TYPE_CATEGORY, Menu::TYPE_TAG])
            ->with(['page'])
            ->withTrashed()
            ->get();

        if ($menuItems->count() > 0) {
            foreach ($menuItems as $menuItem) {
                $menuItem->forceDelete();
                $updatedCount++;
            }
        }

        if ($updatedCount > 0) {
            Artisan::call('optimize:clear');
            Artisan::call('optimize');
        }

        return $updatedCount;
    }

    /**
     * Get all menu items that need URL updates (have object_id)
     */
    public function getMenuItemsWithObjectId(): Collection
    {
        return Menu::query()->menuItems()
            ->whereNotNull('object_id')
            ->where('object_id', '>', 0)
            ->whereIn('type', [Menu::TYPE_PAGE, Menu::TYPE_CATEGORY, Menu::TYPE_TAG])
            ->with(['page', 'parent'])
            ->get();
    }

    /**
     * Preview URL changes without actually updating them
     */
    public function previewUrlChanges(): array
    {
        $menuItems = $this->getMenuItemsWithObjectId();
        $changes = [];

        foreach ($menuItems as $menuItem) {
            $currentUrl = $menuItem->getRawOriginal('url');
            $newUrl = $this->generateUpdatedUrl($menuItem);

            if ($newUrl && $newUrl !== $currentUrl) {
                // Find the container name by traversing up
                $container = $menuItem->parent;
                while ($container && ! $container->isContainer()) {
                    $container = $container->parent;
                }

                $changes[] = [
                    'menu_item_id' => $menuItem->id,
                    'menu_name' => $container?->name,
                    'title' => $menuItem->title,
                    'type' => $menuItem->type,
                    'current_url' => $currentUrl,
                    'new_url' => $newUrl,
                ];
            }
        }

        return $changes;
    }

    /**
     * Generate updated URL for a menu item based on its type and object_id
     */
    private function generateUpdatedUrl(Menu $menuItem): ?string
    {
        if (! $menuItem->object_id || ! $menuItem->page) {
            return null;
        }

        try {
            // Use PermaLinkService to generate the latest permalink URL
            $post = $menuItem->page;

            return $this->permalinkService->generatePostPermalink($post);
        } catch (Exception $exception) {
            Log::warning('Failed to generate URL for menu item', [
                'menu_item_id' => $menuItem->id,
                'object_id' => $menuItem->object_id,
                'type' => $menuItem->type,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }
}
