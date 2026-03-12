<?php

namespace App\Models;

use App\Enums\Status;
use App\Traits\AuditableTrait;
use App\Traits\HasStatusAccessors;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Pagination\LengthAwarePaginator;

class Group extends Model
{
    use AuditableTrait;
    use HasFactory;
    use HasStatusAccessors;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $appends = ['status_label', 'status_badge', 'status_class'];

    // Relationships
    public function items(): HasMany
    {
        return $this->hasMany(GroupItem::class, 'group_id');
    }

    /**
     * Get all groups with filtering and pagination
     */
    public static function getAllData(array $filter_arr = []): LengthAwarePaginator
    {
        $query = static::query()
            ->withTrashed()
            ->withCount('items');

        // Search filter
        if (! empty($filter_arr['search_text'])) {
            $query->where('name', 'ilike', '%'.$filter_arr['search_text'].'%')
                ->orWhere('slug', 'ilike', '%'.$filter_arr['search_text'].'%');
        }

        // Status filter
        if (! empty($filter_arr['status'])) {
            $query->where('status', $filter_arr['status']);
        }

        // Creator filter
        if (! empty($filter_arr['added_by'])) {
            $query->where('created_by', $filter_arr['added_by']);
        }

        // Sorting
        $sortBy = $filter_arr['sort_by'] ?? 'created_at';
        $order = $filter_arr['order'] ?? 'desc';
        $query->orderBy($sortBy, $order);

        // Pagination
        $perPage = $filter_arr['pagelimit'] ?? 15;

        return $query->paginate($perPage);
    }

    /**
     * Get group options for dropdown selects filtered by context (slug prefix).
     *
     * @param  string|null  $context  Filter groups by slug prefix (e.g., 'domain', 'server', 'website')
     * @return array<int, array{value: int, label: string}>
     */
    public static function getGroupOptions(?string $context = null): array
    {
        $query = static::query()
            ->where('status', Status::ACTIVE)
            ->orderBy('name');

        // Filter by context (slug prefix) if provided
        if ($context) {
            $query->where('slug', 'like', $context.'%');
        }

        return $query
            ->get()
            ->map(fn (self $group): array => [
                'value' => $group->id,
                'label' => $group->name,
            ])
            ->all();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => Status::class,
        ];
    }

    /**
     * Boot method to handle slug generation.
     *
     * Slug Generation Logic:
     * - Auto-generates slug from name when creating new records (if slug is empty)
     * - Preserves manual slugs during creation
     * - Never auto-generates slugs during updates (allows manual editing)
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $group): void {
            // Auto-generate slug from name when creating (only if slug is empty)
            if (empty($group->slug) && ! empty($group->name)) {
                $group->slug = generate_slug('groups', 'slug', $group->name);
            }
        });
    }

    // Scopes

    /**
     * Scope to get only active groups.
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('status', Status::ACTIVE);
    }

    /**
     * Scope to get only inactive groups.
     */
    #[Scope]
    protected function inactive(Builder $query): void
    {
        $query->where('status', Status::INACTIVE);
    }
}
