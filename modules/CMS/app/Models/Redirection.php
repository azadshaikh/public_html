<?php

namespace Modules\CMS\Models;

use App\Models\User;
use App\Traits\HasMetadata;
use Exception;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\CMS\Models\Presenters\RedirectionPresenters;

/**
 * @property int $id
 * @property int $redirect_type
 * @property string $url_type
 * @property string $match_type
 * @property string $source_url
 * @property string $target_url
 * @property int $hits
 * @property string $status
 * @property string|null $notes
 * @property Carbon|null $expires_at
 * @property Carbon|null $last_hit_at
 * @property array<string, mixed>|null $metadata
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Redirection extends Model
{
    use HasFactory;
    use HasFactory;
    use HasMetadata;
    use RedirectionPresenters;
    use SoftDeletes;

    protected $table = 'cms_redirections';

    protected $fillable = [
        'redirect_type',
        'url_type',
        'match_type',
        'source_url',
        'target_url',
        'hits',
        'last_hit_at',
        'status',
        'expires_at',
        'notes',
        'metadata',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $appends = [
        'status_label',
        'status_badge',
        'redirect_type_label',
        'url_type_label',
        'match_type_label',
    ];

    public static function getActiveRedirections()
    {
        return self::query()
            ->where('status', 'active')
            ->where(function (Builder $query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->orderBy('match_type') // exact first, then wildcard, then regex
            ->orderByDesc(DB::raw('LENGTH(source_url)')) // longer/more specific first
            ->get();
    }

    /**
     * Check if this redirect has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Check if this redirect matches a given path.
     */
    public function matchesPath(string $path): bool|array
    {
        return match ($this->match_type) {
            'exact' => $this->source_url === $path,
            'wildcard' => $this->matchWildcard($path),
            'regex' => $this->matchRegex($path),
            default => false,
        };
    }

    /**
     * Build target URL with optional captured groups.
     */
    public function buildTargetUrl(string $originalPath, array $matches = []): string
    {
        $targetUrl = $this->target_url;

        // Replace captured groups ($1, $2, etc.) in target URL
        if ($matches !== [] && $this->match_type !== 'exact') {
            foreach ($matches as $index => $match) {
                if ($index > 0) { // Skip full match at index 0
                    $targetUrl = str_replace('$'.$index, $match, $targetUrl);
                }
            }
        }

        return $targetUrl;
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
     * Helper to resolve redirect type label from configuration.
     */
    public function resolveRedirectTypeLabel(): string
    {
        $redirectTypes = config('seo.redirect_types', []);

        $matched = Arr::get($redirectTypes, $this->redirect_type);

        if (is_array($matched) && isset($matched['label'])) {
            return $matched['label'];
        }

        return (string) $this->redirect_type;
    }

    protected function casts(): array
    {
        return [
            'redirect_type' => 'integer',
            'hits' => 'integer',
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
            'expires_at' => 'datetime',
            'last_hit_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model): void {
            $model->created_by ??= auth()->id();
            $model->updated_by ??= auth()->id();
        });

        static::updating(function (self $model): void {
            $model->updated_by = auth()->id();
        });

        static::deleting(function (self $model): void {
            if (! $model->isForceDeleting()) {
                $model->deleted_by = auth()->id();
                $model->saveQuietly();
            }
        });
    }

    /**
     * Scope to get only non-expired active redirects.
     */
    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->where(function (Builder $q): void {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Scope to get expired redirects.
     */
    #[Scope]
    protected function expired(Builder $query): Builder
    {
        return $query->whereNotNull('expires_at')
            ->where('expires_at', '<=', now());
    }

    /**
     * Match path using wildcard pattern.
     * Supports * for any characters and ** for any path segments.
     */
    protected function matchWildcard(string $path): bool|array
    {
        $pattern = $this->source_url;

        // Convert wildcard to regex
        $regex = preg_quote($pattern, '#');
        $regex = str_replace('\*\*', '.*', $regex); // ** matches anything including /
        $regex = str_replace('\*', '[^/]*', $regex); // * matches anything except /

        if (preg_match('#^'.$regex.'$#', $path, $matches)) {
            return $matches;
        }

        return false;
    }

    /**
     * Match path using regex pattern.
     */
    protected function matchRegex(string $path): bool|array
    {
        // Source URL is stored as regex pattern (without delimiters)
        try {
            if (preg_match('#'.$this->source_url.'#', $path, $matches)) {
                return $matches;
            }
        } catch (Exception) {
            // Invalid regex, don't match
            return false;
        }

        return false;
    }
}
