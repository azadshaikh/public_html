<?php

namespace Modules\Platform\Models;

use App\Models\User;
use App\Traits\AuditableTrait;
use App\Traits\HasMetadata;
use App\Traits\HasNotes;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Platform\Enums\WebsiteStatus;
use Modules\Platform\Models\Presenters\WebsitePresenter;
use Modules\Platform\Models\QueryBuilders\WebsiteQueryBuilder;
use Modules\Platform\Traits\HasProvisioningSteps;
use Modules\Platform\Traits\HasSecrets;
use Modules\Platform\Traits\Providerable;
use RuntimeException;

/**
 * @property int $id
 * @property string|null $type
 * @property string|null $plan_tier
 * @property array<int, string>|null $niches
 * @property string|null $uid
 * @property string|null $plan
 * @property string|null $name
 * @property int|null $domain_id
 * @property int|null $ssl_secret_id
 * @property string $domain
 * @property string|null $astero_version
 * @property int|null $server_id
 * @property int|null $agency_id
 * @property string|null $secret_key
 * @property string|null $site_id
 * @property array<string, mixed>|null $metadata
 * @property WebsiteStatus|string|null $status
 * @property Carbon|null $expired_on
 * @property string|null $customer_ref
 * @property array<string, mixed>|null $customer_data
 * @property string|null $plan_ref
 * @property array<string, mixed>|null $plan_data
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Provider|null $dns_provider
 * @property-read Provider|null $cdn_provider
 * @property-read int|null $dns_provider_id
 * @property-read int|null $cdn_provider_id
 * @property-read User|null $updatedBy
 * @property-read User|null $createdBy
 * @property-read string|null $plain_secret_key
 * @property-read string|null $webhook_url
 * @property-read Server|null $server
 * @property-read Agency|null $agency
 * @property-read Collection<int, mixed> $primaryCategory
 * @property-read Collection<int, mixed> $categories
 * @property-read Domain|null $domainRecord
 * @property string|null $provider
 * @property bool $storage_zone_setup
 * @property bool $is_www
 * @property bool $skip_cdn
 * @property bool $skip_dns
 * @property bool $skip_email
 * @property bool $is_agency
 */
class Website extends Model
{
    use AuditableTrait;
    use HasFactory;
    use HasMetadata;
    use HasNotes;
    use HasProvisioningSteps;
    use HasSecrets;
    use Providerable;
    use SoftDeletes;
    use WebsitePresenter;

    protected $table = 'platform_websites';

    protected $fillable = [
        'type',
        'plan_tier',    // renamed from 'plan' — internal tier classification
        'niches',
        'uid',
        'name',
        'domain_id',
        'ssl_secret_id',
        'domain',
        'dns_mode',     // 'managed' | 'external' | 'subdomain' (denormalized from domain record)
        'astero_version',
        'server_id',
        'agency_id',
        'secret_key',
        'metadata',
        'status',
        'expired_on',
        // Customer data (Business Layer — not used for HestiaCP provisioning)
        'customer_ref',
        'customer_data',
        // Plan snapshot (passed from Agency)
        'plan_ref',
        'plan_data',
        'created_by',
        'updated_by',
        'deleted_by',
        'deleted_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     * The secret_key is used for secure API communication.
     */
    protected $hidden = [
        'secret_key',
    ];

    protected $dates = [
        'deleted_at',
        'created_at',
        'updated_at',
        'expired_on',
    ];

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

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class, 'server_id');
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'agency_id');
    }

    /**
     * Get the domain record associated with this website.
     *
     * This relationship links to the root domain (e.g., "example.com")
     * which is extracted from the full website domain during provisioning.
     */
    public function domainRecord()
    {
        return $this->belongsTo(Domain::class, 'domain_id');
    }

    /**
     * Get the SSL certificate (Secret) associated with this website.
     *
     * This tracks which SSL certificate was installed on the website,
     * useful for renewal tracking and certificate management.
     */
    public function sslCertificate()
    {
        return $this->belongsTo(Secret::class, 'ssl_secret_id');
    }

    /**
     * Get human-readable labels for the website's niches.
     *
     * @return array Array of niche labels
     */
    public function getNichesLabels(): array
    {
        $niches = $this->niches ?? [];
        if (empty($niches)) {
            return [];
        }

        $nichesConfig = config('platform::website.niches', []);
        $labels = [];

        foreach ($niches as $niche) {
            $labels[] = $nichesConfig[$niche]['label'] ?? ucfirst(str_replace('_', ' ', $niche));
        }

        return $labels;
    }

    // Note: accountable() relationship has been replaced by secrets() from HasSecrets trait

    public function newEloquentBuilder($query): WebsiteQueryBuilder
    {
        return new WebsiteQueryBuilder($query);
    }

    public static function getAllData(array $filter_arr = []): LengthAwarePaginator
    {
        return static::query()
            ->withTrashed()
            ->with(['server', 'agency'])
            ->search($filter_arr['search_text'] ?? null)
            ->filterByTypeslug($filter_arr['typeslug'] ?? null)
            ->filterByStatus($filter_arr['status'] ?? null)
            ->filterByType($filter_arr['type'] ?? null)
            ->filterByDate($filter_arr['created_at'] ?? null)
            ->filterByCreator($filter_arr['added_by'] ?? null)
            ->filterByServer($filter_arr['server_id'] ?? null)
            ->filterByAgency($filter_arr['agency_id'] ?? null)
            ->filterBySortable($filter_arr['sortable'] ?? null)
            ->sortBy($filter_arr['sort_by'] ?? null)
            ->orderResults($filter_arr['order'] ?? null)
            ->paginateResults($filter_arr['pagelimit'] ?? null);
    }

    /**
     * Generate site ID based on the database record ID.
     *
     * Format: PREFIX + N-digit padded database ID (e.g., WS00045 for ID 45)
     *
     * @param  int  $recordId  The database record ID
     * @param  string|null  $prefix  Prefix for the ID (default agency fallback)
     * @param  int|null  $zeroPadding  Number of padded digits (default agency fallback)
     */
    public static function generateSiteIdFromRecordId(int $recordId, ?string $prefix = null, ?int $zeroPadding = null): string
    {
        $normalizedPrefix = Agency::normalizeWebsiteIdPrefix($prefix);
        $normalizedPadding = Agency::normalizeWebsiteIdZeroPadding($zeroPadding);
        $paddedNumber = str_pad((string) $recordId, $normalizedPadding, '0', STR_PAD_LEFT);

        return $normalizedPrefix.$paddedNumber;
    }

    /**
     * Generate a unique random slug for admin panel access
     *
     * @see generate_unique_id() in app/Helpers/Helper.php
     */
    public static function generateAdminSlug(): string
    {
        return generate_unique_id('platform_websites', 'uid', 9);
    }

    /**
     * Generate a unique random slug for media storage
     *
     * @see generate_unique_id() in app/Helpers/Helper.php
     */
    public static function generateMediaSlug(): string
    {
        return generate_unique_id('platform_websites', 'uid', 9);
    }

    /**
     * Normalize a user-provided uid into a safe, deterministic value that:
     * - contains only letters, numbers, underscores, and dashes
     * - is lowercase
     * - fits into the platform DB uid column length (25)
     *
     * If the value is longer than 25 characters, it is shortened and suffixed
     * with a stable hash so it remains predictable and reduces collisions.
     */
    public static function normalizeCustomUidInput(string $value): string
    {
        $original = trim($value);
        $normalized = strtolower($original);

        // Keep only allowed characters.
        $normalized = preg_replace('/[^a-z0-9_\-]+/', '', $normalized) ?? '';

        throw_if($normalized === '', RuntimeException::class, 'Custom server username may only contain letters, numbers, underscores, and dashes.');

        $maxLength = 25;
        if (mb_strlen($normalized) <= $maxLength) {
            return $normalized;
        }

        // Deterministic shortener: keep room for "-" + 5 chars.
        $hash = substr(hash('crc32b', $original), 0, 5);
        $prefix = substr($normalized, 0, $maxLength - 6);

        return $prefix.'-'.$hash;
    }

    public static function normalizeDomainHost(?string $domain): string
    {
        if (! is_string($domain)) {
            return '';
        }

        $normalized = trim($domain);

        if ($normalized === '') {
            return '';
        }

        $normalized = preg_replace('#^https?://#i', '', $normalized) ?? $normalized;
        $normalized = explode('/', $normalized)[0];
        $normalized = explode(':', $normalized)[0];

        return strtolower(trim($normalized, '.'));
    }

    public static function extractRootDomain(?string $fullDomain): string
    {
        $domain = static::normalizeDomainHost($fullDomain);

        if ($domain === '') {
            return '';
        }

        $domain = preg_replace('#^www\.#i', '', $domain) ?? $domain;
        $parts = explode('.', $domain);
        $partsCount = count($parts);

        if ($partsCount <= 2) {
            return $domain;
        }

        $multiLevelTlds = [
            'co.uk', 'org.uk', 'me.uk', 'ac.uk',
            'com.au', 'net.au', 'org.au',
            'co.nz', 'net.nz', 'org.nz',
            'co.za', 'org.za', 'net.za',
            'com.br', 'net.br', 'org.br',
            'co.in', 'net.in', 'org.in',
            'co.jp', 'ne.jp', 'or.jp',
        ];

        $lastTwo = $parts[$partsCount - 2].'.'.$parts[$partsCount - 1];

        if (in_array($lastTwo, $multiLevelTlds, true)) {
            return implode('.', array_slice($parts, -3));
        }

        return implode('.', array_slice($parts, -2));
    }

    public static function supportsWwwForDomain(?string $domain): bool
    {
        $normalizedDomain = static::normalizeDomainHost($domain);

        return $normalizedDomain !== '' && $normalizedDomain === static::extractRootDomain($normalizedDomain);
    }

    public function supportsWwwFeature(): bool
    {
        return static::supportsWwwForDomain($this->domain);
    }

    public function usesWwwPrimary(): bool
    {
        return $this->supportsWwwFeature() && $this->storedIsWwwPreference();
    }

    public function primaryHostname(): ?string
    {
        $domain = static::normalizeDomainHost($this->domain);

        if ($domain === '') {
            return null;
        }

        return $this->usesWwwPrimary() ? 'www.'.$domain : $domain;
    }

    /**
     * @return list<string>
     */
    public function cdnHostnames(): array
    {
        $domain = static::normalizeDomainHost($this->domain);

        if ($domain === '') {
            return [];
        }

        if (! $this->supportsWwwFeature()) {
            return [$domain];
        }

        return [$domain, 'www.'.$domain];
    }

    public function hestiaRedirectTarget(): ?string
    {
        if (! $this->supportsWwwFeature()) {
            return null;
        }

        return $this->primaryHostname();
    }

    /**
     * Assign and persist the uid, admin_slug, media_slug and secret_key based on the record ID.
     *
     * The secret_key is a random authentication token used to secure API communication
     * between the Astero platform and the provisioned client website. It is included
     * in the Authorization header when the platform makes API calls to client websites
     * (e.g., for expiration notifications, status updates, CDN purge requests).
     *
     * The token is generated using Str::random(64), stored encrypted in the platform database,
     * but sent as plain text to the client website's .env file for use in authorization checks.
     *
     * @param  int  $agencyId  The owning agency ID (used for generated site_id format)
     *
     * @throws RuntimeException If called before the website is persisted (no ID)
     */
    public function assignSiteIdentifier(int $agencyId, ?string $customUid = null): void
    {
        throw_unless($this->id, RuntimeException::class, 'Cannot assign a site identifier without a persisted website ID.');

        $format = static::resolveAgencySiteIdFormat($agencyId);

        $customUid = $customUid !== null ? trim($customUid) : null;
        if ($customUid !== null && $customUid !== '') {
            $siteId = static::normalizeCustomUidInput($customUid);
            $this->setMetadata('uid_source', 'custom');
            $this->setMetadata('uid_requested', $customUid);
        } else {
            $siteId = static::generateSiteIdFromRecordId($this->id, $format['prefix'], $format['zero_padding']);
            $this->setMetadata('uid_source', 'generated');
        }

        $this->setMetadata('uid_prefix', $format['prefix']);
        $this->setMetadata('uid_zero_padding', $format['zero_padding']);
        $this->setMetadata('provisioning.server_username', null);
        $adminSlug = static::generateAdminSlug();
        $mediaSlug = static::generateMediaSlug();

        // Generate a random 64-character token for API authentication
        // This is stored encrypted in DB but sent plain to client .env
        $secretToken = Str::random(64);

        $this->forceFill([
            'uid' => $siteId,
            'secret_key' => encrypt($secretToken),
        ]);

        // Store admin_slug and media_slug in metadata
        $this->admin_slug = $adminSlug;
        $this->media_slug = $mediaSlug;

        $this->save();
    }

    /**
     * Check if an update is available for this website.
     *
     * Compares the server's Astero version with the website's version.
     * Returns true if server version is greater than website version.
     */
    public function hasUpdateAvailable(): bool
    {
        // Must have a server and both versions must exist
        if (! $this->server_id || ! $this->server) {
            return false;
        }

        $serverVersion = $this->server->astero_version;
        $websiteVersion = $this->astero_version;

        // If either version is missing, we can't determine if update is available
        if (empty($serverVersion) || empty($websiteVersion)) {
            return false;
        }

        // Use version_compare for proper semantic versioning comparison
        // Returns 1 if server version > website version
        return version_compare($serverVersion, $websiteVersion, '>');
    }

    protected function casts(): array
    {
        return [
            'server_id' => 'integer',
            'agency_id' => 'integer',
            'niches' => 'array',
            'metadata' => 'array',
            'customer_data' => 'array',
            'plan_data' => 'array',
            'status' => WebsiteStatus::class,
            'created_by' => 'integer',
            'updated_by' => 'integer',
            'deleted_by' => 'integer',
            'expired_on' => 'datetime',
        ];
    }

    // =============================================================================
    // QUERY SCOPES
    // =============================================================================

    /**
     * Scope to filter websites marked as agency websites.
     *
     * This filters for websites where metadata.is_agency = true.
     * Centralizes the is_agency metadata check to avoid duplication.
     *
     * Note: The database has a virtual column 'is_agency_flag' that mirrors this JSON value
     * to enable a unique constraint (unique_agency_website), but the virtual column should
     * never be accessed directly in application code - always use this scope or the accessor.
     */
    #[Scope]
    protected function isAgencyWebsite(Builder $query): Builder
    {
        $driver = $query->getModel()->getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            return $query->whereRaw("COALESCE((metadata->>'is_agency')::boolean, false) = true");
        }

        return $query->whereRaw("JSON_EXTRACT(metadata, '$.is_agency') = true");
    }

    // =============================================================================
    // ACCESSORS & MUTATORS
    // =============================================================================

    /**
     * Get the primary DNS provider for this website via the polymorphic relationship.
     * Returns the Provider model or null.
     */
    protected function getDnsProviderAttribute(): ?Provider
    {
        return $this->getProvider(Provider::TYPE_DNS);
    }

    /**
     * Get the primary CDN provider for this website.
     */
    protected function getCdnProviderAttribute(): ?Provider
    {
        return $this->getProvider(Provider::TYPE_CDN);
    }

    /**
     * Get the DNS provider ID for form display.
     */
    protected function getDnsProviderIdAttribute(): ?int
    {
        return $this->dns_provider?->id;
    }

    /**
     * Get the CDN provider ID for form display.
     */
    protected function getCdnProviderIdAttribute(): ?int
    {
        return $this->cdn_provider?->id;
    }

    /**
     * Get the decrypted secret key for use in client website .env files.
     *
     * The secret_key is stored encrypted in the database for security,
     * but must be sent as plain text to the client website so they can
     * use it to validate incoming API requests from the platform.
     *
     * @return string|null The plain text secret key, or null if not set
     */
    /**
     * Get customer data as a structured array, merging the indexed ref with the JSON blob.
     *
     * Returns null when no customer is associated.
     * Note: no array_filter — preserve intentionally falsy values.
     */
    protected function getCustomerInfoAttribute(): ?array
    {
        if (! $this->customer_ref && ! $this->customer_data) {
            return null;
        }

        return [
            'ref' => $this->customer_ref,
            ...($this->customer_data ?? []),
        ];
    }

    /**
     * Get plan data as a structured array, merging the indexed ref with the JSON blob.
     *
     * Returns null when no plan is associated.
     * Note: no array_filter — preserve intentionally falsy values (0 quotas, false features).
     */
    protected function getPlanInfoAttribute(): ?array
    {
        if (! $this->plan_ref && ! $this->plan_data) {
            return null;
        }

        return [
            'ref' => $this->plan_ref,
            ...($this->plan_data ?? []),
        ];
    }

    protected function getPlainSecretKeyAttribute(): ?string
    {
        if (! $this->secret_key) {
            return null;
        }

        try {
            return decrypt($this->secret_key);
        } catch (Exception $exception) {
            // If decryption fails, log and return null
            Log::warning('Failed to decrypt secret_key for website', [
                'website_id' => $this->id,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get legacy is_www flag from metadata.
     */
    protected function getIsWwwAttribute(): bool
    {
        return $this->supportsWwwFeature() && $this->storedIsWwwPreference();
    }

    protected function setIsWwwAttribute($value): void
    {
        // Store in metadata structure we planned
        $provisioning = $this->getMetadata('provisioning') ?? [];
        $provisioning['is_www'] = (bool) $value;
        $this->setMetadata('provisioning', $provisioning);
    }

    protected function storedIsWwwPreference(): bool
    {
        return (bool) ($this->getMetadata('provisioning.is_www') ?? $this->getMetadata('is_www'));
    }

    /**
     * Get skip_cdn flag from metadata - when true, CDN setup step is skipped during provisioning.
     */
    protected function getSkipCdnAttribute(): bool
    {
        return (bool) $this->getMetadata('provisioning.skip_cdn', false);
    }

    protected function setSkipCdnAttribute($value): void
    {
        $provisioning = $this->getMetadata('provisioning') ?? [];
        $provisioning['skip_cdn'] = (bool) $value;
        $this->setMetadata('provisioning', $provisioning);
    }

    /**
     * Get skip_dns flag from metadata - when true, DNS setup step is skipped during provisioning.
     */
    protected function getSkipDnsAttribute(): bool
    {
        return (bool) $this->getMetadata('provisioning.skip_dns', false);
    }

    protected function setSkipDnsAttribute($value): void
    {
        $provisioning = $this->getMetadata('provisioning') ?? [];
        $provisioning['skip_dns'] = (bool) $value;
        $this->setMetadata('provisioning', $provisioning);
    }

    /**
     * Get skip_ssl_issue flag from metadata - when true, ACME issuance is skipped.
     */
    protected function getSkipSslIssueAttribute(): bool
    {
        return (bool) $this->getMetadata('provisioning.skip_ssl_issue', false);
    }

    protected function setSkipSslIssueAttribute($value): void
    {
        $provisioning = $this->getMetadata('provisioning') ?? [];
        $provisioning['skip_ssl_issue'] = (bool) $value;
        $this->setMetadata('provisioning', $provisioning);
    }

    /**
     * Resolve the ID generation format for a website from its owning agency.
     *
     * @return array{prefix: string, zero_padding: int}
     */
    protected static function resolveAgencySiteIdFormat(int $agencyId): array
    {
        $agency = Agency::withTrashed()
            ->select(['id', 'website_id_prefix', 'website_id_zero_padding'])
            ->find($agencyId);

        /** @var Agency|null $agency */
        return [
            'prefix' => Agency::normalizeWebsiteIdPrefix($agency?->website_id_prefix),
            'zero_padding' => Agency::normalizeWebsiteIdZeroPadding($agency?->website_id_zero_padding),
        ];
    }

    // =========================================================================
    // METADATA ACCESSORS (Backward Compatibility)
    // These columns have been moved to the metadata JSON field.
    // =========================================================================

    protected function getDnsZoneIdAttribute(): ?string
    {
        return $this->getMetadata('dns_zone_id');
    }

    protected function setDnsZoneIdAttribute(?string $value): void
    {
        $this->setMetadata('dns_zone_id', $value);
    }

    protected function getRecordIdAttribute(): ?string
    {
        return $this->getMetadata('record_id');
    }

    protected function setRecordIdAttribute(?string $value): void
    {
        $this->setMetadata('record_id', $value);
    }

    protected function getPullzoneIdAttribute(): ?int
    {
        $cdn = $this->getMetadata('cdn');
        if (is_array($cdn) && isset($cdn['Id'])) {
            return (int) $cdn['Id'];
        }

        return null;
    }

    protected function getWebsiteCachingAttribute(): bool
    {
        return (bool) $this->getMetadata('website_caching', false);
    }

    protected function setWebsiteCachingAttribute($value): void
    {
        $this->setMetadata('website_caching', (bool) $value);
    }

    protected function getBackupSetupAttribute(): bool
    {
        return (bool) $this->getMetadata('backup_setup', false);
    }

    protected function setBackupSetupAttribute($value): void
    {
        $this->setMetadata('backup_setup', (bool) $value);
    }

    protected function getSetupCompleteFlagAttribute(): bool
    {
        return (bool) $this->getMetadata('setup_complete_flag', false);
    }

    protected function setSetupCompleteFlagAttribute($value): void
    {
        $this->setMetadata('setup_complete_flag', (bool) $value);
    }

    protected function getSkipEmailAttribute(): bool
    {
        return (bool) $this->getMetadata('skip_email', false);
    }

    protected function setSkipEmailAttribute($value): void
    {
        $this->setMetadata('skip_email', (bool) $value);
    }

    /**
     * Get whether this website is the primary agency website.
     *
     * This reads from metadata.is_agency JSON field. When set to true, a database-level
     * unique constraint (via virtual column is_agency_flag) ensures only ONE website
     * per agency can have this flag enabled.
     *
     * @return bool True if this is the agency's primary website
     */
    protected function getIsAgencyAttribute(): bool
    {
        return (bool) $this->getMetadata('is_agency', false);
    }

    /**
     * Set whether this website is the primary agency website.
     *
     * When set to true, the database virtual column is_agency_flag will auto-update
     * to 1, triggering the unique_agency_website constraint. If another website for
     * the same agency already has is_agency=true, the database will reject the save
     * with a unique constraint violation error.
     *
     * @param  bool  $value  Whether this should be the agency's primary website
     */
    protected function setIsAgencyAttribute($value): void
    {
        $this->setMetadata('is_agency', (bool) $value);
    }

    protected function getUseDnsManagementAttribute(): bool
    {
        return (bool) $this->getMetadata('use_dns_management', false);
    }

    protected function setUseDnsManagementAttribute($value): void
    {
        $this->setMetadata('use_dns_management', (bool) $value);
    }

    protected function getDbNameAttribute(): ?string
    {
        return $this->getMetadata('db_name');
    }

    protected function setDbNameAttribute(?string $value): void
    {
        $this->setMetadata('db_name', $value);
    }

    protected function getAdminSlugAttribute(): ?string
    {
        return $this->getMetadata('admin_slug');
    }

    protected function setAdminSlugAttribute(?string $value): void
    {
        $this->setMetadata('admin_slug', $value);
    }

    protected function getMediaSlugAttribute(): ?string
    {
        return $this->getMetadata('media_slug');
    }

    protected function setMediaSlugAttribute(?string $value): void
    {
        $this->setMetadata('media_slug', $value);
    }

    protected function getSiteIdPrefixAttribute(): string
    {
        $metadataPrefix = $this->getMetadata('uid_prefix');

        return Agency::normalizeWebsiteIdPrefix(
            is_string($metadataPrefix) ? $metadataPrefix : null
        );
    }

    protected function getSiteIdZeroPaddingAttribute(): int
    {
        $metadataPadding = $this->getMetadata('uid_zero_padding');

        return Agency::normalizeWebsiteIdZeroPadding(
            is_numeric($metadataPadding) ? (int) $metadataPadding : null
        );
    }

    // =========================================================================
    // COMPUTED ACCESSORS
    // These are derived from other fields or relationships.
    // =========================================================================

    /**
     * Backward compatibility accessor for site_id (now uid).
     *
     * @deprecated Use uid instead
     */
    protected function getSiteIdAttribute(): ?string
    {
        return $this->uid;
    }

    /**
     * Get the website username (same as uid, used by Hestia commands).
     */
    protected function getWebsiteUsernameAttribute(): ?string
    {
        $provisioningUsername = $this->getMetadata('provisioning.server_username');
        if (is_string($provisioningUsername) && $provisioningUsername !== '') {
            return $provisioningUsername;
        }

        return $this->uid;
    }

    /**
     * Get the provider from the associated server.
     */
    protected function getProviderAttribute(): ?string
    {
        // @phpstan-ignore-next-line nullsafe.neverNull
        return $this->server?->driver ?? 'hestia';
    }

    /**
     * Get the server's Astero version (for display purposes).
     */
    protected function getServerVersionAttribute(): ?string
    {
        return $this->server?->astero_version;
    }
}
