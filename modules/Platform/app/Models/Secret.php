<?php

namespace Modules\Platform\Models;

use App\Models\User;
use App\Traits\AuditableTrait;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $secretable_type
 * @property int $secretable_id
 * @property string $key
 * @property string|null $username
 * @property string $type
 * @property string|null $value
 * @property array<string, mixed>|null $metadata
 * @property bool $is_active
 * @property Carbon|null $expires_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read string|null $decrypted_value
 * @property-read bool $is_expired
 */
class Secret extends Model
{
    use AuditableTrait;
    use HasFactory;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'platform_secrets';

    protected $fillable = [
        'secretable_type',
        'secretable_id',
        'key',
        'username',
        'type',
        'value',
        'metadata',
        'is_active',
        'expires_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $hidden = [
        'value',
    ];

    protected $appends = [
        'is_expired',
        'type_label',
        'type_color',
    ];

    // =============================================================================
    // RELATIONSHIPS
    // =============================================================================

    public function secretable(): MorphTo
    {
        return $this->morphTo();
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
     * Get a specific metadata value by key.
     */
    public function getMetadata(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'is_active' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    // =============================================================================
    // ACCESSORS
    // =============================================================================

    protected function getTypeLabelAttribute(): string
    {
        $types = config('platform.secret_types');

        return $types[$this->type]['label'] ?? $this->type;
    }

    protected function getTypeColorAttribute(): string
    {
        $types = config('platform.secret_types');

        return $types[$this->type]['color'] ?? 'secondary';
    }

    protected function decryptedValue(): Attribute
    {
        return Attribute::make(
            get: function () {
                try {
                    return decrypt($this->value);
                } catch (Exception) {
                    return null;
                }
            }
        );
    }

    protected function isExpired(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => $this->expires_at && $this->expires_at->isPast()
        );
    }

    // =============================================================================
    // SCOPES
    // =============================================================================

    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    #[Scope]
    protected function expired(Builder $query): Builder
    {
        return $query->whereNotNull('expires_at')->where('expires_at', '<', now());
    }

    #[Scope]
    protected function notExpired(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->whereNull('expires_at')
                ->orWhere('expires_at', '>=', now());
        });
    }

    #[Scope]
    protected function ofType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    #[Scope]
    protected function withKey(Builder $query, string $key): Builder
    {
        return $query->where('key', $key);
    }
}
