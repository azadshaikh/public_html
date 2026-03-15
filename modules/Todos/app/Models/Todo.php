<?php

declare(strict_types=1);

namespace Modules\Todos\Models;

use App\Models\User;
use App\Traits\AuditableTrait;
use App\Traits\HasMetadata;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Modules\Todos\Database\Factories\TodoFactory;

/**
 * @property int $id
 * @property int $user_id
 * @property string $title
 * @property string|null $description
 * @property string $status
 * @property string $priority
 * @property Carbon|null $start_date
 * @property Carbon|null $due_date
 * @property string $visibility
 * @property bool $is_starred
 * @property Carbon|null $completed_at
 * @property string|null $labels
 * @property int|null $assigned_to
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read string $status_label
 * @property-read string $priority_label
 * @property-read string $assigned_to_name
 * @property-read string $owner_name
 * @property-read array<int, string> $labels_list
 * @property-read bool $is_overdue
 * @property-read User|null $owner
 * @property-read User|null $assignedTo
 */
class Todo extends Model
{
    use AuditableTrait;
    use HasFactory;
    use HasMetadata;
    use SoftDeletes;

    protected static function newFactory(): Factory
    {
        return TodoFactory::new();
    }

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'status',
        'priority',
        'start_date',
        'due_date',
        'visibility',
        'is_starred',
        'completed_at',
        'labels',
        'assigned_to',
        'metadata',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $appends = [
        'status_label',
        'priority_label',
        'assigned_to_name',
        'owner_name',
        'labels_list',
        'is_overdue',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Determine if the task is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Determine if the task is overdue.
     */
    public function isTodoOverdue(): bool
    {
        if (empty($this->due_date)) {
            return false;
        }

        if ($this->isCompleted()) {
            return false;
        }

        return today()->greaterThan($this->due_date->startOfDay());
    }

    protected function casts(): array
    {
        return [
            'start_date' => 'datetime',
            'due_date' => 'datetime',
            'completed_at' => 'datetime',
            'metadata' => 'array',
            'is_starred' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model): void {
            if (empty($model->user_id)) {
                $model->user_id = auth()->id();
            }

            if (empty($model->status)) {
                $model->status = 'pending';
            }

            if (empty($model->priority)) {
                $model->priority = 'medium';
            }
        });

        static::updating(function (self $model): void {
            if ($model->status === 'completed' && empty($model->completed_at)) {
                $model->completed_at = now();
            }
        });
    }

    protected function statusLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => match ($this->status) {
                'pending' => 'Pending',
                'in_progress' => 'In Progress',
                'completed' => 'Completed',
                'on_hold' => 'On Hold',
                'cancelled' => 'Cancelled',
                default => Str::of($this->status ?? 'Unknown')->replace('_', ' ')->title(),
            }
        );
    }

    protected function priorityLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => match ($this->priority) {
                'low' => 'Low',
                'medium' => 'Medium',
                'high' => 'High',
                'critical' => 'Critical',
                default => Str::of($this->priority ?? 'Unknown')->replace('_', ' ')->title(),
            }
        );
    }

    protected function assignedToName(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->assignedTo->name ?? 'Unassigned'
        );
    }

    protected function ownerName(): Attribute
    {
        return Attribute::make(
            get: fn (): string => $this->owner->name ?? 'System'
        );
    }

    protected function labelsList(): Attribute
    {
        return Attribute::make(
            get: fn (): array => array_values(array_filter(
                Arr::map(
                    explode(',', (string) $this->labels),
                    fn ($label): string => trim((string) $label)
                ),
            ))
        );
    }

    protected function isOverdue(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => $this->isTodoOverdue()
        );
    }
}
