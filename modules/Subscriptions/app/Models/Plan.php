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
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Subscriptions\Database\Factories\PlanFactory;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property int $trial_days
 * @property int $grace_days
 * @property int $sort_order
 * @property bool $is_popular
 * @property bool $is_active
 * @property array<string, mixed>|null $metadata
 * @property float|null $price
 * @property string|null $currency
 * @property string|null $billing_cycle
 * @property-read string|null $formatted_price
 * @property-read string|null $billing_cycle_label
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read PlanPrice|null $cheapestPrice
 * @property-read Collection<int, PlanPrice> $prices
 * @property-read Collection<int, PlanFeature> $features
 * @property-read Collection<int, Subscription> $subscriptions
 */
class Plan extends Model
{
    use AuditableTrait;
    use HasFactory;
    use HasMetadata;
    use SoftDeletes;

    public const CYCLE_MONTHLY = 'monthly';

    public const CYCLE_QUARTERLY = 'quarterly';

    public const CYCLE_YEARLY = 'yearly';

    public const CYCLE_LIFETIME = 'lifetime';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_ARCHIVED = 'archived';

    protected $table = 'subscriptions_plans';

    protected $fillable = [
        'code',
        'name',
        'description',
        'trial_days',
        'grace_days',
        'sort_order',
        'is_popular',
        'is_active',
        'metadata',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $appends = [
        'status_label',
        'status_badge',
        'formatted_price',
        'billing_cycle_label',
    ];

    /**
     * Get pricing options for this plan.
     */
    public function prices(): HasMany
    {
        return $this->hasMany(PlanPrice::class, 'plan_id')->orderBy('sort_order');
    }

    /**
     * Get the cheapest active price for this plan.
     */
    public function cheapestPrice(): HasOne
    {
        return $this->hasOne(PlanPrice::class, 'plan_id')
            ->where('is_active', true)
            ->orderBy('price');
    }

    /**
     * Get features for this plan.
     */
    public function features(): HasMany
    {
        return $this->hasMany(PlanFeature::class, 'plan_id');
    }

    /**
     * Get subscriptions for this plan.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'plan_id');
    }

    /**
     * Get active subscriptions for this plan.
     */
    public function activeSubscriptions(): HasMany
    {
        return $this->subscriptions()->where('status', Subscription::STATUS_ACTIVE);
    }

    /**
     * Check if plan has a specific feature.
     */
    public function hasFeature(string $featureCode): bool
    {
        return $this->features()->where('code', $featureCode)->exists();
    }

    /**
     * Get feature value by code.
     */
    public function getFeatureValue(string $featureCode): mixed
    {
        /** @var PlanFeature|null $feature */
        $feature = $this->features()->where('code', $featureCode)->first();

        return $feature?->value;
    }

    /**
     * Get all feature codes and values as array.
     *
     * @return array<string, mixed>
     */
    public function getFeaturesList(): array
    {
        return $this->features->pluck('value', 'code')->toArray();
    }

    /**
     * Get active plans ordered by sort order.
     *
     * @return Collection<int, static>
     */
    public static function getActivePlans(): Collection
    {
        return static::query()->where('is_active', true)
            ->with(['prices' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();
    }

    protected static function newFactory(): PlanFactory
    {
        return PlanFactory::new();
    }

    protected function casts(): array
    {
        return [
            'trial_days' => 'integer',
            'grace_days' => 'integer',
            'sort_order' => 'integer',
            'is_popular' => 'boolean',
            'is_active' => 'boolean',
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    protected function getStatusLabelAttribute(): string
    {
        return $this->is_active ? 'Active' : 'Inactive';
    }

    protected function getStatusBadgeAttribute(): string
    {
        return $this->is_active ? 'success' : 'warning';
    }

    protected function getPriceAttribute(): ?float
    {
        $price = $this->resolvePrimaryPrice();

        return $price instanceof PlanPrice ? (float) $price->price : null;
    }

    protected function getCurrencyAttribute(): ?string
    {
        return $this->resolvePrimaryPrice()?->currency;
    }

    protected function getBillingCycleAttribute(): ?string
    {
        return $this->resolvePrimaryPrice()?->billing_cycle;
    }

    protected function getFormattedPriceAttribute(): ?string
    {
        $price = $this->price;
        $currency = $this->currency;

        if ($price === null || $currency === null) {
            return null;
        }

        $symbol = match ($currency) {
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'INR' => '₹',
            'JPY' => '¥',
            default => $currency.' ',
        };

        return $symbol.number_format((float) $price, 2);
    }

    protected function getBillingCycleLabelAttribute(): ?string
    {
        $billingCycle = $this->billing_cycle;

        if ($billingCycle === null) {
            return null;
        }

        return match ($billingCycle) {
            self::CYCLE_MONTHLY => 'Monthly',
            self::CYCLE_QUARTERLY => 'Quarterly',
            self::CYCLE_YEARLY => 'Yearly',
            self::CYCLE_LIFETIME => 'Lifetime',
            default => ucfirst($billingCycle),
        };
    }

    /**
     * Scope for active plans.
     */
    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for popular plans.
     */
    #[Scope]
    protected function popular(Builder $query): Builder
    {
        return $query->where('is_popular', true);
    }

    private function resolvePrimaryPrice(): ?PlanPrice
    {
        if ($this->relationLoaded('prices')) {
            /** @var PlanPrice|null $price */
            $price = $this->prices
                ->where('is_active', true)
                ->sortBy('sort_order')
                ->first();

            return $price;
        }

        /** @var PlanPrice|null $price */
        $price = $this->prices()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->first();

        return $price;
    }
}
