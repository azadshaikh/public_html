<?php

declare(strict_types=1);

namespace Modules\Subscriptions\Models;

use App\Traits\HasMetadata;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $plan_id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property string $type
 * @property string|null $value
 * @property int $sort_order
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string $formatted_value
 * @property-read string $type_label
 * @property-read Plan $plan
 */
class PlanFeature extends Model
{
    use HasFactory;
    use HasMetadata;

    public const TYPE_BOOLEAN = 'boolean';

    public const TYPE_LIMIT = 'limit';

    public const TYPE_VALUE = 'value';

    public const TYPE_UNLIMITED = 'unlimited';

    protected $table = 'subscriptions_plan_features';

    protected $fillable = [
        'plan_id',
        'code',
        'name',
        'description',
        'type',
        'value',
        'sort_order',
        'metadata',
    ];

    protected $appends = [
        'formatted_value',
        'type_label',
    ];

    /**
     * Get the plan this feature belongs to.
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    /**
     * Check if this is a boolean feature.
     */
    public function isBoolean(): bool
    {
        return $this->type === self::TYPE_BOOLEAN;
    }

    /**
     * Check if this is a metered/limit feature.
     */
    public function isMetered(): bool
    {
        return $this->type === self::TYPE_LIMIT;
    }

    /**
     * Check if this feature is unlimited.
     */
    public function isUnlimited(): bool
    {
        return $this->type === self::TYPE_UNLIMITED;
    }

    /**
     * Get the numeric limit value.
     */
    public function getLimit(): ?int
    {
        if ($this->type === self::TYPE_UNLIMITED) {
            return -1;
        }

        if ($this->type !== self::TYPE_LIMIT) {
            return null;
        }

        return (int) $this->value;
    }

    /**
     * Check if feature is enabled.
     */
    public function isEnabled(): bool
    {
        if ($this->type === self::TYPE_BOOLEAN) {
            return (bool) $this->value;
        }

        return true;
    }

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    protected function getFormattedValueAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_BOOLEAN => $this->value ? '✓' : '✗',
            self::TYPE_UNLIMITED => 'Unlimited',
            self::TYPE_LIMIT => number_format((int) $this->value),
            default => (string) $this->value,
        };
    }

    protected function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_BOOLEAN => 'Boolean',
            self::TYPE_LIMIT => 'Usage Limit',
            self::TYPE_VALUE => 'Value',
            self::TYPE_UNLIMITED => 'Unlimited',
            default => ucfirst($this->type),
        };
    }
}
