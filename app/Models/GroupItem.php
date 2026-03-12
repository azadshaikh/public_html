<?php

namespace App\Models;

use App\Enums\Status;
use App\Traits\HasMetadata;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Pagination\LengthAwarePaginator;

class GroupItem extends Model
{
    use HasFactory;
    use HasMetadata;
    use SoftDeletes;

    // Status constants
    public const STATUS_ACTIVE = Status::ACTIVE->value;

    public const STATUS_INACTIVE = Status::INACTIVE->value;

    protected $table = 'group_items';

    protected $fillable = [
        'group_id',
        'name',
        'slug',
        'parent_id',
        'ranking',
        'status',
        'is_default',
        'metadata',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $appends = [
        'status_label',
        'status_badge',
    ];

    public static function getStatusOptions(): array
    {
        $allowedStatuses = [
            Status::ACTIVE->value,
            Status::INACTIVE->value,
        ];

        return array_intersect_key(Status::labels(), array_flip($allowedStatuses));
    }

    // Relationships
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'group_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(GroupItem::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(GroupItem::class, 'parent_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    // Static methods
    public static function getByGroup(string $groupSlug): Collection
    {
        return static::query()->whereHas('group', function ($query) use ($groupSlug): void {
            $query->where('slug', $groupSlug);
        })
            ->active()
            ->orderBy('ranking')
            ->orderBy('name')
            ->get();
    }

    public static function getBySlug(string $slug, string $groupSlug): ?self
    {
        return static::query()->where('slug', $slug)
            ->whereHas('group', function ($query) use ($groupSlug): void {
                $query->where('slug', $groupSlug);
            })
            ->first();
    }

    public static function getItemsArrayByPost(string $fieldName = 'id', array $post = []): array|Collection
    {
        $query = static::query()->select('group_items.'.$fieldName, 'group_items.name AS item_name', 'group_items.slug AS item_slug', 'group_items.color AS item_color', 'group_items.is_default', 'group_items.parent_id', 'group_items.group_id', 'groups.slug AS group_slug', 'group_items.note')
            ->join('groups', 'groups.id', '=', 'group_items.group_id')
            ->where('group_items.status', 'Active');

        // Apply filters
        if (! empty($post['group_slug'])) {
            if (is_array($post['group_slug'])) {
                $query->whereIn('groups.slug', $post['group_slug']);
            } else {
                $query->where('groups.slug', $post['group_slug']);
            }
        }

        if (! empty($post['item_slug'])) {
            if (is_array($post['item_slug'])) {
                $query->whereIn('group_items.slug', $post['item_slug']);
            } else {
                $query->where('group_items.slug', $post['item_slug']);
            }
        }

        if (! empty($post['group_id'])) {
            if (is_array($post['group_id'])) {
                $query->whereIn('group_items.group_id', $post['group_id']);
            } else {
                $query->where('group_items.group_id', $post['group_id']);
            }
        }

        if (! empty($post['parent_id'])) {
            $query->where('group_items.parent_id', $post['parent_id']);
        }

        $query->orderBy('group_items.name', 'asc');

        if (! empty($post['is_pluck'])) {
            return $query->pluck('item_name', $fieldName)->toArray();
        }

        return $query->get();
    }

    public static function getAllData(array $filter_arr = []): LengthAwarePaginator
    {
        $query = self::query()
            ->select('group_items.*')
            ->with(['group'])
            ->withTrashed();

        if (! empty($filter_arr['group_id'])) {
            $query->where('group_id', $filter_arr['group_id']);
        }

        if (! empty($filter_arr['search_text'])) {
            $search = (string) $filter_arr['search_text'];
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'ilike', sprintf('%%%s%%', $search))
                    ->orWhere('slug', 'ilike', sprintf('%%%s%%', $search));
            });
        }

        if (! empty($filter_arr['status'])) {
            if ($filter_arr['status'] === 'trash') {
                $query->onlyTrashed();
            } elseif ($filter_arr['status'] !== 'all') {
                $query->where('status', $filter_arr['status']);
            }
        }

        if (! empty($filter_arr['date']) && is_array($filter_arr['date'])) {
            if (! empty($filter_arr['date']['from'])) {
                $query->whereDate('created_at', '>=', $filter_arr['date']['from']);
            }

            if (! empty($filter_arr['date']['to'])) {
                $query->whereDate('created_at', '<=', $filter_arr['date']['to']);
            }
        }

        if (! empty($filter_arr['added_by'])) {
            $query->where('created_by', $filter_arr['added_by']);
        }

        $sortBy = in_array(($filter_arr['sort_by'] ?? ''), ['name', 'slug', 'status', 'created_at', 'updated_at'], true)
            ? $filter_arr['sort_by']
            : 'created_at';
        $direction = strtolower((string) ($filter_arr['order'] ?? 'desc'));
        $direction = in_array($direction, ['asc', 'desc'], true) ? $direction : 'desc';

        $perPage = (int) ($filter_arr['pagelimit'] ?? 15);
        $perPage = max(1, min(100, $perPage));

        return $query->orderBy((string) $sortBy, $direction)->paginate($perPage);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'parent_id' => 'integer',
            'ranking' => 'integer',
            'is_default' => 'boolean',
            'metadata' => 'array',
            'status' => Status::class,
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Boot method to handle model events.
     *
     * Slug Generation Logic:
     * - Auto-generates slug from name when creating new records (if slug is empty)
     * - Preserves manual slugs during creation
     * - Never auto-generates slugs during updates (allows manual editing)
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $groupItem): void {
            $groupItem->created_by = auth()->id();

            // Auto-generate slug from name when creating (only if slug is empty)
            if (empty($groupItem->slug) && ! empty($groupItem->name)) {
                $groupItem->slug = generate_slug('group_items', 'slug', $groupItem->name);
            }
        });

        static::updating(function (self $groupItem): void {
            $groupItem->updated_by = auth()->id();
            // Don't auto-generate slug on update - user can manually change it
        });

        static::deleting(function (self $groupItem): void {
            $groupItem->deleted_by = auth()->id();
            $groupItem->save();
        });
    }

    // Accessors
    protected function statusLabel(): Attribute
    {
        $status = (string) ($this->getAttribute('status') ?? 'inactive');

        return Attribute::make(
            get: fn () => Status::labels()[$status] ?? 'Unknown'
        );
    }

    protected function statusBadge(): Attribute
    {
        $status = (string) ($this->getAttribute('status') ?? 'inactive');

        return Attribute::make(
            get: function () use ($status): string {
                $statusMap = [
                    'active' => 'success',
                    'inactive' => 'secondary',
                    'trash' => 'danger',
                ];

                return $statusMap[$status] ?? 'secondary';
            }
        );
    }

    // Scopes
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('status', Status::ACTIVE);
    }

    #[Scope]
    protected function byGroup(Builder $query, int $groupId): void
    {
        $query->where('group_id', $groupId);
    }

    #[Scope]
    protected function byParent(Builder $query, ?int $parentId): void
    {
        if ($parentId === null) {
            $query->whereNull('parent_id');
        } else {
            $query->where('parent_id', $parentId);
        }
    }
}
