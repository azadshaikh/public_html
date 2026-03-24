<?php

declare(strict_types=1);

namespace Modules\AIRegistry\Models;

use App\Traits\AuditableTrait;
use App\Traits\HasMetadata;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * AiProvider Model
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string|null $docs_url
 * @property string|null $api_key_url
 * @property array<int, string>|null $capabilities
 * @property bool $is_active
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, AiModel> $models
 * @property-read Collection<int, AiModel> $activeModels
 */
class AiProvider extends Model
{
    use AuditableTrait;
    use HasFactory;
    use HasMetadata;
    use SoftDeletes;

    protected $table = 'airegistry_providers';

    protected $fillable = [
        'slug',
        'name',
        'docs_url',
        'api_key_url',
        'capabilities',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'capabilities' => 'array',
            'is_active' => 'boolean',
        ];
    }

    // ================================================================
    // RELATIONSHIPS
    // ================================================================

    /** @return HasMany<AiModel, $this> */
    public function models(): HasMany
    {
        return $this->hasMany(AiModel::class, 'provider_id');
    }

    /** @return HasMany<AiModel, $this> */
    public function activeModels(): HasMany
    {
        return $this->hasMany(AiModel::class, 'provider_id')->where('is_active', true);
    }

    // ================================================================
    // SCOPES
    // ================================================================

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    // ================================================================
    // HELPERS
    // ================================================================

    public function hasCapability(string $capability): bool
    {
        return in_array($capability, $this->capabilities ?? [], true);
    }
}
