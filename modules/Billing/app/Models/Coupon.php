<?php

declare(strict_types=1);

namespace Modules\Billing\Models;

use App\Traits\AuditableTrait;
use App\Traits\HasMetadata;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property string $type
 * @property float $value
 * @property string|null $currency
 * @property string $discount_duration
 * @property int|null $duration_in_months
 * @property int|null $max_uses
 * @property int $uses_count
 * @property int $max_uses_per_customer
 * @property float|null $min_order_amount
 * @property array<int>|null $applicable_plan_ids
 * @property Carbon|null $expires_at
 * @property bool $is_active
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, CouponRedemption> $redemptions
 */
class Coupon extends Model
{
    use AuditableTrait;
    use HasFactory;
    use HasMetadata;
    use SoftDeletes;

    public const TYPE_PERCENT = 'percent';

    public const TYPE_FIXED = 'fixed';

    public const DURATION_ONCE = 'once';

    public const DURATION_REPEATING = 'repeating';

    public const DURATION_FOREVER = 'forever';

    protected $table = 'billing_coupons';

    protected $fillable = [
        'code',
        'name',
        'description',
        'type',
        'value',
        'currency',
        'discount_duration',
        'duration_in_months',
        'max_uses',
        'uses_count',
        'max_uses_per_customer',
        'min_order_amount',
        'applicable_plan_ids',
        'expires_at',
        'is_active',
        'metadata',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'float',
            'min_order_amount' => 'float',
            'max_uses' => 'integer',
            'uses_count' => 'integer',
            'max_uses_per_customer' => 'integer',
            'duration_in_months' => 'integer',
            'applicable_plan_ids' => 'array',
            'is_active' => 'boolean',
            'expires_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    // ================================================================
    // RELATIONSHIPS
    // ================================================================

    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class, 'coupon_id');
    }

    // ================================================================
    // HELPERS
    // ================================================================

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isExhausted(): bool
    {
        return $this->max_uses !== null && $this->uses_count >= $this->max_uses;
    }

    public function isApplicableToPlan(?int $planId): bool
    {
        if (empty($this->applicable_plan_ids)) {
            return true;
        }

        return in_array($planId, array_map(intval(...), $this->applicable_plan_ids), true);
    }

    /**
     * Compute the discount amount for a given subtotal.
     */
    public function calculateDiscount(float $subtotal): float
    {
        if ($this->type === self::TYPE_PERCENT) {
            return round($subtotal * $this->value / 100, 2);
        }

        // Fixed — cannot make total negative
        return min((float) $this->value, $subtotal);
    }
}
