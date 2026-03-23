<?php

declare(strict_types=1);

namespace Modules\Customers\Models;

use App\Enums\Status;
use App\Models\Address;
use App\Models\User;
use App\Traits\ActivityTrait;
use App\Traits\AddressableTrait;
use App\Traits\AuditableTrait;
use App\Traits\HasMetadata;
use App\Traits\HasNotes;
use App\Traits\HasStatusAccessors;
use App\Traits\InteractWithCustomMedia;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Customers\Database\Factories\CustomerFactory;
use Modules\Customers\Enums\AnnualRevenue;
use Modules\Customers\Enums\CustomerGroup;
use Modules\Customers\Enums\CustomerSource;
use Modules\Customers\Enums\CustomerTier;
use Modules\Customers\Enums\Industry;
use Modules\Customers\Enums\OrganizationSize;
use Spatie\MediaLibrary\HasMedia;

/**
 * @property int $id
 * @property int|null $user_id
 * @property Industry|string|null $industry
 * @property CustomerGroup|string|null $customer_group
 * @property int|null $account_manager_id
 * @property string|null $type
 * @property string|null $unique_id
 * @property string|null $company_name
 * @property string|null $contact_first_name
 * @property string|null $contact_last_name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $phone_code
 * @property string|null $billing_email
 * @property string|null $billing_phone
 * @property string|null $tax_id
 * @property string|null $website
 * @property string|null $logo
 * @property OrganizationSize|string|null $org_size
 * @property AnnualRevenue|string|null $revenue
 * @property CustomerSource|string|null $source
 * @property CustomerTier|string|null $tier
 * @property array<int|string, mixed>|null $tags
 * @property string|null $currency
 * @property string|null $language
 * @property string|null $description
 * @property Status|string|null $status
 * @property array<string, mixed>|null $metadata
 * @property bool $opt_in_marketing
 * @property bool $do_not_call
 * @property bool $do_not_email
 * @property Carbon|null $last_contacted_at
 * @property Carbon|null $next_action_date
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read string|null $contact_name
 * @property-read string $company_name_display
 * @property-read string|null $status_label
 * @property-read string|null $status_class
 * @property-read User|null $user
 * @property-read User|null $accountManager
 * @property-read Collection<int, CustomerContact> $contacts
 * @property-read Collection<int, Address> $addresses
 */
class Customer extends Model implements HasMedia
{
    use ActivityTrait;
    use AddressableTrait;
    use AuditableTrait;
    use HasFactory;
    use HasMetadata;
    use HasNotes;
    use HasStatusAccessors;
    use InteractWithCustomMedia;
    use SoftDeletes;

    protected $table = 'customers_customers';

    protected $fillable = [
        'user_id',
        'industry',
        'customer_group',
        'account_manager_id',
        'type',
        'unique_id',
        'company_name',
        'contact_first_name',
        'contact_last_name',
        'email',
        'phone',
        'phone_code',
        'billing_email',
        'billing_phone',
        'tax_id',
        'website',
        'logo',
        'org_size',
        'revenue',
        'source',
        'tier',
        'tags',
        'currency',
        'language',
        'description',
        'status',
        'metadata',
        'opt_in_marketing',
        'do_not_call',
        'do_not_email',
        'last_contacted_at',
        'next_action_date',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $appends = [
        'status_label',
        'status_badge',
        'status_class',
        'contact_name',
        'company_name_display',
    ];

    public static function generateUniqueId(?int $id = null): string
    {
        $prefix = 'CUS';
        $numericId = $id ?? (int) (static::withTrashed()->max('id') ?? 0) + 1;

        return $prefix.str_pad((string) $numericId, 5, '0', STR_PAD_LEFT);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function accountManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'account_manager_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(CustomerContact::class, 'customer_id');
    }

    public function addresses(): MorphMany
    {
        return $this->morphMany(Address::class, 'addressable');
    }

    public function primaryAddress(): MorphMany
    {
        return $this->addresses()->where('is_primary', true);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'account_manager_id' => 'integer',
            'org_size' => OrganizationSize::class,
            'revenue' => AnnualRevenue::class,
            'source' => CustomerSource::class,
            'tier' => CustomerTier::class,
            'industry' => Industry::class,
            'customer_group' => CustomerGroup::class,
            'tags' => 'array',
            'opt_in_marketing' => 'boolean',
            'do_not_call' => 'boolean',
            'do_not_email' => 'boolean',
            'status' => Status::class,
            'metadata' => 'array',
            'last_contacted_at' => 'datetime',
            'next_action_date' => 'datetime',
            'created_by' => 'integer',
            'updated_by' => 'integer',
            'deleted_by' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::created(function (self $customer): void {
            if (empty($customer->unique_id)) {
                $customer->forceFill([
                    'unique_id' => self::generateUniqueId($customer->id),
                ])->saveQuietly();
            }
        });
    }

    protected static function newFactory(): CustomerFactory
    {
        return CustomerFactory::new();
    }

    protected function contactName(): Attribute
    {
        return Attribute::make(
            get: fn () => trim(implode(' ', array_filter([
                $this->contact_first_name,
                $this->contact_last_name,
            ]))) ?: null
        );
    }

    protected function companyNameDisplay(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->company_name ?: ($this->contact_name ?: 'Individual')
        );
    }
}
