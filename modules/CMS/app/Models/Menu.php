<?php

namespace Modules\CMS\Models;

use App\Traits\HasMetadata;
use Exception;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\CMS\Services\MenuCacheService;
use Modules\CMS\Services\ThemeConfigService;

/**
 * @property int $id
 * @property string $type
 * @property int|null $parent_id
 * @property string|null $name
 * @property string|null $title
 * @property string|null $slug
 * @property string|null $link_title
 * @property string|null $description
 * @property string|null $url
 * @property int|null $object_id
 * @property string|null $target
 * @property string|null $icon
 * @property string|null $css_classes
 * @property string|null $link_rel
 * @property int $sort_order
 * @property string|null $location
 * @property bool $is_active
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Menu|null $parent
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Menu> $children
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Menu> $allChildren
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Menu> $items
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Menu> $allItems
 * @property-read CmsPost|null $page
 */
class Menu extends Model
{
    use HasFactory;
    use HasFactory;
    use HasMetadata;
    use SoftDeletes;

    /**
     * Menu type constants
     */
    public const TYPE_CONTAINER = 'container';

    public const TYPE_CUSTOM = 'custom';

    public const TYPE_PAGE = 'page';

    public const TYPE_CATEGORY = 'category';

    public const TYPE_HOME = 'home';

    public const TYPE_ARCHIVE = 'archive';

    public const TYPE_SEARCH = 'search';

    public const TYPE_TAG = 'tag';

    protected $table = 'cms_menus';

    protected $fillable = [
        'type',
        'parent_id',
        'name',
        'title',
        'slug',
        'link_title',
        'description',
        'url',
        'object_id',
        'target',
        'icon',
        'css_classes',
        'link_rel',
        'sort_order',
        'location',
        'is_active',
        'metadata',
    ];

    /**
     * Default attribute values
     */
    protected $attributes = [
        'type' => self::TYPE_CONTAINER,
        'target' => '_self',
        'sort_order' => 0,
        'is_active' => true,
    ];

    // =========================================================================
    // TYPE DISCRIMINATION HELPERS
    // =========================================================================

    /**
     * Check if this is a menu container
     */
    public function isContainer(): bool
    {
        return $this->type === self::TYPE_CONTAINER;
    }

    /**
     * Check if this is a menu item (not a container)
     */
    public function isItem(): bool
    {
        return $this->type !== self::TYPE_CONTAINER;
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * Get the parent menu/item
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Get the menu container this item belongs to
     */
    public function container(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id')
            ->where('type', self::TYPE_CONTAINER);
    }

    /**
     * Get all children (items directly under this menu/item)
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->with(['children', 'page']);
    }

    /**
     * Get all children regardless of active status
     */
    public function allChildren(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->orderBy('sort_order');
    }

    /**
     * Get top-level items (for menu containers) - items directly under this menu
     */
    public function items(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->where('type', '!=', self::TYPE_CONTAINER)
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->orderBy('sort_order');
    }

    /**
     * Get all items (for menu containers) - direct children with their nested children
     */
    public function allItems(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->where('type', '!=', self::TYPE_CONTAINER)
            ->with(['allChildren', 'page'])
            ->orderBy('sort_order');
    }

    /**
     * Get all descendants (recursive children for containers)
     */
    public function descendants(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->where('type', '!=', self::TYPE_CONTAINER)
            ->with('descendants');
    }

    /**
     * Get the page if this is a page type menu item
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(CmsPost::class, 'object_id');
    }

    // =========================================================================
    // MENU CONTAINER METHODS
    // =========================================================================

    /**
     * Get menu by location
     *
     * Uses MenuCacheService for two-tier caching (memory + persistent).
     */
    public static function getByLocation(string $location): ?self
    {
        return resolve(MenuCacheService::class)->getByLocation($location);
    }

    /**
     * Get cached menu items with hierarchy
     *
     * Uses MenuCacheService for two-tier caching (memory + persistent).
     */
    public function getCachedItems(): Collection
    {
        if (! $this->isContainer()) {
            return collect();
        }

        return resolve(MenuCacheService::class)->getMenuItems($this->id);
    }

    /**
     * Check if menu has items
     */
    public function hasItems(): bool
    {
        return $this->items()->exists();
    }

    /**
     * Get available menu locations from theme config
     */
    public static function getAvailableLocations(): array
    {
        try {
            $activeTheme = Theme::getActiveTheme();
            if ($activeTheme && isset($activeTheme['directory'])) {
                $configService = resolve(ThemeConfigService::class);
                $config = $configService->loadThemeConfig($activeTheme['directory']);

                if (isset($config['menus']['locations']) && is_array($config['menus']['locations'])) {
                    return $config['menus']['locations'];
                }
            }
        } catch (Exception $exception) {
            Log::warning('Failed to load theme menu locations: '.$exception->getMessage());
        }

        return [];
    }

    /**
     * Get theme menu settings
     */
    public static function getThemeMenuSettings(): array
    {
        $defaultSettings = [
            'support_hierarchy' => true,
            'max_depth' => 3,
            'auto_add_pages' => false,
        ];

        try {
            $activeTheme = Theme::getActiveTheme();
            if ($activeTheme && isset($activeTheme['directory'])) {
                $configService = resolve(ThemeConfigService::class);
                $config = $configService->loadThemeConfig($activeTheme['directory']);

                if (isset($config['menus']['settings']) && is_array($config['menus']['settings'])) {
                    return array_merge($defaultSettings, $config['menus']['settings']);
                }
            }
        } catch (Exception $exception) {
            Log::warning('Failed to load theme menu settings: '.$exception->getMessage());
        }

        return $defaultSettings;
    }

    /**
     * Check if theme supports menu hierarchy
     */
    public static function supportsHierarchy(): bool
    {
        $settings = static::getThemeMenuSettings();

        return $settings['support_hierarchy'] ?? true;
    }

    /**
     * Get maximum menu depth from theme
     */
    public static function getMaxDepth(): int
    {
        $settings = static::getThemeMenuSettings();

        return $settings['max_depth'] ?? 3;
    }

    /**
     * Check if current theme supports menus
     */
    public static function themeSupportsMenus(): bool
    {
        return static::getAvailableLocations() !== [];
    }

    /**
     * Check if this menu item has children
     */
    public function hasChildren(): bool
    {
        return $this->children()->exists();
    }

    /**
     * Check if this menu item is active (current page)
     */
    public function isActive(): bool
    {
        $currentUrl = request()->url();
        $itemUrl = $this->url;

        if ($currentUrl === $itemUrl) {
            return true;
        }

        foreach ($this->children as $child) {
            if ($child->isActive()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the depth level of this menu item
     */
    public function getDepth(): int
    {
        $depth = 0;
        $parent = $this->parent;

        while ($parent && ! $parent->isContainer()) {
            $depth++;
            $parent = $parent->parent;
        }

        return $depth;
    }

    // =========================================================================
    // STATIC HELPERS
    // =========================================================================

    /**
     * Get available menu item types
     */
    public static function getAvailableTypes(): array
    {
        return [
            self::TYPE_CUSTOM => 'Custom Link',
            self::TYPE_PAGE => 'Page',
            self::TYPE_HOME => 'Home Page',
            self::TYPE_ARCHIVE => 'Archive/Blog',
            self::TYPE_SEARCH => 'Search Page',
            self::TYPE_CATEGORY => 'Category',
            self::TYPE_TAG => 'Tag',
        ];
    }

    /**
     * Get available link targets
     */
    public static function getAvailableTargets(): array
    {
        return [
            '_self' => 'Same Window',
            '_blank' => 'New Window/Tab',
        ];
    }

    // =========================================================================
    // CACHE MANAGEMENT
    // =========================================================================

    /**
     * Clear all caches related to this menu
     *
     * Uses MenuCacheService for two-tier cache invalidation (memory + persistent).
     */
    public static function clearMenuCaches($menu): void
    {
        $cacheService = resolve(MenuCacheService::class);

        // Clear location-based cache
        if ($menu->location) {
            $cacheService->invalidateLocation($menu->location);
        }

        // Clear menu items cache
        $cacheService->invalidateMenuItems($menu->id);

        // For items, also clear parent's cache
        if ($menu->parent_id) {
            $cacheService->invalidateMenuItems($menu->parent_id);

            // Find the container and clear its cache
            $parent = $menu->parent;
            while ($parent && ! $parent->isContainer()) {
                $parent = $parent->parent;
            }

            if ($parent) {
                $cacheService->invalidateMenuItems($parent->id);
                if ($parent->location) {
                    $cacheService->invalidateLocation($parent->location);
                }
            }
        }

        // Clear all possible menu location caches
        $locations = static::getAvailableLocations();
        foreach (array_keys($locations) as $location) {
            $cacheService->invalidateLocation($location);
        }

        // Clear view cache
        try {
            Artisan::call('view:clear');
        } catch (Exception) {
            // Silently fail if artisan is not available
        }
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'metadata' => 'array',
        ];
    }

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($menu): void {
            // Only generate slug for containers
            if ($menu->isContainer() && empty($menu->slug)) {
                $menu->slug = Str::slug($menu->name);
            }
        });

        static::updating(function ($menu): void {
            if ($menu->isContainer() && $menu->isDirty('name') && empty($menu->slug)) {
                $menu->slug = Str::slug($menu->name);
            }
        });

        static::saved(function ($menu): void {
            static::clearMenuCaches($menu);
        });

        static::deleted(function ($menu): void {
            static::clearMenuCaches($menu);
        });
    }

    /**
     * Scope to only get containers
     */
    #[Scope]
    protected function containers(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_CONTAINER);
    }

    /**
     * Scope to only get items (non-containers)
     */
    #[Scope]
    protected function menuItems(Builder $query): Builder
    {
        return $query->where('type', '!=', self::TYPE_CONTAINER);
    }

    // =========================================================================
    // MENU ITEM METHODS
    // =========================================================================

    /**
     * Get the URL for this menu item
     */
    protected function getUrlAttribute($value): string
    {
        // For containers, return empty
        if ($this->isContainer()) {
            return '';
        }

        // If custom URL is set, return it
        if (! empty($value)) {
            return $value;
        }

        // Handle different menu item types
        switch ($this->type) {
            case self::TYPE_PAGE:
                if ($this->page && $this->page->slug) {
                    return $this->page->permalink_url;
                }

                break;

            case self::TYPE_HOME:
                try {
                    return route('home');
                } catch (Exception) {
                    return url('/');
                }

            case self::TYPE_ARCHIVE:
                try {
                    return route('archive');
                } catch (Exception) {
                    return url('/archive');
                }

            case self::TYPE_SEARCH:
                try {
                    return route('search');
                } catch (Exception) {
                    return url('/search');
                }

            default:
                return '#';
        }

        return '#';
    }

    /**
     * Get CSS classes for this menu item
     */
    protected function getCssClassesAttribute($value): string
    {
        return $value ?: '';
    }

    /**
     * Get the display title (uses title for items, name for containers)
     */
    protected function getDisplayTitleAttribute(): string
    {
        return $this->title ?: $this->name ?: '';
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    /**
     * Scope for active menus/items
     */
    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for menus by location
     */
    #[Scope]
    protected function byLocation(Builder $query, string $location): Builder
    {
        return $query->where('location', $location);
    }

    /**
     * Scope for top-level items (no parent or parent is a container)
     */
    #[Scope]
    protected function topLevel(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope for menu items of a specific type
     */
    #[Scope]
    protected function ofType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Scope for ordered by sort_order
     */
    #[Scope]
    protected function ordered(Builder $query): Builder
    {
        return $query->orderBy('sort_order');
    }
}
