<?php

namespace Modules\CMS\Models;

use App\Models\Address;
use App\Models\CustomMedia;
use App\Models\Modelmetas;
use App\Models\User;
use App\Traits\AddressableTrait;
use App\Traits\AuditableTrait;
use App\Traits\HasMetadata;
use App\Traits\HasNotes;
use App\Traits\RevisionableTrait;
use Exception;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\CMS\Models\Presenters\CmsPostPresenter;
use Modules\CMS\Models\QueryBuilders\CmsPostQueryBuilder;
use Modules\CMS\Services\PermaLinkService;

/**
 * @property int $id
 * @property string|null $type
 * @property string|null $title
 * @property string|null $slug
 * @property string|null $content
 * @property string|null $excerpt
 * @property string|null $subtitle
 * @property string|null $template
 * @property string|null $format
 * @property string|null $excerpt
 * @property string|null $status
 * @property string|null $visibility
 * @property string|null $comment_status
 * @property string|null $css
 * @property string|null $js
 * @property array<string, mixed>|null $seo_data
 * @property array<string, mixed>|null $og_data
 * @property array<string, mixed>|null $schema
 * @property array<string, mixed>|null $metadata
 * @property bool|null $is_cached
 * @property bool|null $is_featured
 * @property bool|null $review_pending
 * @property bool|null $enable_caching
 * @property int|null $reading_seconds
 * @property int|null $author_id
 * @property int|null $parent_id
 * @property int|null $category_id
 * @property int|null $feature_image_id
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property int $post_terms_count
 * @property string|null $post_feature_image_id
 * @property string|null $post_password
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read string|null $status_label
 * @property-read string|null $status_badge
 * @property-read string|null $published_at_formatted
 * @property-read string|null $published_date
 * @property-read string|null $published_date_context
 * @property-read string|null $permalink_url
 * @property-read string|null $author_name
 * @property-read string|null $parent_name
 * @property-read string|null $parent_name_display
 * @property-read int $category_post_count
 * @property-read string $category_post_count_badge
 * @property-read int $tag_post_count
 * @property-read string $tag_post_count_badge
 * @property-read int|null $posts_count
 * @property-read string|null $posts_count_badge
 * @property-read int|null $tag_post_count
 * @property-read string|null $tag_post_count_badge
 * @property-read string|null $featured_image_url
 * @property-read string|null $featured_image_thumbnail
 * @property-read string|null $featured_image_thumbnail_url
 * @property-read User|null $author
 * @property-read CmsPost|null $parent
 * @property-read CmsPost|null $category
 * @property-read User|null $createdBy
 * @property-read CustomMedia|null $featuredImage
 * @property-read Collection<int, CmsPost> $categories
 * @property-read Collection<int, CmsPost> $tags
 * @property-read Collection<int, CmsPost> $terms
 * @property-read \Illuminate\Support\Collection<int, mixed> $revisionHistory
 *
 * @method static CmsPostQueryBuilder query()
 * @method static CmsPostQueryBuilder where(string $column, mixed $operator = null, mixed $value = null, string $boolean = 'and')
 * @method static CmsPostQueryBuilder published()
 */
class CmsPost extends Model
{
    use AddressableTrait;
    use AuditableTrait;
    use CmsPostPresenter;
    use HasFactory;
    use HasMetadata;
    use HasNotes;
    use RevisionableTrait;
    use SoftDeletes;

    protected $revisionCreationsEnabled = false;

    protected $revisionEnabled = true;

    protected $revisionCleanup = true; // Remove old revisions (works only when used with $historyLimit)

    // protected $keepRevisionOf = ['title','status','pagebuilder_data','published_at'];
    protected $dontKeepRevisionOf = ['type', 'created_at', 'updated_at', 'deleted_at', 'created_by', 'updated_by', 'deleted_by'];

    protected $table = 'cms_posts';

    protected $guarded = ['id'];

    /**
     * Appended attributes should only include CHEAP computations.
     * Expensive attributes (that hit the database or traverse relationships)
     * should be added explicitly in Resource classes.
     */
    protected $appends = [
        'status_label',
        'status_badge',
        'published_at_formatted',
        'published_date',
        'published_date_context',
    ];

    /**
     * Get all of the post's metas.
     */
    public function metas(): MorphMany
    {
        return $this->morphMany(Modelmetas::class, 'metable');
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

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(CmsPost::class, 'parent_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CmsPost::class, 'category_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(CmsPost::class, 'parent_id');
    }

    public function terms(): BelongsToMany
    {
        return $this->belongsToMany(CmsPost::class, 'cms_post_terms', 'post_id', 'term_id')
            ->withPivot('term_type')
            ->withTimestamps();
    }

    public function termPosts(): HasMany
    {
        return $this->hasMany(CmsPostTerm::class, 'term_id');
    }

    public function publishedTermPosts(): HasMany
    {
        return $this->hasMany(CmsPostTerm::class, 'term_id')->whereHas('post', function ($query): void {
            $query->where('status', 'published');
        });
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(CmsPost::class, 'cms_post_terms', 'post_id', 'term_id')
            ->withPivot('term_type')
            ->wherePivot('term_type', 'tag')
            ->withTimestamps();
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(CmsPost::class, 'cms_post_terms', 'post_id', 'term_id')
            ->withPivot('term_type')
            ->wherePivot('term_type', 'category')
            ->withTimestamps();
    }

    public function featuredImage(): BelongsTo
    {
        return $this->belongsTo(CustomMedia::class, 'feature_image_id');
    }

    public function addresses(): MorphMany
    {
        return $this->morphMany(Address::class, 'addressable');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published' &&
               $this->published_at !== null &&
               $this->published_at <= now();
    }

    public function newEloquentBuilder($query): CmsPostQueryBuilder
    {
        return new CmsPostQueryBuilder($query);
    }

    public static function getAllData(array $filter_arr = []): LengthAwarePaginator
    {
        return static::query()
            ->with(['author', 'category', 'featuredImage', 'parent'])
            ->withTrashed()
            ->search($filter_arr['search_text'] ?? null)
            ->filterByStatus($filter_arr['status'] ?? null)
            ->filterByType($filter_arr['type'] ?? null)
            ->filterByVisibility($filter_arr['visibility'] ?? null)
            ->filterByAuthor($filter_arr['author_id'] ?? null)
            ->filterByCategory($filter_arr['category_id'] ?? null)
            ->filterByTags($filter_arr['tags'] ?? null)
            ->filterByDate($filter_arr['created_at'] ?? null)
            ->filterByPublishedDate($filter_arr['published_on'] ?? null)
            ->filterByCreator($filter_arr['added_by'] ?? null)
            ->filterBySortable($filter_arr['sortable'] ?? null)
            ->sortBy($filter_arr['sort_by'] ?? null)
            ->orderResults($filter_arr['order'] ?? null)
            ->paginateResults($filter_arr['pagelimit'] ?? null);
    }

    public static function getPostOptions($type = 'post', array $filter_arr = [])
    {
        $query = static::query()
            ->select('id as value', 'title as label')
            ->where('status', 'published')
            ->where('type', $type)
            ->whereNull('deleted_at'); // Exclude trashed items

        if (isset($filter_arr['notid']) && ! empty($filter_arr['notid'])) {
            if (is_array($filter_arr['notid'])) {
                $query->whereNotIn('id', $filter_arr['notid']);
            } else {
                $query->where('id', '!=', $filter_arr['notid']);
            }
        }

        if (isset($filter_arr['nullfield']) && ! empty($filter_arr['nullfield'])) {
            $query->where(function ($q) use ($filter_arr): void {
                $q->whereNull($filter_arr['nullfield']);
                $q->orWhere($filter_arr['nullfield'], '0');
            });
        }

        return $query->get()->toArray();
    }

    public static function getStatistics($type = 'post'): array
    {
        $statistics = [];

        $statisticsdata = self::query()->select('status', DB::raw('COUNT(*) as total'))
            ->where('type', $type)
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $statistics['total'] = array_sum($statisticsdata);
        $statistics['published'] = $statisticsdata['published'] ?? 0;
        $statistics['draft'] = $statisticsdata['draft'] ?? 0;
        $statistics['pending_review'] = $statisticsdata['pending_review'] ?? 0;
        $statistics['scheduled'] = $statisticsdata['scheduled'] ?? 0;
        $statistics['trash'] = self::onlyTrashed()->where('type', $type)->count();

        return $statistics;
    }

    /**
     * @return array<array{id: mixed, url: UrlGenerator|string, updated_at: mixed}>
     */
    public static function getDataForSitemap($post_type, array $post_array = []): array
    {
        $response_data = [];

        $query = self::query()->where('type', $post_type);

        if ($post_type === 'post') {
            $query->with(['category']);
        }

        if (isset($post_array['not_ids']) && ! empty($post_array['not_ids'])) {
            if (is_array($post_array['not_ids'])) {
                $query->whereNotIn('id', $post_array['not_ids']);
            } else {
                $query->where('id', '!=', $post_array['not_ids']);
            }
        }

        if (isset($post_array['not_meta_robot']) && ! empty($post_array['not_meta_robot'])) {
            $metaRobotsColumn = 'seo_data->meta_robots';

            if (is_array($post_array['not_meta_robot'])) {
                $query->where(function ($q) use ($post_array, $metaRobotsColumn): void {
                    $q->whereNotIn($metaRobotsColumn, $post_array['not_meta_robot'])
                        ->orWhereNull($metaRobotsColumn);
                });
            } else {
                $query->where(function ($q) use ($post_array, $metaRobotsColumn): void {
                    $q->where($metaRobotsColumn, '!=', $post_array['not_meta_robot'])
                        ->orWhereNull($metaRobotsColumn);
                });
            }
        }

        if (isset($post_array['status']) && $post_array['status'] !== '') {
            if (is_array($post_array['status'])) {
                $query->whereIn('cms_posts.status', $post_array['status']);
            } else {
                $query->where('cms_posts.status', $post_array['status']);
            }
        }

        /** @var Collection<int, CmsPost> $datalist */
        $datalist = $query->orderBy('id', 'DESC')->get();

        if (count($datalist) > 0) {
            foreach ($datalist as $post) {
                $response_data[] = [
                    'id' => $post->id,
                    'url' => url($post->permalink_url),
                    'updated_at' => $post->updated_at,
                ];
            }
        }

        return $response_data;
    }

    /**
     * @return array<mixed>
     */
    public static function getChildrenIds($id): array
    {
        $response_ids = [];
        $visited = []; // Track visited IDs to prevent circular references
        $maxDepth = 10; // Maximum depth to prevent infinite loops
        $currentDepth = 0;

        // Use a queue-based approach for breadth-first traversal
        $queue = [$id];
        $visited[] = $id; // Mark the initial ID as visited

        while ($queue !== []) {
            // @phpstan-ignore-next-line greaterOrEqual.alwaysFalse
            if ($currentDepth >= $maxDepth) {
                break;
            }

            $current_level_size = count($queue);

            // Process all nodes at current level
            for ($i = 0; $i < $current_level_size; $i++) {
                $parent_id = array_shift($queue);

                // Get direct children of current parent
                /** @var Collection<int, CmsPost> $children */
                $children = self::query()->where('parent_id', $parent_id)
                    ->select('id')
                    ->get();

                foreach ($children as $child) {
                    // Check for circular references
                    if (! in_array($child->id, $visited)) {
                        $response_ids[] = $child->id;
                        $visited[] = $child->id;
                        $queue[] = $child->id; // Add to queue for next level processing
                    }
                }
            }

            $currentDepth++;
        }

        return $response_ids;
    }

    // ==================== PASSWORD PROTECTION ====================

    /**
     * Check if the post is password protected.
     */
    public function isPasswordProtected(): bool
    {
        return ! empty($this->post_password);
    }

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
            'is_featured' => 'boolean',
            'metadata' => 'array',
            'seo_data' => 'array',
            'og_data' => 'array',
        ];
    }

    protected function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /**
     * Scope to filter password protected posts.
     */
    #[Scope]
    protected function passwordProtected($query, bool $protected = true)
    {
        return $protected
            ? $query->whereNotNull('post_password')->where('post_password', '!=', '')
            : $query->where(function ($q): void {
                $q->whereNull('post_password')->orWhere('post_password', '');
            });
    }

    // ==================== ACCESSORS & MUTATORS ====================

    protected function statusLabel(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->trashed()) {
                    return 'Trash';
                }

                return config('cms.post_status.'.$this->status.'.label', ucfirst(str_replace('_', ' ', (string) $this->status)));
            }
        );
    }

    protected function statusBadge(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                if ($this->trashed()) {
                    return '<span class="badge text-bg-danger">Trash</span>';
                }

                $color = config('cms.post_status.'.$this->status.'.color', 'secondary');
                $label = $this->status_label;

                return sprintf('<span class="badge text-bg-%s">%s</span>', $color, $label);
            }
        );
    }

    protected function publishedAtFormatted(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->published_at?->format('M d, Y g:i A')
        );
    }

    protected function categoryPostCount(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->type !== 'category') {
                    return 0;
                }

                // Check if already loaded via withCount
                if (array_key_exists('category_post_count', $this->attributes)) {
                    return $this->attributes['category_post_count'];
                }

                if (array_key_exists('posts_count', $this->attributes)) {
                    return $this->attributes['posts_count'];
                }

                return $this->publishedTermPosts()->count();
            }
        );
    }

    protected function authorName(): Attribute
    {
        return Attribute::make(
            get: function () {
                // If author is already loaded, use it (avoid N+1)
                if ($this->relationLoaded('author') && $this->author) {
                    return $this->author->name;
                }

                // Fallback to createdBy if loaded (for tags/categories that don't have author_id)
                if ($this->relationLoaded('createdBy') && $this->createdBy) {
                    return $this->createdBy->name;
                }

                // Don't trigger additional queries - return default value
                return 'Unknown';
            }
        );
    }

    protected function featuredImageThumbnailUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => get_media_url($this->featuredImage, 'thumbnail', usePlaceholder: true)
        );
    }

    protected function permalinkUrl(): Attribute
    {
        return Attribute::make(
            get: function () {
                // Use PermaLinkService to generate the correct URL
                try {
                    $permalinkService = resolve(PermaLinkService::class);

                    return match ($this->type) {
                        'post', 'page', 'category', 'tag' => $permalinkService->generatePostPermalink($this),
                        default => '#'
                    };
                } catch (Exception $exception) {
                    Log::error('Error generating permalink', [
                        'post_id' => $this->id,
                        'type' => $this->type,
                        'error' => $exception->getMessage(),
                    ]);

                    return '#';
                }
            }
        );
    }

    protected function categoryPostCountBadge(): Attribute
    {
        return Attribute::make(
            get: fn (): string => $this->type === 'category' ? sprintf('<span class="badge bg-info">%d</span>', $this->category_post_count) : ''
        );
    }

    protected function tagPostCountBadge(): Attribute
    {
        return Attribute::make(
            get: fn (): string => $this->type === 'tag' ? sprintf('<span class="badge bg-info">%s</span>', $this->tag_post_count) : ''
        );
    }

    protected function parentName(): Attribute
    {
        return Attribute::make(
            get: function () {
                // Only access parent if already loaded to avoid N+1
                if ($this->relationLoaded('parent')) {
                    $parent = $this->parent;

                    return $parent ? $parent->title : '';
                }

                return '';
            }
        );
    }

    protected function parentNameDisplay(): Attribute
    {
        return Attribute::make(
            get: function () {
                // Only access parent if already loaded to avoid N+1
                if ($this->relationLoaded('parent')) {
                    $parent = $this->parent;

                    return $parent ? $parent->title : '-';
                }

                return '-';
            }
        );
    }

    protected function featuredImageUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => get_media_url($this->featuredImage, 'thumbnail')
        );
    }

    protected function featuredImageThumbnail(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                $url = $this->featured_image_thumbnail_url;

                return $url ? sprintf('<img src="%s" alt="" class="img-thumbnail" style="width: 50px; height: 50px; object-fit: cover;">', $url) : '-';
            }
        );
    }

    /**
     * Mutator for post_password - automatically hashes the password.
     */
    protected function postPassword(): Attribute
    {
        return Attribute::make(
            set: function ($value) {
                // Don't hash if empty or null
                if (empty($value)) {
                    return null;
                }

                // Don't re-hash if already hashed (60 chars for bcrypt)
                if (strlen($value) === 60 && str_starts_with($value, '$2y$')) {
                    return $value;
                }

                return bcrypt($value);
            }
        );
    }

    // =============================================================================
    // SEO DATA ACCESSORS/MUTATORS (stored in seo_data JSON column)
    // =============================================================================

    protected function seoData(): Attribute
    {
        return Attribute::make(
            get: function ($value): array {
                if (is_array($value)) {
                    return $value;
                }

                if (is_string($value) && $value !== '') {
                    $decoded = json_decode($value, true);

                    return is_array($decoded) ? $decoded : [];
                }

                return [];
            },
            set: function ($value) {
                if (is_array($value)) {
                    return json_encode($value, JSON_UNESCAPED_UNICODE);
                }

                if (is_string($value)) {
                    return $value;
                }

                return null;
            }
        );
    }

    protected function metaTitle(): Attribute
    {
        return Attribute::make(
            get: fn () => data_get($this->seo_data, 'meta_title', ''),
            set: function ($value): array {
                $seoData = $this->getSeoDataAttributes();
                $seoData['meta_title'] = $value;

                return ['seo_data' => $this->encodeSeoData($seoData)];
            }
        );
    }

    protected function metaDescription(): Attribute
    {
        return Attribute::make(
            get: fn () => data_get($this->seo_data, 'meta_description', ''),
            set: function ($value): array {
                $seoData = $this->getSeoDataAttributes();
                $seoData['meta_description'] = $value;

                return ['seo_data' => $this->encodeSeoData($seoData)];
            }
        );
    }

    protected function metaRobots(): Attribute
    {
        return Attribute::make(
            get: fn () => data_get($this->seo_data, 'meta_robots', ''),
            set: function ($value): array {
                $seoData = $this->getSeoDataAttributes();
                $seoData['meta_robots'] = $value;

                return ['seo_data' => $this->encodeSeoData($seoData)];
            }
        );
    }

    // =============================================================================
    // OPEN GRAPH DATA ACCESSORS/MUTATORS (stored in og_data JSON column)
    // =============================================================================

    protected function ogData(): Attribute
    {
        return Attribute::make(
            get: function ($value): array {
                if (is_array($value)) {
                    return $value;
                }

                if (is_string($value) && $value !== '') {
                    $decoded = json_decode($value, true);

                    return is_array($decoded) ? $decoded : [];
                }

                return [];
            },
            set: function ($value) {
                if (is_array($value)) {
                    return json_encode($value, JSON_UNESCAPED_UNICODE);
                }

                if (is_string($value)) {
                    return $value;
                }

                return null;
            }
        );
    }

    protected function ogTitle(): Attribute
    {
        return Attribute::make(
            get: fn () => data_get($this->og_data, 'og_title', ''),
            set: function ($value): array {
                $ogData = $this->getOgDataAttributes();
                $ogData['og_title'] = $value;

                return ['og_data' => $this->encodeOgData($ogData)];
            }
        );
    }

    protected function ogDescription(): Attribute
    {
        return Attribute::make(
            get: fn () => data_get($this->og_data, 'og_description', ''),
            set: function ($value): array {
                $ogData = $this->getOgDataAttributes();
                $ogData['og_description'] = $value;

                return ['og_data' => $this->encodeOgData($ogData)];
            }
        );
    }

    protected function ogImage(): Attribute
    {
        return Attribute::make(
            get: fn () => data_get($this->og_data, 'og_image', ''),
            set: function ($value): array {
                $ogData = $this->getOgDataAttributes();
                $ogData['og_image'] = $value;

                return ['og_data' => $this->encodeOgData($ogData)];
            }
        );
    }

    protected function ogUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => data_get($this->og_data, 'og_url', ''),
            set: function ($value): array {
                $ogData = $this->getOgDataAttributes();
                $ogData['og_url'] = $value;

                return ['og_data' => $this->encodeOgData($ogData)];
            }
        );
    }

    protected function postMetaOgTitle(): Attribute
    {
        return Attribute::make(
            get: fn () => data_get($this->og_data, 'og_title', ''),
            set: function ($value): array {
                $ogData = $this->getOgDataAttributes();
                $ogData['og_title'] = $value;

                return ['og_data' => $this->encodeOgData($ogData)];
            }
        );
    }

    protected function postMetaOgDescription(): Attribute
    {
        return Attribute::make(
            get: fn () => data_get($this->og_data, 'og_description', ''),
            set: function ($value): array {
                $ogData = $this->getOgDataAttributes();
                $ogData['og_description'] = $value;

                return ['og_data' => $this->encodeOgData($ogData)];
            }
        );
    }

    protected function postMetaOgImage(): Attribute
    {
        return Attribute::make(
            get: fn () => data_get($this->og_data, 'og_image', ''),
            set: function ($value): array {
                $ogData = $this->getOgDataAttributes();
                $ogData['og_image'] = $value;

                return ['og_data' => $this->encodeOgData($ogData)];
            }
        );
    }

    protected function postMetaOgUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => data_get($this->og_data, 'og_url', ''),
            set: function ($value): array {
                $ogData = $this->getOgDataAttributes();
                $ogData['og_url'] = $value;

                return ['og_data' => $this->encodeOgData($ogData)];
            }
        );
    }

    // =============================================================================
    // SCHEMA ACCESSOR/MUTATOR
    // =============================================================================

    protected function postSchema(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->schema ?? '',
            set: fn ($value): array => [
                'schema' => is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value,
            ]
        );
    }

    protected function featureImage(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->feature_image_id,
            set: fn ($value): array => ['feature_image_id' => $value ?: null]
        );
    }

    private function getSeoDataAttributes(): array
    {
        $raw = $this->getAttributes()['seo_data'] ?? null;

        if (is_array($raw)) {
            return $raw;
        }

        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function encodeSeoData(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    private function getOgDataAttributes(): array
    {
        $raw = $this->getAttributes()['og_data'] ?? null;

        if (is_array($raw)) {
            return $raw;
        }

        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function encodeOgData(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE);
    }
}
