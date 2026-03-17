<?php

namespace Modules\Platform\Models;

use App\Models\User;
use App\Traits\AuditableTrait;
use App\Traits\HasMetadata;
use App\Traits\HasNotes;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Platform\Models\Presenters\DomainPresenters;
use Modules\Platform\Models\QueryBuilders\DomainQueryBuilder;
use Modules\Platform\Traits\HasSecrets;
use Modules\Platform\Traits\Providerable;

/**
 * @property int $id
 * @property string $name
 * @property string|null $type
 * @property string|null $status
 * @property int|null $agency_id
 * @property int|null $tld_id
 * @property Carbon|null $registered_date
 * @property Carbon|null $expiry_date
 * @property Carbon|null $updated_date
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read string|null $domain_name
 * @property-read Agency|null $agency
 * @property-read Tld|null $tld
 * @property-read Provider|null $dns_provider
 * @property-read Provider|null $registrar
 */
class Domain extends Model
{
    use AuditableTrait;
    use DomainPresenters;
    use HasFactory;
    use HasMetadata;
    use HasNotes;
    use HasSecrets;
    use Providerable;
    use SoftDeletes;

    protected $table = 'platform_domains';

    protected $guarded = ['id'];

    protected $appends = [
        'domain_name',
        'status_label',
        'status_badge',
        'type_label',
        'type_badge',
        'type_color',
    ];

    // ==========================================
    // Relationships
    // ==========================================

    // Note: accountable() relationship has been replaced by secrets() from HasSecrets trait

    public function dnsRecords(): HasMany
    {
        return $this->hasMany(DomainDnsRecord::class, 'domain_id');
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'agency_id');
    }

    /**
     * The server where acme.sh state for this domain lives.
     * Renewal SSHes here, not to website.server_id.
     */
    public function acmeServer(): BelongsTo
    {
        return $this->belongsTo(Server::class, 'acme_server_id');
    }

    public function tld(): BelongsTo
    {
        return $this->belongsTo(Tld::class, 'tld_id');
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
    // Query Builder
    // ==========================================

    public function newEloquentBuilder($query): DomainQueryBuilder
    {
        return new DomainQueryBuilder($query);
    }

    // ==========================================
    // Static Helpers
    // ==========================================

    public static function isValidUrl(string $url): bool
    {
        if (($verifyURL = parse_url($url)) && ! isset($verifyURL['scheme'])) {
            $url = 'http://'.$url;
        }

        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    public static function getTld(string $domain): string
    {
        $domain = 'https://'.$domain;
        $ext = pathinfo($domain, PATHINFO_EXTENSION);

        return '.'.$ext;
    }

    public static function webProtocol(): string
    {
        return (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on') ? 'https' : 'http').'://';
    }

    public static function getDomain(string $url): string
    {
        if (preg_match('#https?://#', $url) === 0) {
            $url = self::webProtocol().$url;
        }

        return strtolower(str_ireplace('www.', '', parse_url($url, PHP_URL_HOST)));
    }

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'registered_date' => 'date',
            'expiry_date' => 'date',
            'updated_date' => 'date',
            'dns_verified_at' => 'datetime',
            'ssl_auto_renew' => 'boolean',
        ];
    }

    /**
     * Get the domain registrar provider via the polymorphic relationship.
     */
    protected function getRegistrarAttribute(): ?Provider
    {
        return $this->getProvider(Provider::TYPE_DOMAIN_REGISTRAR);
    }

    /**
     * Get the DNS provider for this domain.
     */
    protected function getDnsProviderAttribute(): ?Provider
    {
        return $this->getProvider(Provider::TYPE_DNS);
    }

    // ==========================================
    // Accessors
    // ==========================================

    protected function domainName(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => $value ?? ($attributes['name'] ?? null),
            set: fn ($value): array => ['name' => $value]
        );
    }

    protected function typeLabel(): Attribute
    {
        return Attribute::make(
            get: function () {
                $types = config('platform.domain.types');

                return $types[$this->type]['label'] ?? $this->type;
            }
        );
    }

    protected function typeBadge(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                $types = config('platform.domain.types');
                $color = $types[$this->type]['color'] ?? 'secondary';
                $label = $types[$this->type]['label'] ?? $this->type;

                return sprintf("<span class='badge bg-%s'>%s</span>", $color, $label);
            }
        );
    }

    protected function typeColor(): Attribute
    {
        return Attribute::make(
            get: function () {
                $types = config('platform.domain.types');

                return $types[$this->type]['color'] ?? 'secondary';
            }
        );
    }

    protected function statusLabel(): Attribute
    {
        return Attribute::make(
            get: function () {
                $statuses = config('platform.domain.statuses');

                return $statuses[$this->status]['label'] ?? ucfirst($this->status ?? 'Unknown');
            }
        );
    }

    protected function statusBadge(): Attribute
    {
        return Attribute::make(
            get: function () {
                $statuses = config('platform.domain.statuses');

                return $statuses[$this->status]['color'] ?? 'secondary';
            }
        );
    }
}
