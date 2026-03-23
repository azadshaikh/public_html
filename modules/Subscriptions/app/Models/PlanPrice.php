<?php

declare(strict_types=1);

namespace Modules\Subscriptions\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Subscriptions\Database\Factories\PlanPriceFactory;

/**
 * @property int $id
 * @property int $plan_id
 * @property string $billing_cycle
 * @property float|string $price
 * @property string $currency
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string $formatted_price
 * @property-read string $billing_cycle_label
 * @property-read Plan $plan
 * @property-read Collection<int, Subscription> $subscriptions
 */
class PlanPrice extends Model
{
    use HasFactory;

    protected $table = 'subscriptions_plan_prices';

    protected $fillable = [
        'plan_id',
        'billing_cycle',
        'price',
        'currency',
        'is_active',
        'sort_order',
    ];

    protected $appends = [
        'formatted_price',
        'billing_cycle_label',
    ];

    /**
     * The plan this price belongs to.
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    /**
     * Subscriptions using this price.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'plan_price_id');
    }

    protected static function newFactory(): PlanPriceFactory
    {
        return PlanPriceFactory::new();
    }

    protected function casts(): array
    {
        return [
            'plan_id' => 'integer',
            'price' => 'decimal:2',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Scope to only active prices.
     */
    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    protected function getFormattedPriceAttribute(): string
    {
        $symbol = match ($this->currency) {
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'INR' => '₹',
            'JPY' => '¥',
            default => $this->currency.' ',
        };

        return $symbol.number_format((float) $this->price, 2);
    }

    protected function getBillingCycleLabelAttribute(): string
    {
        return match ($this->billing_cycle) {
            Plan::CYCLE_MONTHLY => 'Monthly',
            Plan::CYCLE_QUARTERLY => 'Quarterly',
            Plan::CYCLE_YEARLY => 'Yearly',
            Plan::CYCLE_LIFETIME => 'Lifetime',
            default => ucfirst((string) $this->billing_cycle),
        };
    }
}
