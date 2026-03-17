<?php

namespace Modules\Platform\Models;

use App\Models\Address;
use App\Models\User;
use App\Traits\AddressableTrait;
use App\Traits\AuditableTrait;
use App\Traits\HasMetadata;
use App\Traits\HasNotes;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Platform\Models\Presenters\AgencyPresenter;
use Modules\Platform\Models\QueryBuilders\AgencyQueryBuilder;
use Modules\Platform\Traits\Providerable;
use RuntimeException;

/**
 * @property int $id
 * @property string|null $uid
 * @property string $name
 * @property string|null $email
 * @property string|null $website
 * @property string|null $mobile
 * @property string|null $country_code
 * @property string|null $address
 * @property string|null $city_name
 * @property string|null $zip_code
 * @property string|null $type
 * @property string|null $plan
 * @property int|null $owner_id
 * @property string|null $status
 * @property array<string, mixed>|null $metadata
 * @property int|null $agency_website_id
 * @property string|null $secret_key
 * @property string|null $webhook_url
 * @property string|null $website_id_prefix
 * @property int|null $website_id_zero_padding
 * @property int|string|null $logo_id
 * @property int|string|null $icon_id
 * @property int|string|null $light_icon_id
 * @property int|string|null $favicon_icon
 * @property int|string|null $apple_touch_icon
 * @property int|string|null $android_devices_icon
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, Server> $servers
 * @property-read string|null $plain_secret_key
 */
class Agency extends Model
{
    use AddressableTrait;
    use AgencyPresenter;
    use AuditableTrait;
    use HasFactory;
    use HasMetadata;
    use HasNotes;
    use Providerable;
    use SoftDeletes;

    public const DEFAULT_WEBSITE_ID_PREFIX = 'WS';

    public const DEFAULT_WEBSITE_ID_ZERO_PADDING = 5;

    public const MIN_WEBSITE_ID_ZERO_PADDING = 1;

    public const MAX_WEBSITE_ID_ZERO_PADDING = 10;

    protected $table = 'platform_agencies';

    protected $fillable = [
        'uid',
        'name',
        'email',
        'type',
        'plan',
        'website_id_prefix',
        'website_id_zero_padding',
        'owner_id',
        'status',
        'metadata',
        'agency_website_id', // FK to platform_websites for agency's SaaS platform
        'secret_key', // Encrypted token for agency-to-platform API auth
        'webhook_url', // URL to receive provisioning status webhooks
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    /**
     * The attributes that should be hidden for serialization.
     * The secret_key is used for secure API communication.
     */
    protected $hidden = [
        'secret_key',
    ];

    protected $appends = [
        'type_label',
        'type_color',
    ];

    /**
     * Check if agency has White-label capability based on its plan.
     */
    public function isWhitelabel(): bool
    {
        $plans = config('astero.agency_plans', []);

        return $plans[$this->plan]['whitelabel'] ?? false;
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function deletedBy()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Get the servers that belong to this agency.
     */
    public function servers()
    {
        return $this->belongsToMany(
            Server::class,
            'platform_agency_server',
            'agency_id',
            'server_id'
        )
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    /**
     * Get the websites that belong to this agency.
     */
    public function websites()
    {
        return $this->hasMany(Website::class, 'agency_id');
    }

    /**
     * Get the agency's own SaaS platform website (if exists).
     * This is the website where the agency manages their clients/sub-websites.
     */
    public function agencyWebsite()
    {
        return $this->belongsTo(Website::class, 'agency_website_id');
    }

    /**
     * Generate and save a new secret key for this agency.
     * Called automatically when an agency website is assigned.
     */
    public function generateSecretKey(): void
    {
        throw_unless($this->id, RuntimeException::class, 'Cannot generate secret key without persisted agency ID.');

        $secretToken = Str::random(64);
        $this->forceFill(['secret_key' => encrypt($secretToken)])->save();
    }

    /**
     * Get the primary server for this agency.
     */
    public function primaryServer()
    {
        return $this->servers()->wherePivot('is_primary', true)->first();
    }

    public static function normalizeWebsiteIdPrefix(?string $prefix): string
    {
        $candidate = strtoupper(trim((string) $prefix));
        $candidate = preg_replace('/[^A-Z0-9]/', '', $candidate) ?? '';

        if ($candidate === '') {
            return self::DEFAULT_WEBSITE_ID_PREFIX;
        }

        return substr($candidate, 0, 10);
    }

    /**
     * @param  int|string|null  $padding
     */
    public static function normalizeWebsiteIdZeroPadding($padding): int
    {
        $value = (int) $padding;
        if ($value < self::MIN_WEBSITE_ID_ZERO_PADDING) {
            return self::DEFAULT_WEBSITE_ID_ZERO_PADDING;
        }

        return min($value, self::MAX_WEBSITE_ID_ZERO_PADDING);
    }

    // ==================== POLYMORPHIC RELATIONSHIPS ====================

    public function addresses(): MorphMany
    {
        return $this->morphMany(Address::class, 'addressable');
    }

    public function primaryAddress(): MorphMany
    {
        return $this->addresses()->where('is_primary', true);
    }

    // ==================== HELPER METHODS ====================

    public function getPrimaryAddress(): ?Address
    {
        /** @var Address|null $address */
        $address = $this->addresses()->where('is_primary', true)->first();

        return $address;
    }

    public function newEloquentBuilder($query): AgencyQueryBuilder
    {
        return new AgencyQueryBuilder($query);
    }

    public static function getAllData(array $filter_arr = []): LengthAwarePaginator
    {
        return static::query()
            ->withTrashed()
            ->with(['owner'])
            ->search($filter_arr['search_text'] ?? null)
            ->filterByStatus($filter_arr['status'] ?? null)
            ->filterByDate($filter_arr['created_at'] ?? null)
            ->filterByCreator($filter_arr['added_by'] ?? null)
            ->filterByGroup($filter_arr['group'] ?? null)
            ->filterByOwner($filter_arr['owner_id'] ?? null)
            ->filterBySortable($filter_arr['sortable'] ?? null)
            ->sortBy($filter_arr['sort_by'] ?? null)
            ->orderResults($filter_arr['order'] ?? null)
            ->paginateResults($filter_arr['pagelimit'] ?? null);
    }

    /**
     * Generate UID based on the database record ID.
     *
     * Format: AGY + 4-digit padded database ID (e.g., AGY0001 for ID 1)
     *
     * @param  int  $recordId  The database record ID
     * @param  string  $prefix  Prefix for the ID (default 'AGY')
     * @return string Generated UID (e.g., AGY0001)
     */
    public static function generateUidFromRecordId(int $recordId, string $prefix = 'AGY'): string
    {
        // Format with leading zeros (4 digits)
        $paddedNumber = str_pad((string) $recordId, 4, '0', STR_PAD_LEFT);

        return $prefix.$paddedNumber;
    }

    /**
     * Assign UID based on the record ID.
     */
    public function assignUid(): void
    {
        throw_unless($this->id, RuntimeException::class, 'Cannot assign UID without a persisted agency ID.');

        $this->uid = static::generateUidFromRecordId($this->id);
        $this->save();
    }

    public static function getAgencyOptions()
    {
        return static::query()
            ->select('id as value', 'name as label')
            ->get()
            ->toArray();
    }

    protected function casts(): array
    {
        return [
            'type' => 'string',
            'owner_id' => 'integer',
            'website_id_zero_padding' => 'integer',
            'agency_website_id' => 'integer',
            'status' => 'string',
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Get the decrypted secret key for use in agency website .env files.
     *
     * The secret_key is stored encrypted in the database for security,
     * but must be sent as plain text to the agency website so they can
     * use it to authenticate API requests to the platform.
     *
     * @return string|null The plain text secret key, or null if not set
     */
    protected function getPlainSecretKeyAttribute(): ?string
    {
        if (! $this->secret_key) {
            return null;
        }

        try {
            return decrypt($this->secret_key);
        } catch (Exception $exception) {
            Log::warning('Failed to decrypt secret_key for agency', [
                'agency_id' => $this->id,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    protected function getWebsiteIdPrefixAttribute(?string $value): string
    {
        return self::normalizeWebsiteIdPrefix($value);
    }

    protected function setWebsiteIdPrefixAttribute(?string $value): void
    {
        $this->attributes['website_id_prefix'] = self::normalizeWebsiteIdPrefix($value);
    }

    protected function getWebsiteIdZeroPaddingAttribute($value): int
    {
        return self::normalizeWebsiteIdZeroPadding($value);
    }

    protected function setWebsiteIdZeroPaddingAttribute($value): void
    {
        $this->attributes['website_id_zero_padding'] = self::normalizeWebsiteIdZeroPadding($value);
    }

    protected function getTypeLabelAttribute(): string
    {
        $types = config('platform.agency_types');

        return $types[$this->type]['label'] ?? (string) $this->type;
    }

    protected function getTypeColorAttribute(): string
    {
        $types = config('platform.agency_types');

        return $types[$this->type]['color'] ?? 'secondary';
    }

    // ==================== METADATA ACCESSORS ====================

    protected function getBrandingNameAttribute(): ?string
    {
        return $this->getMetadata('branding_name');
    }

    protected function setBrandingNameAttribute($value): void
    {
        $this->setMetadata('branding_name', $value);
    }

    protected function getBrandingLogoAttribute(): ?string
    {
        return $this->getMetadata('branding_logo');
    }

    protected function setBrandingLogoAttribute($value): void
    {
        $this->setMetadata('branding_logo', $value);
    }

    protected function getBrandingIconAttribute(): ?string
    {
        return $this->getMetadata('branding_icon');
    }

    protected function setBrandingIconAttribute($value): void
    {
        $this->setMetadata('branding_icon', $value);
    }

    protected function getBrandingWebsiteAttribute(): ?string
    {
        return $this->getMetadata('branding_website');
    }

    protected function setBrandingWebsiteAttribute($value): void
    {
        $this->setMetadata('branding_website', $value);
    }
}
