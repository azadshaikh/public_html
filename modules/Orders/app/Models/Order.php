<?php

declare(strict_types=1);

namespace Modules\Orders\Models;

use App\Traits\AuditableTrait;
use App\Traits\HasMetadata;
use App\Traits\HasStatusAccessors;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Customers\Models\Customer;

/**
 * @property int $id
 * @property string $order_number
 * @property int|null $customer_id
 * @property string $type
 * @property string $status
 * @property float $subtotal
 * @property float $discount_amount
 * @property float $tax_amount
 * @property float $total
 * @property string $currency
 * @property int|null $coupon_id
 * @property string|null $coupon_code
 * @property string|null $stripe_checkout_session_id
 * @property string|null $stripe_payment_intent_id
 * @property string|null $notes
 * @property Carbon|null $paid_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read string $status_label
 * @property-read string $status_badge
 */
class Order extends Model
{
    use AuditableTrait;
    use HasFactory;
    use HasMetadata;
    use HasStatusAccessors;
    use SoftDeletes;

    // --- Types ---
    public const string TYPE_SUBSCRIPTION_SIGNUP = 'subscription_signup';

    public const string TYPE_SUBSCRIPTION_UPGRADE = 'subscription_upgrade';

    public const string TYPE_ADDON = 'addon';

    public const string TYPE_ONE_TIME = 'one_time';

    // --- Statuses ---
    public const string STATUS_PENDING = 'pending';

    public const string STATUS_PROCESSING = 'processing';

    public const string STATUS_ACTIVE = 'active';

    public const string STATUS_CANCELLED = 'cancelled';

    public const string STATUS_REFUNDED = 'refunded';

    protected $table = 'orders_orders';

    protected $fillable = [
        'order_number',
        'customer_id',
        'type',
        'status',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'total',
        'currency',
        'coupon_id',
        'coupon_code',
        'stripe_checkout_session_id',
        'stripe_payment_intent_id',
        'notes',
        'metadata',
        'paid_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $appends = ['status_label', 'status_badge'];

    public function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'metadata' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    // --- Relationships ---

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    // --- HasStatusAccessors contract ---

    /**
     * @return array<string, array{label: string, class: string}>
     */
    public function statusMap(): array
    {
        return [
            self::STATUS_PENDING => ['label' => 'Pending',    'class' => 'bg-warning-subtle text-warning'],
            self::STATUS_PROCESSING => ['label' => 'Processing', 'class' => 'bg-info-subtle text-info'],
            self::STATUS_ACTIVE => ['label' => 'Active',     'class' => 'bg-success-subtle text-success'],
            self::STATUS_CANCELLED => ['label' => 'Cancelled',  'class' => 'bg-danger-subtle text-danger'],
            self::STATUS_REFUNDED => ['label' => 'Refunded',   'class' => 'bg-secondary-subtle text-secondary'],
        ];
    }

    // --- Type helpers ---

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            self::TYPE_SUBSCRIPTION_SIGNUP => 'Subscription Signup',
            self::TYPE_SUBSCRIPTION_UPGRADE => 'Subscription Upgrade',
            self::TYPE_ADDON => 'Add-on',
            self::TYPE_ONE_TIME => 'One-Time',
            default => ucfirst(str_replace('_', ' ', $type)),
        };
    }

    public static function typeBadgeClass(string $type): string
    {
        return match ($type) {
            self::TYPE_SUBSCRIPTION_SIGNUP => 'bg-primary-subtle text-primary',
            self::TYPE_SUBSCRIPTION_UPGRADE => 'bg-info-subtle text-info',
            self::TYPE_ADDON => 'bg-purple-subtle text-purple',
            self::TYPE_ONE_TIME => 'bg-secondary-subtle text-secondary',
            default => 'bg-light text-muted',
        };
    }

    public static function typeBadgeVariant(string $type): string
    {
        return match ($type) {
            self::TYPE_SUBSCRIPTION_SIGNUP => 'default',
            self::TYPE_SUBSCRIPTION_UPGRADE => 'info',
            self::TYPE_ADDON => 'secondary',
            self::TYPE_ONE_TIME => 'outline',
            default => 'secondary',
        };
    }

    /** @return array<string, string> */
    public static function typeOptions(): array
    {
        return [
            self::TYPE_SUBSCRIPTION_SIGNUP => 'Subscription Signup',
            self::TYPE_SUBSCRIPTION_UPGRADE => 'Subscription Upgrade',
            self::TYPE_ADDON => 'Add-on',
            self::TYPE_ONE_TIME => 'One-Time',
        ];
    }

    /** @return array<string, string> */
    public static function statusOptions(): array
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_PROCESSING => 'Processing',
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_REFUNDED => 'Refunded',
        ];
    }

    protected function getStatusColorName(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'warning',
            self::STATUS_PROCESSING => 'info',
            self::STATUS_ACTIVE => 'success',
            self::STATUS_CANCELLED => 'danger',
            self::STATUS_REFUNDED => 'secondary',
            default => 'secondary',
        };
    }
}
