<?php

declare(strict_types=1);

namespace Modules\Customers\Models;

use App\Enums\Status;
use App\Models\User;
use App\Traits\AuditableTrait;
use App\Traits\HasMetadata;
use App\Traits\HasStatusAccessors;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Customers\Database\Factories\CustomerContactFactory;

/**
 * @property int $id
 * @property int|null $customer_id
 * @property int|null $user_id
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $phone_code
 * @property string|null $position
 * @property bool $is_primary
 * @property Status|string|null $status
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read string $full_name
 * @property-read string|null $status_label
 * @property-read string|null $status_class
 * @property-read Customer|null $customer
 * @property-read User|null $user
 */
class CustomerContact extends Model
{
    use AuditableTrait;
    use HasFactory;
    use HasMetadata;
    use HasStatusAccessors;
    use SoftDeletes;

    protected $table = 'customers_customer_contacts';

    protected $fillable = [
        'customer_id',
        'user_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'phone_code',
        'position',
        'is_primary',
        'status',
        'metadata',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $appends = [
        'status_label',
        'status_badge',
        'status_class',
        'full_name',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    protected function casts(): array
    {
        return [
            'customer_id' => 'integer',
            'user_id' => 'integer',
            'is_primary' => 'boolean',
            'status' => Status::class,
            'metadata' => 'array',
            'created_by' => 'integer',
            'updated_by' => 'integer',
            'deleted_by' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    protected static function newFactory(): CustomerContactFactory
    {
        return CustomerContactFactory::new();
    }

    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn (): string => trim(implode(' ', array_filter([
                $this->first_name,
                $this->last_name,
            ])))
        );
    }
}
