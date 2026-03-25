<?php

namespace Modules\Platform\Models;

use App\Models\User;
use App\Traits\AuditableTrait;
use App\Traits\HasMetadata;
use App\Traits\HasNotes;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Modules\Platform\Models\Presenters\ServerPresenter;
use Modules\Platform\Models\QueryBuilders\ServerQueryBuilder;
use Modules\Platform\Traits\HasSecrets;
use Modules\Platform\Traits\Providerable;
use Throwable;

/**
 * @property int $id
 * @property string|null $uid
 * @property string $name
 * @property bool $monitor
 * @property string $ip
 * @property int|null $port
 * @property string|null $access_key_id
 * @property string|null $access_key_secret
 * @property string|null $ssh_private_key
 * @property string|null $ssh_public_key
 * @property int|null $ssh_port
 * @property string|null $ssh_user
 * @property string|null $username
 * @property string|null $type
 * @property string|null $driver
 * @property int|null $current_domains
 * @property int|null $max_domains
 * @property string|null $fqdn
 * @property array<string, mixed>|null $metadata
 * @property string|null $status
 * @property string|null $provisioning_status
 * @property string|null $scripts_version
 * @property Carbon|null $scripts_updated_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Provider|null $provider
 * @property-read User|null $createdBy
 * @property-read User|null $updatedBy
 * @property-read User|null $deletedBy
 * @property-read Pivot $pivot
 */
class Server extends Model
{
    use AuditableTrait;
    use HasFactory;
    use HasMetadata;
    use HasNotes;
    use HasSecrets;
    use Providerable;
    use ServerPresenter;
    use SoftDeletes;

    // Provisioning status constants
    public const PROVISIONING_STATUS_PENDING = 'pending';

    public const PROVISIONING_STATUS_PROVISIONING = 'provisioning';

    public const PROVISIONING_STATUS_READY = 'ready';

    public const PROVISIONING_STATUS_FAILED = 'failed';

    public const TYPE_LOCALHOST = 'localhost';

    /**
     * Metadata field keys stored in the metadata JSON column
     */
    public const METADATA_FIELDS = [
        'server_cpu',
        'server_ccore',
        'server_storage',
        'server_storage_used',
        'server_storage_free',
        'server_ram',
        'server_os',
        'server_uptime',
        'server_load',
        'astero_version',
        'astero_releases',
        'hestia_version',
        'location_country_code',
        'location_country',
        'location_city_code',
        'location_city',
    ];

    protected $table = 'platform_servers';

    protected $fillable = [
        'uid',
        'name',
        'monitor',
        'ip',
        'port',
        'access_key_id',
        'access_key_secret',
        'ssh_private_key',
        'ssh_public_key',
        'ssh_port',
        'ssh_user',
        'type',
        'driver',
        'current_domains',
        'max_domains',
        'fqdn',
        'metadata',
        'status',
        'provisioning_status',
        'scripts_version',
        'scripts_updated_at',
        'acme_configured',
        'acme_email',
        'created_by',
        'updated_by',
        'deleted_by',
        'deleted_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     * These sensitive fields will not be included in JSON responses.
     */
    protected $hidden = [
        'access_key_secret',
        'ssh_private_key',
        'ssh_public_key',
    ];

    /**
     * Get the provider associated with the server.
     */
    protected $appends = [
        'type_label',
        'type_color',
        'type_badge',
        'provisioning_status_label',
        'provisioning_status_color',
    ];

    protected $dates = [
        'deleted_at',
        'created_at',
        'updated_at',
    ];

    /**
     * Set multiple metadata fields at once from form input
     */
    public function setMetadataFields(array $data): self
    {
        foreach (self::METADATA_FIELDS as $field) {
            if (array_key_exists($field, $data)) {
                $this->setMetadata($field, $data[$field]);
            }
        }

        return $this;
    }

    public function websites(): HasMany
    {
        return $this->hasMany(Website::class);
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

    /**
     * Get the agencies that this server belongs to.
     */
    public function agencies(): BelongsToMany
    {
        return $this->belongsToMany(
            Agency::class,
            'platform_agency_server',
            'server_id',
            'agency_id'
        )
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    /**
     * Get the primary agency for this server.
     */
    public function primaryAgency(): ?Agency
    {
        /** @var Agency|null $agency */
        $agency = $this->agencies()->wherePivot('is_primary', true)->first();

        return $agency;
    }

    /**
     * Check if server has SSH credentials configured.
     */
    public function hasSshCredentials(): bool
    {
        return ! empty($this->ip)
            && $this->hasSshPrivateKey()
            && ! empty($this->ssh_user);
    }

    public function getSshPrivateKeyForConnection(): ?string
    {
        $secretValue = $this->getSecretValue('ssh_private_key');
        if (is_string($secretValue) && trim($secretValue) !== '') {
            return $secretValue;
        }

        $legacyValue = $this->ssh_private_key;
        if (is_string($legacyValue) && trim($legacyValue) !== '') {
            return $legacyValue;
        }

        return null;
    }

    public function hasSshPrivateKey(): bool
    {
        return ! in_array($this->getSshPrivateKeyForConnection(), [null, '', '0'], true);
    }

    public function isLocalhostType(): bool
    {
        return $this->type === self::TYPE_LOCALHOST;
    }

    /**
     * Check if server is ready for website provisioning.
     */
    public function isReady(): bool
    {
        return $this->provisioning_status === self::PROVISIONING_STATUS_READY
            && $this->status === 'active';
    }

    /**
     * Check if server is currently being provisioned.
     */
    public function isProvisioning(): bool
    {
        return $this->provisioning_status === self::PROVISIONING_STATUS_PROVISIONING;
    }

    /**
     * Check if provisioning can be started (pending or failed status).
     */
    public function canProvision(): bool
    {
        return in_array($this->provisioning_status, [
            self::PROVISIONING_STATUS_PENDING,
            self::PROVISIONING_STATUS_FAILED,
        ], true);
    }

    public function newEloquentBuilder($query): ServerQueryBuilder
    {
        return new ServerQueryBuilder($query);
    }

    /**
     * Format the primary key into a human-friendly server code (e.g., SVR0001).
     */
    public static function generateServerCodeFromId(int $id, int $padLength = 4): string
    {
        $padded = str_pad((string) $id, $padLength, '0', STR_PAD_LEFT);

        return 'SVR'.$padded;
    }

    public static function getAllData(array $filter_arr = []): LengthAwarePaginator
    {
        return static::query()
            ->withTrashed()
            ->with(['providers'])
            ->search($filter_arr['search_text'] ?? null)
            ->filterByStatus($filter_arr['status'] ?? null)
            ->filterByDate($filter_arr['created_at'] ?? null)
            ->filterByCreator($filter_arr['added_by'] ?? null)
            ->filterByGroup($filter_arr['group'] ?? null)
            ->filterBySortable($filter_arr['sortable'] ?? null)
            ->sortBy($filter_arr['sort_by'] ?? null)
            ->orderResults($filter_arr['order'] ?? null)
            ->paginateResults($filter_arr['pagelimit'] ?? null);
    }

    public static function getServerOptions(): array
    {
        try {
            return static::query()
                ->select('id as value', DB::raw(static::getServerLabelSelectRaw().' as label'))
                ->get()
                ->toArray();
        } catch (Throwable $throwable) {
            if (App::runningInConsole()) {
                return [];
            }

            throw $throwable;
        }
    }

    public static function getServerLabelSelectRaw(?string $driver = null): string
    {
        $connectionDriver = $driver ?? DB::connection()->getDriverName();

        return $connectionDriver === 'sqlite'
            ? "name || ' (' || ip || ')'"
            : "CONCAT(name, ' (', ip, ')')";
    }

    protected function casts(): array
    {
        return [
            'monitor' => 'boolean',
            'port' => 'integer',
            'ssh_port' => 'integer',
            'current_domains' => 'integer',
            'max_domains' => 'integer',
            'access_key_id' => 'encrypted',
            'access_key_secret' => 'encrypted',
            'ssh_private_key' => 'encrypted',
            'metadata' => 'array',
            'acme_configured' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
            'scripts_updated_at' => 'datetime',
        ];
    }

    /**
     * Accessor for release_api_key from metadata.
     */
    protected function getReleaseApiKeyAttribute(): ?string
    {
        return $this->getSecretValue('release_api_key') ?? $this->getMetadata('release_api_key');
    }

    /**
     * Accessor for server_cpu from metadata
     */
    protected function getServerCpuAttribute(): ?string
    {
        return $this->getMetadata('server_cpu');
    }

    /**
     * Accessor for server_ccore from metadata
     */
    protected function getServerCcoreAttribute(): ?string
    {
        return $this->getMetadata('server_ccore');
    }

    /**
     * Accessor for server_storage from metadata
     */
    protected function getServerStorageAttribute(): ?int
    {
        return $this->getMetadata('server_storage');
    }

    /**
     * Accessor for server_ram from metadata
     */
    protected function getServerRamAttribute(): ?int
    {
        return $this->getMetadata('server_ram');
    }

    /**
     * Accessor for server_os from metadata
     */
    protected function getServerOsAttribute(): ?string
    {
        return $this->getMetadata('server_os');
    }

    /**
     * Accessor for astero_version from metadata
     */
    protected function getAsteroVersionAttribute(): ?string
    {
        return $this->getMetadata('astero_version');
    }

    /**
     * Accessor for hestia_version from metadata
     */
    protected function getHestiaVersionAttribute(): ?string
    {
        return $this->getMetadata('hestia_version');
    }

    /**
     * Accessor for location_country_code from metadata (ISO2 code)
     */
    protected function getLocationCountryCodeAttribute(): ?string
    {
        return $this->getMetadata('location_country_code');
    }

    /**
     * Accessor for location_country from metadata
     */
    protected function getLocationCountryAttribute(): ?string
    {
        return $this->getMetadata('location_country');
    }

    /**
     * Accessor for location_city_code from metadata
     */
    protected function getLocationCityCodeAttribute(): ?string
    {
        return $this->getMetadata('location_city_code');
    }

    /**
     * Accessor for location_city from metadata
     */
    protected function getLocationCityAttribute(): ?string
    {
        return $this->getMetadata('location_city');
    }

    /**
     * Get a formatted location label for display (e.g., "Mumbai, India")
     */
    protected function getLocationLabelAttribute(): ?string
    {
        $city = $this->location_city;
        $country = $this->location_country;

        if ($city && $country) {
            return sprintf('%s, %s', $city, $country);
        }

        return $city ?: $country;
    }

    protected function getTypeLabelAttribute(): string
    {
        if (empty($this->type)) {
            return '-';
        }

        $types = config('platform.server_types');

        return $types[$this->type]['label'] ?? $this->type;
    }

    protected function getTypeColorAttribute(): string
    {
        if (empty($this->type)) {
            return 'secondary';
        }

        $types = config('platform.server_types');

        return $types[$this->type]['color'] ?? 'secondary';
    }

    protected function getTypeBadgeAttribute(): string
    {
        $color = $this->type_color;

        return sprintf("<span class='badge text-bg-%s'>%s</span>", $color, $this->type_label);
    }

    /**
     * Get the human-readable provisioning status label.
     */
    protected function getProvisioningStatusLabelAttribute(): string
    {
        return match ($this->provisioning_status) {
            self::PROVISIONING_STATUS_PENDING => 'Pending',
            self::PROVISIONING_STATUS_PROVISIONING => 'Provisioning',
            self::PROVISIONING_STATUS_READY => 'Ready',
            self::PROVISIONING_STATUS_FAILED => 'Failed',
            default => ucfirst($this->provisioning_status ?? 'unknown'),
        };
    }

    /**
     * Get the Bootstrap color class for provisioning status.
     */
    protected function getProvisioningStatusColorAttribute(): string
    {
        return match ($this->provisioning_status) {
            self::PROVISIONING_STATUS_PENDING => 'secondary',
            self::PROVISIONING_STATUS_PROVISIONING => 'info',
            self::PROVISIONING_STATUS_READY => 'success',
            self::PROVISIONING_STATUS_FAILED => 'danger',
            default => 'secondary',
        };
    }

    /**
     * Get the server provider (hosting provider like Hetzner) via polymorphic relationship.
     */
    protected function getProviderAttribute(): ?Provider
    {
        return $this->getProvider(Provider::TYPE_SERVER);
    }
}
