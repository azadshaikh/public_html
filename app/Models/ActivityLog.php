<?php

namespace App\Models;

use App\Enums\ActivityAction;
use App\Models\Presenters\ActivityLogsPresenter;
use App\Models\QueryBuilders\ActivityLogQueryBuilder;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\CMS\Models\Theme;
use ReflectionClass;
use Spatie\Activitylog\Models\Activity;
use Throwable;

/**
 * ActivityLog Model
 *
 * Enhanced activity log model with improved querying, filtering, and presentation capabilities.
 * This model extends Spatie's Activity model with application-specific functionality.
 *
 * @property string $log_name
 * @property string $description
 * @property string|null $event
 * @property int|null $causer_id
 * @property string|null $causer_type
 * @property int|null $subject_id
 * @property string|null $subject_type
 * @property array|null $properties
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 */
class ActivityLog extends Activity
{
    use ActivityLogsPresenter;
    use HasFactory;
    use HasFactory;
    use SoftDeletes;

    /**
     * Create a new Eloquent query builder for the model.
     */
    public function newEloquentBuilder($query): ActivityLogQueryBuilder
    {
        return new ActivityLogQueryBuilder($query);
    }

    /**
     * Override the causer relationship to handle missing model classes gracefully
     */
    public function causer(): MorphTo
    {
        return $this->morphTo('causer')->withDefault();
    }

    /**
     * Override the subject relationship to handle missing model classes gracefully
     */
    public function subject(): MorphTo
    {
        return $this->morphTo('subject')->withDefault();
    }

    /**
     * Get valid morph types (only those where the class exists and uses a database table)
     */
    public static function getValidMorphTypes(string $column): Collection
    {
        static $cache = [];

        if (isset($cache[$column])) {
            return collect($cache[$column]);
        }

        $allTypes = static::query()
            ->distinct($column)
            ->whereNotNull($column)
            ->pluck($column);

        $validTypes = $allTypes->filter(function ($type): bool {
            // Check if class exists (disable autoloading to prevent errors for deleted models)
            try {
                // Try with autoloading, but catch any errors
                if (! class_exists($type, false) && ! class_exists($type)) {
                    return false;
                }
            } catch (Throwable) {
                // Autoloading failed (e.g., file was deleted), skip this type
                return false;
            }

            // Special handling for Theme model - it's file-based, not DB-based
            if ($type === Theme::class) {
                return false;
            }

            // Check if it's an Eloquent model with a table
            try {
                $reflection = new ReflectionClass($type);
                if (! $reflection->isSubclassOf(Model::class)) {
                    return false;
                }

                // Instantiate to check for table (may fail for models without DB tables)
                $instance = new $type;

                return ! empty($instance->getTable());
            } catch (Throwable) {
                // If instantiation fails, consider it invalid
                return false;
            }
        });

        $cache[$column] = $validTypes->toArray();

        return $validTypes;
    }

    /**
     * Get all activity data with enhanced filtering and querying
     */
    public static function getAllData(array $filters = []): LengthAwarePaginator
    {
        return self::query()
            ->visibleToCurrentUser()
            ->withValidRelations()
            ->with(['causer']) // Only eager load causer, subject will be lazy loaded with withDefault()
            ->when($filters['search_text'] ?? null, fn ($q, $search) => $q->search($search))
            ->when($filters['action'] ?? null, fn ($q, $action) => $q->where('event', $action))
            ->when($filters['date'] ?? null, fn ($q, $date) => $q->byDateRange($date['from'] ?? null, $date['to'] ?? null))
            ->when($filters['added_by'] ?? null, fn ($q, $causerId) => $q->byCauser($causerId))
            ->when($filters['subject_type'] ?? null, fn ($q, $type) => $q->bySubjectType($type))
            ->when($filters['recent_days'] ?? null, fn ($q, $days) => $q->recent($days))
            ->orderBy($filters['sort_by'] ?? 'created_at', $filters['sort_direction'] ?? 'desc')
            ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Get event options for dropdowns with enhanced formatting
     */
    public static function getEventOptions(): array
    {
        $events = self::query()
            ->distinct('event')
            ->whereNotNull('event')
            ->orderBy('event', 'asc')
            ->pluck('event');

        return $events->map(fn (string $event): array => [
            'label' => ucwords(str_replace('_', ' ', $event)),
            'value' => $event,
            'badge_class' => self::getEventBadgeClass($event),
        ])->all();
    }

    /**
     * Get statistics for dashboard/analytics
     */
    public static function getStatistics(int $days = 30): array
    {
        $query = self::query()
            ->visibleToCurrentUser()
            ->where('created_at', '>=', now()->subDays($days));

        return [
            'total_activities' => (clone $query)->count(),
            'unique_users' => (clone $query)->distinct('causer_id')->count('causer_id'),
            'by_action' => (clone $query)->groupBy('event')
                ->selectRaw('event, count(*) as count')
                ->pluck('count', 'event')
                ->toArray(),
            'daily_counts' => (clone $query)->selectRaw('DATE(created_at) as date, count(*) as count')
                ->groupBy('date')
                ->orderBy('date')
                ->pluck('count', 'date')
                ->toArray(),
        ];
    }

    /**
     * Get recent activities for a specific model
     */
    public static function getForModel(string $modelType, int $modelId, int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return self::query()
            ->where('subject_type', $modelType)
            ->where('subject_id', $modelId)
            ->with('causer')->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Clean up old activity logs
     */
    public static function cleanup(int $daysToKeep = 365): int
    {
        return self::query()
            ->where('created_at', '<', now()->subDays($daysToKeep))
            ->delete();
    }

    /**
     * Get property value safely
     */
    public function getProperty(string $key, mixed $default = null): mixed
    {
        return data_get($this->properties, $key, $default);
    }

    /**
     * Check if activity has specific property
     */
    public function hasProperty(string $key): bool
    {
        return data_get($this->properties, $key) !== null;
    }

    /**
     * The attributes that should be cast to native types.
     */
    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'properties' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ]);
    }

    /**
     * Scope to filter by specific actions using enum
     */
    #[Scope]
    protected function byAction(Builder $query, ActivityAction $action): Builder
    {
        return $query->where('event', $action->value);
    }

    /**
     * Scope to filter by multiple actions
     */
    #[Scope]
    protected function byActions(Builder $query, array $actions): Builder
    {
        $actionValues = collect($actions)->map(
            fn ($action) => $action instanceof ActivityAction ? $action->value : $action
        )->toArray();

        return $query->whereIn('event', $actionValues);
    }

    /**
     * Scope to filter by date range
     */
    #[Scope]
    protected function byDateRange(Builder $query, ?string $from = null, ?string $to = null): Builder
    {
        if ($from) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to) {
            $query->whereDate('created_at', '<=', $to);
        }

        return $query;
    }

    /**
     * Scope to filter by causer (user who performed the action)
     */
    #[Scope]
    protected function byCauser(Builder $query, int|array $causerIds): Builder
    {
        if (is_array($causerIds)) {
            return $query->whereIn('causer_id', $causerIds);
        }

        return $query->where('causer_id', $causerIds);
    }

    /**
     * Scope to filter by subject type (model type)
     */
    #[Scope]
    protected function bySubjectType(Builder $query, string $subjectType): Builder
    {
        return $query->where('subject_type', $subjectType);
    }

    /**
     * Scope to search in log name and description
     */
    #[Scope]
    protected function search(Builder $query, ?string $search): Builder
    {
        if (! $search) {
            return $query;
        }

        return $query->where(function ($q) use ($search): void {
            $q->where('log_name', 'ilike', sprintf('%%%s%%', $search))
                ->orWhere('description', 'ilike', sprintf('%%%s%%', $search))
                ->orWhere('event', 'ilike', sprintf('%%%s%%', $search));
        });
    }

    /**
     * Scope for recent activities
     */
    #[Scope]
    protected function recent(Builder $query, int $days = 7): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Scope to filter out activities by super users and system logs from non-super user views.
     * Super users can see all activities, but normal users cannot see:
     * - Activities by super users
     * - System logs (activities with no causer/user)
     */
    #[Scope]
    protected function visibleToCurrentUser(Builder $query): Builder
    {
        $currentUser = Auth::user();

        // If no user is authenticated or current user is a super user, show all
        if (! $currentUser || $currentUser->isSuperUser()) {
            return $query;
        }

        // For non-super users, exclude:
        // 1. Activities with no causer (system logs)
        // 2. Activities where causer is a super user
        $superUserIds = DB::table('model_has_roles')
            ->where('role_id', User::superUserRoleId())
            ->where('model_type', User::class)
            ->pluck('model_id');

        return $query
            ->whereNotNull('causer_id')
            ->whereNotIn('causer_id', $superUserIds);
    }

    /**
     * Scope to filter out records with invalid morph types (where class doesn't exist or doesn't use DB)
     */
    #[Scope]
    protected function withValidRelations(Builder $query): Builder
    {
        $validCauserTypes = static::getValidMorphTypes('causer_type');
        $validSubjectTypes = static::getValidMorphTypes('subject_type');

        return $query->where(function ($q) use ($validCauserTypes, $validSubjectTypes): void {
            // Include records where causer_type is null OR is a valid class
            $q->where(function ($subQ) use ($validCauserTypes): void {
                $subQ->whereNull('causer_type')
                    ->orWhereIn('causer_type', $validCauserTypes->toArray());
            })
            // AND where subject_type is null OR is a valid class
                ->where(function ($subQ) use ($validSubjectTypes): void {
                    $subQ->whereNull('subject_type')
                        ->orWhereIn('subject_type', $validSubjectTypes->toArray());
                });
        });
    }

    /**
     * Get badge class for event formatting
     */
    private static function getEventBadgeClass(string $event): string
    {
        return match ($event) {
            'create', 'created', 'stored' => 'bg-success-subtle text-success',
            'update', 'updated', 'edited' => 'bg-info-subtle text-info',
            'delete', 'deleted', 'trashed' => 'bg-danger-subtle text-danger',
            'restore', 'restored' => 'bg-warning-subtle text-warning',
            default => 'bg-primary-subtle text-primary',
        };
    }
}
