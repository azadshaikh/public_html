<?php

namespace App\Models;

use App\Services\GeoDataService;
use App\Traits\HasMetadata;
use App\Traits\HasNotes;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Address extends Model
{
    use HasFactory;
    use HasMetadata;
    use HasNotes;
    use SoftDeletes;

    protected $fillable = [
        'addressable_type', 'addressable_id', 'type', 'address1', 'address2', 'address3',
        'city', 'city_code', 'company', 'country_code', 'first_name', 'last_name',
        'phone', 'phone_code', 'state', 'state_code', 'zip',
        'country', 'latitude', 'longitude', 'metadata',
        'is_primary', 'is_verified',
        'created_by', 'updated_by', 'deleted_by',
    ];

    protected $appends = [
        'status',
        'status_label',
        'status_class',
        'full_name',
    ];

    // ==================== RELATIONSHIPS ====================

    /**
     * Polymorphic relationship
     */
    public function addressable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the user who created this address
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this address
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get the user who deleted this address
     */
    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    /**
     * Refresh geo data from API
     */
    public function refreshGeoData(): void
    {
        $geoService = resolve(GeoDataService::class);
        $updated = false;

        // Refresh country data
        if ($this->country_code) {
            $country = $geoService->getCountryByCode($this->country_code);
            if ($country) {
                $countryName = $country['name'] ?? null;
                if ($countryName !== null) {
                    $this->country = $countryName;
                    $updated = true;
                }
            }
        }

        // Refresh state data
        if ($this->state_code) {
            $state = $geoService->getStateByCode($this->state_code);
            if ($state) {
                $this->state = $state['name'] ?? $this->state;
                $updated = true;
            }
        }

        if ($updated) {
            $this->save();
        }
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'is_primary' => 'boolean',
            'is_verified' => 'boolean',
            'latitude' => 'decimal:9',
            'longitude' => 'decimal:9',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Boot method to handle model events.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $address): void {
            $address->created_by = auth()->id();
            $address->generateComputedFields();
        });

        static::updating(function (self $address): void {
            $address->updated_by = auth()->id();
            if ($address->isDirty(['address1', 'address2', 'address3', 'city', 'state', 'country_code', 'zip'])) {
                $address->generateComputedFields();
            }
        });

        static::deleting(function (self $address): void {
            $address->deleted_by = auth()->id();
            $address->save();
        });
    }

    // ==================== ACCESSORS & MUTATORS ====================

    /**
     * Get the status attribute for DataGrid badge template
     */
    protected function status(): Attribute
    {
        return Attribute::make(
            get: fn (): string => $this->trashed() ? 'trashed' : 'active'
        );
    }

    /**
     * Get the status label attribute
     */
    protected function statusLabel(): Attribute
    {
        return Attribute::make(
            get: fn (): string => $this->trashed() ? 'Trashed' : 'Active'
        );
    }

    /**
     * Get the status class attribute for badge styling
     */
    protected function statusClass(): Attribute
    {
        return Attribute::make(
            get: fn (): string => $this->trashed() ? 'bg-danger' : 'bg-success'
        );
    }

    /**
     * Get the full name attribute (computed from first_name + last_name)
     */
    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn (): string => trim($this->first_name.' '.$this->last_name)
        );
    }

    /**
     * Get country name
     */
    protected function getCountryAttribute($value): ?string
    {
        // Return the stored country name
        return $value;
    }

    /**
     * Get state name
     */
    protected function getStateAttribute($value): ?string
    {
        // For now, return the state name directly
        // In future, we can implement proper state lookup if needed
        return $value;
    }

    /**
     * Get city name
     */
    protected function getCityAttribute($value): ?string
    {
        // For now, return the city name directly
        // In future, we can implement proper city lookup if needed
        return $value;
    }

    /**
     * Scope for primary addresses
     */
    #[Scope]
    protected function primary($query)
    {
        return $query->where('is_primary', true);
    }

    /**
     * Scope by type
     */
    #[Scope]
    protected function ofType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope by country
     */
    #[Scope]
    protected function inCountry($query, string $countryCode)
    {
        return $query->where('country_code', strtoupper($countryCode));
    }

    /**
     * Scope by state
     */
    #[Scope]
    protected function inState($query, string $stateCode)
    {
        return $query->where('state_code', strtoupper($stateCode));
    }

    // ==================== SCOPES ====================

    /**
     * Scope for active addresses (not trashed)
     */
    #[Scope]
    protected function active($query)
    {
        return $query->whereNull('deleted_at');
    }

    /**
     * Scope for inactive addresses (trashed)
     */
    #[Scope]
    protected function inactive($query)
    {
        return $query->onlyTrashed();
    }

    /**
     * Scope for verified addresses
     */
    #[Scope]
    protected function verified($query)
    {
        return $query->where('is_verified', true);
    }

    /**
     * Scope for unverified addresses
     */
    #[Scope]
    protected function unverified($query)
    {
        return $query->where('is_verified', false);
    }

    /**
     * Generate computed fields (currently no computed fields needed)
     */
    private function generateComputedFields(): void
    {
        // No computed fields currently needed - full_name is handled by accessor
    }
}
