<?php

declare(strict_types=1);

namespace Modules\Subscriptions\Models;

use App\Traits\AuditableTrait;
use App\Traits\HasMetadata;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Date;
use Modules\Customers\Models\Customer;
use Modules\Subscriptions\Database\Factories\SubscriptionFactory;
use RuntimeException;

/**
 * @property int $id
 * @property string|null $unique_id
 * @property int $customer_id
 * @property int $plan_id
 * @property int|null $plan_price_id
 * @property int|null $previous_plan_id
 * @property string $status
 * @property string|null $billing_cycle
 * @property float|string $price
 * @property string $currency
 * @property Carbon|null $trial_ends_at
 * @property Carbon|null $current_period_start
 * @property Carbon|null $current_period_end
 * @property Carbon|null $plan_changed_at
 * @property Carbon|null $canceled_at
 * @property Carbon|null $cancels_at
 * @property bool $cancel_at_period_end
 * @property Carbon|null $ended_at
 * @property Carbon|null $paused_at
 * @property Carbon|null $resumes_at
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read bool $is_active
 * @property-read bool $on_trial
 * @property-read bool $on_grace_period
 * @property-read string $status_label
 * @property-read string $status_badge
 * @property-read Plan|null $plan
 * @property-read Plan|null $previousPlan
 * @property-read PlanPrice|null $planPrice
 * @property-read Customer|null $customer
 * @property-read Collection<int, UsageRecord> $usageRecords
 */
class Subscription extends Model
{
    use AuditableTrait;
    use HasFactory;
    use HasMetadata;
    use SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_TRIALING = 'trialing';

    public const STATUS_PAST_DUE = 'past_due';

    public const STATUS_CANCELED = 'canceled';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_PAUSED = 'paused';

    protected $table = 'subscriptions_subscriptions';

    protected $fillable = [
        'unique_id',
        'customer_id',
        'plan_id',
        'plan_price_id',
        'previous_plan_id',
        'status',
        'billing_cycle',
        'price',
        'currency',
        'trial_ends_at',
        'current_period_start',
        'current_period_end',
        'plan_changed_at',
        'canceled_at',
        'cancels_at',
        'cancel_at_period_end',
        'ended_at',
        'paused_at',
        'resumes_at',
        'metadata',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $appends = [
        'status_label',
        'status_badge',
        'is_active',
        'on_trial',
        'on_grace_period',
    ];

    /**
     * Get the customer that owns the subscription.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * Get the plan for this subscription.
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    /**
     * Get the specific price option for this subscription.
     */
    public function planPrice(): BelongsTo
    {
        return $this->belongsTo(PlanPrice::class, 'plan_price_id');
    }

    /**
     * Get the previous plan for this subscription.
     */
    public function previousPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'previous_plan_id');
    }

    /**
     * Get usage records for this subscription.
     */
    public function usageRecords(): HasMany
    {
        return $this->hasMany(UsageRecord::class, 'subscription_id');
    }

    public static function generateUniqueId(?int $id = null): string
    {
        $prefix = 'SUB-';
        $numericId = $id ?? (int) (static::withTrashed()->max('id') ?? 0) + 1;

        return $prefix.str_pad((string) $numericId, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Check if subscription is valid (active or on trial).
     */
    public function valid(): bool
    {
        return $this->is_active || $this->on_grace_period;
    }

    /**
     * Check if subscription has ended.
     */
    public function ended(): bool
    {
        return $this->ended_at !== null && $this->ended_at->isPast();
    }

    /**
     * Check if subscription is past due.
     */
    public function pastDue(): bool
    {
        return $this->status === self::STATUS_PAST_DUE;
    }

    /**
     * Check if subscription is paused.
     */
    public function paused(): bool
    {
        return $this->status === self::STATUS_PAUSED;
    }

    /**
     * Cancel the subscription.
     */
    public function cancel(bool $immediately = false): self
    {
        $this->canceled_at = Date::now();

        if ($immediately) {
            $this->status = self::STATUS_CANCELED;
            $this->ended_at = Date::now();
            $this->cancel_at_period_end = false;
            $this->cancels_at = null;
        } else {
            // Cancel at end of current period (grace period)
            $this->cancels_at = $this->current_period_end;
            $this->cancel_at_period_end = true;
        }

        $this->save();

        return $this;
    }

    /**
     * Resume a canceled subscription (if in grace period).
     */
    public function resume(): self
    {
        if ($this->status === self::STATUS_PAUSED) {
            return $this->resumeFromPause();
        }

        throw_if(! $this->cancel_at_period_end || ! $this->cancels_at || $this->cancels_at->isPast(), RuntimeException::class, 'Cannot resume subscription that is not scheduled to cancel.');

        if ($this->status === self::STATUS_CANCELED) {
            $this->status = self::STATUS_ACTIVE;
        }

        $this->canceled_at = null;
        $this->cancels_at = null;
        $this->cancel_at_period_end = false;

        $this->save();

        return $this;
    }

    /**
     * Pause the subscription.
     */
    public function pause(?Carbon $resumeAt = null): self
    {
        if ($this->status !== self::STATUS_PAUSED) {
            $this->setMetadata('previous_status', $this->status);
        }

        $this->status = self::STATUS_PAUSED;
        $this->paused_at = Date::now();
        $this->resumes_at = $resumeAt;

        $this->save();

        return $this;
    }

    /**
     * Resume a paused subscription.
     */
    public function resumeFromPause(): self
    {
        throw_if($this->status !== self::STATUS_PAUSED, RuntimeException::class, 'Cannot resume subscription that is not paused.');

        $previousStatus = $this->getMetadata('previous_status') ?? self::STATUS_ACTIVE;
        $pauseStartedAt = $this->paused_at ?? Date::now();
        $pauseDuration = $pauseStartedAt->diffInSeconds(Date::now());

        if ($this->current_period_end) {
            $this->current_period_end = $this->current_period_end->copy()->addSeconds($pauseDuration);
        }

        $this->status = $previousStatus;
        $this->paused_at = null;
        $this->resumes_at = null;

        $this->save();

        return $this;
    }

    /**
     * Check if plan has a feature.
     */
    public function hasFeature(string $featureCode): bool
    {
        return $this->plan->hasFeature($featureCode);
    }

    /**
     * Get feature value from plan.
     */
    public function getFeatureValue(string $featureCode): mixed
    {
        return $this->plan->getFeatureValue($featureCode);
    }

    /**
     * Get current usage for a feature.
     */
    public function getCurrentUsage(string $featureCode): int
    {
        return (int) $this->usageRecords()
            ->where('feature_code', $featureCode)
            ->whereBetween('recorded_at', [
                $this->current_period_start,
                $this->current_period_end,
            ])
            ->sum('quantity');
    }

    /**
     * Record usage for a feature.
     */
    public function recordUsage(string $featureCode, int $quantity = 1): UsageRecord
    {
        /** @var UsageRecord $usageRecord */
        $usageRecord = $this->usageRecords()->create([
            'feature_code' => $featureCode,
            'quantity' => $quantity,
            'recorded_at' => Date::now(),
        ]);

        return $usageRecord;
    }

    protected static function newFactory(): SubscriptionFactory
    {
        return SubscriptionFactory::new();
    }

    protected function casts(): array
    {
        return [
            'customer_id' => 'integer',
            'plan_price_id' => 'integer',
            'previous_plan_id' => 'integer',
            'price' => 'decimal:2',
            'trial_ends_at' => 'datetime',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'plan_changed_at' => 'datetime',
            'canceled_at' => 'datetime',
            'cancels_at' => 'datetime',
            'cancel_at_period_end' => 'boolean',
            'ended_at' => 'datetime',
            'paused_at' => 'datetime',
            'resumes_at' => 'datetime',
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    protected function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_TRIALING => 'Trial',
            self::STATUS_PAST_DUE => 'Past Due',
            self::STATUS_CANCELED => 'Canceled',
            self::STATUS_EXPIRED => 'Expired',
            self::STATUS_PAUSED => 'Paused',
            default => ucfirst($this->status),
        };
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $subscription): void {
            if (empty($subscription->unique_id)) {
                $subscription->unique_id = self::generateUniqueId($subscription->id);
            }
        });
    }

    protected function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_ACTIVE => 'success',
            self::STATUS_TRIALING => 'info',
            self::STATUS_PAST_DUE => 'warning',
            self::STATUS_CANCELED => 'warning',
            self::STATUS_EXPIRED => 'danger',
            self::STATUS_PAUSED => 'secondary',
            default => 'secondary',
        };
    }

    protected function getIsActiveAttribute(): bool
    {
        return in_array($this->status, [self::STATUS_ACTIVE, self::STATUS_TRIALING]);
    }

    protected function getOnTrialAttribute(): bool
    {
        return $this->status === self::STATUS_TRIALING
            && $this->trial_ends_at
            && $this->trial_ends_at->isFuture();
    }

    protected function getOnGracePeriodAttribute(): bool
    {
        return $this->cancel_at_period_end
            && $this->cancels_at
            && $this->cancels_at->isFuture();
    }

    /**
     * Scope for active subscriptions.
     */
    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_ACTIVE, self::STATUS_TRIALING]);
    }

    /**
     * Scope for valid subscriptions (including grace period).
     */
    protected function scopeValid(Builder $query): Builder
    {
        return $query->where(function ($q): void {
            $q->whereIn('status', [self::STATUS_ACTIVE, self::STATUS_TRIALING])
                ->orWhere(function ($q2): void {
                    $q2->where('status', self::STATUS_CANCELED)
                        ->where('cancels_at', '>', Date::now());
                });
        });
    }
}
