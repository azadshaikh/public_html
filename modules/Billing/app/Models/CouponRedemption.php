<?php

declare(strict_types=1);

namespace Modules\Billing\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Customers\Models\Customer;

/**
 * @property int $id
 * @property int $coupon_id
 * @property int $customer_id
 * @property int|null $order_id
 * @property float $discount_applied
 * @property Carbon $redeemed_at
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Coupon $coupon
 * @property-read Customer $customer
 */
class CouponRedemption extends Model
{
    use HasFactory;

    protected $table = 'billing_coupon_redemptions';

    protected $fillable = [
        'coupon_id',
        'customer_id',
        'order_id',
        'discount_applied',
        'redeemed_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'discount_applied' => 'float',
            'redeemed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    // ================================================================
    // RELATIONSHIPS
    // ================================================================

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class, 'coupon_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
}
