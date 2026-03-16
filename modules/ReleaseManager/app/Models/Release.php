<?php

namespace Modules\ReleaseManager\Models;

use App\Models\User;
use App\Traits\AuditableTrait;
use App\Traits\HasMetadata;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\ReleaseManager\Models\Presenters\ReleasePresenter;
use Modules\ReleaseManager\Models\QueryBuilders\ReleaseQueryBuilder;

/**
 * @property int $id
 * @property string $package_identifier
 * @property string $version
 * @property string|null $version_type
 * @property string|null $release_type
 * @property string|null $status
 * @property Carbon|null $release_at
 * @property string|null $change_log
 * @property string|null $release_link
 * @property string|null $file_name
 * @property string|null $checksum
 * @property int|null $file_size
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read string $status_label
 * @property-read string $status_badge
 * @property-read string|null $file_size_formatted
 */
class Release extends Model
{
    use AuditableTrait;
    use HasFactory;
    use HasMetadata;
    use ReleasePresenter;
    use SoftDeletes;

    protected $table = 'release_manager_releases';

    protected $fillable = [
        'package_identifier',
        'version',
        'version_type',
        'release_type',
        'status',
        'release_at',
        'change_log',
        'release_link',
        'file_name',
        'checksum',
        'file_size',
        'metadata',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $appends = [
        'status_label',
        'status_badge',
        'file_size_formatted',
    ];

    // =============================================================================
    // RELATIONSHIPS
    // =============================================================================

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

    // =============================================================================
    // QUERY BUILDER
    // =============================================================================

    public function newEloquentBuilder($query): ReleaseQueryBuilder
    {
        return new ReleaseQueryBuilder($query);
    }

    // =============================================================================
    // HELPERS
    // =============================================================================

    /**
     * Generate the next package identifier for releases.
     */
    public function generatePackageIdentifier(): string
    {
        $startIdentifier = 1000;
        /** @var self|null $latest */
        $latest = self::query()->orderBy('package_identifier', 'desc')->first();

        if ($latest) {
            return (string) ((int) $latest->package_identifier + 1);
        }

        return (string) $startIdentifier;
    }

    /**
     * Get the latest published release for a specific type and package identifier.
     * Orders by semantic version (major.minor.patch) descending.
     */
    public static function getLatestRelease(string $type, string $packageIdentifier): ?self
    {
        $query = self::query()->where('release_type', $type)
            ->where('package_identifier', $packageIdentifier)
            ->where('status', 'published');

        $driver = $query->getModel()->getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            $query
                ->orderByRaw("COALESCE(NULLIF(split_part(version, '.', 1), ''), '0')::int DESC")
                ->orderByRaw("COALESCE(NULLIF(split_part(version, '.', 2), ''), '0')::int DESC")
                ->orderByRaw("COALESCE(NULLIF(split_part(version, '.', 3), ''), '0')::int DESC");
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            $query
                ->orderByRaw("CAST(SUBSTRING_INDEX(version, '.', 1) AS UNSIGNED) DESC")
                ->orderByRaw("CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(version, '.', 2), '.', -1) AS UNSIGNED) DESC")
                ->orderByRaw("CAST(SUBSTRING_INDEX(version, '.', -1) AS UNSIGNED) DESC");
        } else {
            $query->orderByDesc('version');
        }

        /** @var Release|null $release */
        $release = $query->first();

        return $release;
    }

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'release_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
            'metadata' => 'array',
            'file_size' => 'integer',
        ];
    }

    // =============================================================================
    // ACCESSORS
    // =============================================================================

    protected function getStatusLabelAttribute(): string
    {
        return ucfirst((string) ($this->status ?? 'Unknown'));
    }

    protected function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'published' => 'success',
            'draft' => 'warning',
            'deprecate' => 'secondary',
            'trash' => 'danger',
            default => 'secondary',
        };
    }

    protected function getFileSizeFormattedAttribute(): ?string
    {
        if (! $this->file_size) {
            return null;
        }

        $bytes = (float) $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2).' '.$units[$i];
    }

    // =============================================================================
    // SCOPES
    // =============================================================================

    /**
     * Scope to filter by release type.
     */
    #[Scope]
    protected function ofType(Builder $query, string $type): Builder
    {
        return $query->where('release_type', $type);
    }

    /**
     * Scope to get only published releases.
     */
    #[Scope]
    protected function published(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    /**
     * Scope to get only draft releases.
     */
    #[Scope]
    protected function draft(Builder $query): Builder
    {
        return $query->where('status', 'draft');
    }
}
