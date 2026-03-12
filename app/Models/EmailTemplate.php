<?php

namespace App\Models;

use App\Attributes\ViewData;
use App\Enums\Status;
use App\Traits\AddressableTrait;
use App\Traits\AuditableTrait;
use App\Traits\HasStatusAccessors;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ViewData(
    key: 'statusOptions',
    value: [
        ['value' => 'active', 'label' => 'Active'],
        ['value' => 'inactive', 'label' => 'Inactive'],
        ['value' => 'draft', 'label' => 'Draft'],
    ],
    views: ['*.create', '*.edit', '*.form']
)]
#[ViewData(
    key: 'providerOptions',
    from: 'EmailProvider::getActiveProvidersForSelect()',
    views: ['*.create', '*.edit', '*.form']
)]
class EmailTemplate extends Model
{
    use AddressableTrait;
    use AuditableTrait;
    use HasFactory;
    use HasStatusAccessors;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'subject',
        'message',
        'send_to',
        'provider_id',
        'is_raw',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $appends = [
        'status_label',
        'status_badge',
        'status_class',
        'template_info',
    ];

    // ==================== RELATIONSHIPS ====================

    public function provider(): BelongsTo
    {
        return $this->belongsTo(EmailProvider::class, 'provider_id');
    }

    public function addresses(): MorphMany
    {
        return $this->morphMany(Address::class, 'addressable');
    }

    public function primaryAddress(): MorphMany
    {
        return $this->addresses()->where('is_primary', true);
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

    // ==================== HELPER METHODS ====================

    public function getPrimaryAddress()
    {
        return $this->addresses()->where('is_primary', true)->first();
    }

    public function isRaw(): bool
    {
        return $this->is_raw;
    }

    public function hasProvider(): bool
    {
        return $this->provider_id !== null;
    }

    public function getSendToRecipients(): array
    {
        if (empty($this->send_to)) {
            return [];
        }

        return array_map(trim(...), explode(',', (string) $this->send_to));
    }

    public function canSend(): bool
    {
        return $this->getAttribute('status') === Status::ACTIVE && $this->hasProvider();
    }

    protected function casts(): array
    {
        return [
            'status' => Status::class,
            'is_raw' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    // ==================== ACCESSORS & MUTATORS ====================

    protected function templateInfo(): Attribute
    {
        return Attribute::make(
            get: fn (): string => $this->name.' - '.$this->subject
        );
    }

    /**
     * Customize status color mapping for subtle badge classes.
     */
    protected function getStatusColorName(): string
    {
        $raw = $this->getAttribute('status');
        $status = $raw instanceof Status ? $raw->value : (string) ($raw ?? 'inactive');

        return match ($status) {
            'active' => 'success',
            'inactive' => 'warning',
            'draft' => 'info',
            'trash' => 'danger',
            default => 'secondary',
        };
    }

    // ==================== SCOPES ====================

    #[Scope]
    protected function active($query)
    {
        return $query->where('status', Status::ACTIVE);
    }

    #[Scope]
    protected function inactive($query)
    {
        return $query->where('status', Status::INACTIVE);
    }

    #[Scope]
    protected function byProvider($query, int $providerId)
    {
        return $query->where('provider_id', $providerId);
    }

    #[Scope]
    protected function raw($query)
    {
        return $query->where('is_raw', true);
    }

    #[Scope]
    protected function notRaw($query)
    {
        return $query->where('is_raw', false);
    }
}
