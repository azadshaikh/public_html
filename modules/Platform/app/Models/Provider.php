<?php

namespace Modules\Platform\Models;

use App\Models\User;
use App\Traits\AuditableTrait;
use App\Traits\HasMetadata;
use App\Traits\HasNotes;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphPivot;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\App;
use Throwable;

/**
 * @property int $id
 * @property string $name
 * @property string $type
 * @property string $vendor
 * @property string|null $email
 * @property array<string, mixed>|null $credentials
 * @property array<string, mixed>|null $metadata
 * @property string $status
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read bool $is_active
 * @property-read MorphPivot|Pivot $pivot
 */
class Provider extends Model
{
    use AuditableTrait;
    use HasFactory;
    use HasMetadata;
    use HasNotes;
    use SoftDeletes;

    // ==========================================
    // Type Constants (for convenience, config is source of truth)
    // ==========================================

    public const TYPE_DNS = 'dns';

    public const TYPE_CDN = 'cdn';

    public const TYPE_SERVER = 'server';

    public const TYPE_DOMAIN_REGISTRAR = 'domain_registrar';

    protected $table = 'platform_providers';

    protected $fillable = [
        'name',
        'type',
        'vendor',
        'email',
        'credentials',
        'metadata',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    /**
     * The attributes that should be hidden for serialization.
     * Credentials contain sensitive API keys/secrets.
     */
    protected $hidden = [
        'credentials',
    ];

    // ==========================================
    // Polymorphic Relationships (inverse)
    // ==========================================

    /**
     * Get all websites using this provider.
     */
    public function websites(): MorphToMany
    {
        return $this->morphedByMany(Website::class, 'providerable', 'platform_providerables')
            ->withPivot('is_primary');
    }

    /**
     * Get all domains using this provider.
     */
    public function domains(): MorphToMany
    {
        return $this->morphedByMany(Domain::class, 'providerable', 'platform_providerables')
            ->withPivot('is_primary');
    }

    /**
     * Get all servers using this provider.
     */
    public function servers(): MorphToMany
    {
        return $this->morphedByMany(Server::class, 'providerable', 'platform_providerables')
            ->withPivot('is_primary');
    }

    /**
     * Get all agencies using this provider.
     */
    public function agencies(): MorphToMany
    {
        return $this->morphedByMany(Agency::class, 'providerable', 'platform_providerables')
            ->withPivot('is_primary');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    // ==========================================
    // Static Methods
    // ==========================================

    /**
     * Get provider options for dropdowns (optionally filtered by type).
     */
    public static function getProviderOptions(?string $type = null): array
    {
        $query = static::query()->active();

        if ($type) {
            $query->ofType($type);
        }

        try {
            return $query->get()
                ->map(fn ($provider): array => [
                    'value' => $provider->id,
                    'label' => $provider->name.' ('.$provider->vendor_label.')',
                ])
                ->values()
                ->toArray();
        } catch (Throwable $throwable) {
            if (App::runningInConsole()) {
                return [];
            }

            throw $throwable;
        }
    }

    /**
     * Get type options from config.
     */
    public static function getTypeOptions(): array
    {
        return collect(config('platform.provider.types', []))
            ->map(fn ($config, $key): array => [
                'value' => $key,
                'label' => $config['label'],
                'color' => $config['color'] ?? 'secondary',
                'icon' => $config['icon'] ?? 'ri-settings-3-line',
            ])
            ->values()
            ->all();
    }

    /**
     * Get vendor options from config (optionally filtered by type).
     */
    public static function getVendorOptions(?string $type = null): array
    {
        return collect(config('platform.provider.vendors', []))
            ->filter(function (array $config) use ($type): bool {
                if (! $type) {
                    return true;
                }

                return in_array($type, $config['types'] ?? []);
            })
            ->map(fn ($config, $key): array => [
                'value' => $key,
                'label' => $config['label'],
                'color' => $config['color'] ?? 'secondary',
                'icon' => $config['icon'] ?? 'ri-settings-3-line',
            ])
            ->values()
            ->all();
    }

    /**
     * Get status options from config.
     */
    public static function getStatusOptions(): array
    {
        return collect(config('platform.provider.statuses', []))
            ->map(fn ($config, $key): array => [
                'value' => $key,
                'label' => $config['label'],
                'color' => $config['color'] ?? 'secondary',
            ])
            ->values()
            ->all();
    }

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'metadata' => 'array',
            'created_by' => 'integer',
            'updated_by' => 'integer',
            'deleted_by' => 'integer',
        ];
    }

    // ==========================================
    // Accessors
    // ==========================================

    /**
     * Get the type label from config.
     */
    protected function getTypeLabelAttribute(): string
    {
        return config(sprintf('platform.provider.types.%s.label', $this->type), ucfirst($this->type));
    }

    /**
     * Get the type color from config.
     */
    protected function getTypeColorAttribute(): string
    {
        return config(sprintf('platform.provider.types.%s.color', $this->type), 'secondary');
    }

    /**
     * Get the type icon from config.
     */
    protected function getTypeIconAttribute(): string
    {
        return config(sprintf('platform.provider.types.%s.icon', $this->type), 'ri-settings-3-line');
    }

    /**
     * Get the vendor label from config.
     */
    protected function getVendorLabelAttribute(): string
    {
        return config(sprintf('platform.provider.vendors.%s.label', $this->vendor), ucfirst($this->vendor));
    }

    /**
     * Get the vendor color from config.
     */
    protected function getVendorColorAttribute(): string
    {
        return config(sprintf('platform.provider.vendors.%s.color', $this->vendor), 'secondary');
    }

    /**
     * Get the vendor icon from config.
     */
    protected function getVendorIconAttribute(): string
    {
        return config(sprintf('platform.provider.vendors.%s.icon', $this->vendor), 'ri-settings-3-line');
    }

    /**
     * Get the status label from config.
     */
    protected function getStatusLabelAttribute(): string
    {
        return config(sprintf('platform.provider.statuses.%s.label', $this->status), ucfirst($this->status));
    }

    /**
     * Get the status color from config.
     */
    protected function getStatusColorAttribute(): string
    {
        return config(sprintf('platform.provider.statuses.%s.color', $this->status), 'secondary');
    }

    /**
     * Check if the provider is active.
     */
    protected function getIsActiveAttribute(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Get the balance from metadata (useful for API providers like Bunny).
     */
    protected function getBalanceAttribute()
    {
        return $this->getMetadata('balance') ?? 0.00;
    }

    // ==========================================
    // Scopes
    // ==========================================

    /**
     * Scope to filter by type.
     */
    #[Scope]
    protected function ofType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to filter by vendor.
     */
    #[Scope]
    protected function ofVendor($query, string $vendor)
    {
        return $query->where('vendor', $vendor);
    }

    /**
     * Scope to filter by status.
     */
    #[Scope]
    protected function ofStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get only active providers.
     */
    #[Scope]
    protected function active($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to filter DNS providers.
     */
    #[Scope]
    protected function dns($query)
    {
        return $query->where('type', self::TYPE_DNS);
    }

    /**
     * Scope to filter CDN providers.
     */
    #[Scope]
    protected function cdn($query)
    {
        return $query->where('type', self::TYPE_CDN);
    }

    /**
     * Scope to filter server providers.
     */
    #[Scope]
    protected function server($query)
    {
        return $query->where('type', self::TYPE_SERVER);
    }

    /**
     * Scope to filter domain registrars.
     */
    #[Scope]
    protected function domainRegistrar($query)
    {
        return $query->where('type', self::TYPE_DOMAIN_REGISTRAR);
    }
}
