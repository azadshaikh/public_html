<?php

declare(strict_types=1);

namespace Modules\AIRegistry\Models;

use App\Traits\AuditableTrait;
use App\Traits\HasMetadata;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * AiModel Model
 *
 * @property int $id
 * @property int $provider_id
 * @property string $slug
 * @property string $name
 * @property string|null $description
 * @property int|null $context_window
 * @property int|null $max_output_tokens
 * @property float|null $input_cost_per_1m
 * @property float|null $output_cost_per_1m
 * @property array<int, string>|null $input_modalities
 * @property array<int, string>|null $output_modalities
 * @property string|null $tokenizer
 * @property bool|null $is_moderated
 * @property array<int, string>|null $supported_parameters
 * @property array<int, string>|null $capabilities
 * @property array<int, string>|null $categories
 * @property bool $is_active
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read AiProvider $provider
 */
class AiModel extends Model
{
    use AuditableTrait;
    use HasFactory;
    use HasMetadata;
    use SoftDeletes;

    protected $table = 'airegistry_models';

    protected $fillable = [
        'provider_id',
        'slug',
        'name',
        'description',
        'context_window',
        'max_output_tokens',
        'input_cost_per_1m',
        'output_cost_per_1m',
        'input_modalities',
        'output_modalities',
        'tokenizer',
        'is_moderated',
        'supported_parameters',
        'capabilities',
        'categories',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'context_window' => 'integer',
            'max_output_tokens' => 'integer',
            'input_cost_per_1m' => 'decimal:4',
            'output_cost_per_1m' => 'decimal:4',
            'input_modalities' => 'array',
            'output_modalities' => 'array',
            'is_moderated' => 'boolean',
            'supported_parameters' => 'array',
            'capabilities' => 'array',
            'categories' => 'array',
            'is_active' => 'boolean',
        ];
    }

    // ================================================================
    // RELATIONSHIPS
    // ================================================================

    /** @return BelongsTo<AiProvider, $this> */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class, 'provider_id');
    }

    // ================================================================
    // SCOPES
    // ================================================================

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForProvider(Builder $query, int $providerId): Builder
    {
        return $query->where('provider_id', $providerId);
    }

    // ================================================================
    // HELPERS
    // ================================================================

    public function hasCapability(string $capability): bool
    {
        return in_array($capability, $this->capabilities ?? [], true);
    }

    /**
     * Format context window for display (e.g. "128K").
     */
    public function getFormattedContextWindow(): string
    {
        if ($this->context_window === null) {
            return '—';
        }

        if ($this->context_window >= 1000) {
            return round($this->context_window / 1000).'K';
        }

        return (string) $this->context_window;
    }
}
