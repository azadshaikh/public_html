<?php

declare(strict_types=1);

namespace Modules\Subscriptions\Models;

use App\Traits\HasMetadata;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $subscription_id
 * @property string $feature_code
 * @property int $quantity
 * @property Carbon $recorded_at
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Subscription $subscription
 */
class UsageRecord extends Model
{
    use HasFactory;
    use HasMetadata;

    protected $table = 'subscriptions_usage_records';

    protected $fillable = [
        'subscription_id',
        'feature_code',
        'quantity',
        'recorded_at',
        'metadata',
    ];

    /**
     * Get the subscription this usage belongs to.
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'subscription_id');
    }

    /**
     * Get the plan feature definition.
     */
    public function getFeature(): ?PlanFeature
    {
        /** @var PlanFeature|null $feature */
        $feature = $this->subscription->plan->features()
            ->where('code', $this->feature_code)
            ->first();

        return $feature;
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'recorded_at' => 'datetime',
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Scope for specific feature.
     */
    #[Scope]
    protected function forFeature(Builder $query, string $featureCode): Builder
    {
        return $query->where('feature_code', $featureCode);
    }

    /**
     * Scope for current billing period.
     */
    #[Scope]
    protected function currentPeriod(Builder $query): Builder
    {
        return $query->whereHas('subscription', function ($q): void {
            $q->whereBetween('recorded_at', [
                $q->getQuery()->from.'.current_period_start',
                $q->getQuery()->from.'.current_period_end',
            ]);
        });
    }
}
