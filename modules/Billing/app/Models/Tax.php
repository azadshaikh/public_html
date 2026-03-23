<?php

declare(strict_types=1);

namespace Modules\Billing\Models;

use App\Traits\AuditableTrait;
use App\Traits\HasMetadata;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $name
 * @property string|null $code
 * @property string|null $description
 * @property float|string $rate
 * @property string|null $type
 * @property string|null $country
 * @property string|null $state
 * @property string|null $postal_code
 * @property array<int, mixed>|null $applies_to
 * @property array<int, mixed>|null $excludes
 * @property bool $is_compound
 * @property int|null $priority
 * @property bool $is_active
 * @property Carbon|null $effective_from
 * @property Carbon|null $effective_to
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read string $status_label
 * @property-read string $status_badge
 * @property-read string $formatted_rate
 */
class Tax extends Model
{
    use AuditableTrait;
    use HasFactory;
    use HasMetadata;
    use SoftDeletes;

    public const TYPE_PERCENTAGE = 'percentage';

    public const TYPE_FIXED = 'fixed';

    protected $table = 'billing_taxes';

    protected $fillable = [
        'name',
        'code',
        'description',
        'rate',
        'type',
        'country',
        'state',
        'postal_code',
        'applies_to',
        'excludes',
        'is_compound',
        'priority',
        'is_active',
        'effective_from',
        'effective_to',
        'metadata',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $appends = [
        'status_label',
        'status_badge',
        'formatted_rate',
    ];

    /**
     * Calculate tax amount for a given subtotal.
     */
    public function calculateTax(float $subtotal): float
    {
        if ($this->type === self::TYPE_PERCENTAGE) {
            return $subtotal * $this->rate / 100;
        }

        return $this->rate;
    }

    /**
     * Check if tax is currently effective.
     */
    public function isEffective(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $now = now();

        if ($this->effective_from && $now->lt($this->effective_from)) {
            return false;
        }

        return ! ($this->effective_to && $now->gt($this->effective_to));
    }

    /**
     * Check if tax applies to a given location.
     */
    public function appliesToLocation(?string $country, ?string $state = null, ?string $postalCode = null): bool
    {
        // If no location constraints, applies everywhere
        if (! $this->country) {
            return true;
        }

        // Check country
        if ($this->country !== $country) {
            return false;
        }

        // Check state if specified
        if ($this->state && $this->state !== $state) {
            return false;
        }

        // Check postal code if specified
        if ($this->postal_code && $this->postal_code !== $postalCode) {
            return false;
        }

        return true;
    }

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2',
            'applies_to' => 'array',
            'excludes' => 'array',
            'is_compound' => 'boolean',
            'priority' => 'integer',
            'is_active' => 'boolean',
            'effective_from' => 'date',
            'effective_to' => 'date',
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
        return $this->is_active
            ? 'success'
            : 'secondary';
    }

    protected function getFormattedRateAttribute(): string
    {
        if ($this->type === self::TYPE_PERCENTAGE) {
            return ((float) $this->rate).'%';
        }

        return number_format((float) $this->rate, 2);
    }

    /**
     * Scope for active taxes.
     */
    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for effective taxes (active and within date range).
     *
     * @param  Builder<Tax>  $query
     * @return Builder<Tax>
     */
    #[Scope]
    protected function effective(Builder $query): Builder
    {
        return $query->active()
            ->where(function ($q): void {
                $q->whereNull('effective_from')
                    ->orWhere('effective_from', '<=', now());
            })
            ->where(function ($q): void {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', now());
            });
    }

    /**
     * Scope for taxes applicable to a location.
     */
    #[Scope]
    protected function forLocation(Builder $query, ?string $country, ?string $state = null): Builder
    {
        return $query->where(function ($q) use ($country, $state): void {
            $q->whereNull('country')
                ->orWhere(function ($q) use ($country, $state): void {
                    $q->where('country', $country);

                    if ($state) {
                        $q->where(function ($q) use ($state): void {
                            $q->whereNull('state')
                                ->orWhere('state', $state);
                        });
                    }
                });
        });
    }
}
