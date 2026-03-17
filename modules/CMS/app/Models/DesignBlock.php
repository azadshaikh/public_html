<?php

namespace Modules\CMS\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Design Block Model
 *
 * Represents reusable UI components stored in cms_posts table with type='design_block'.
 * Extends CmsPost to leverage unified content management structure.
 *
 * Field Mappings (form → database):
 * - html → content
 * - description → excerpt
 * - scripts → js
 * - category_id → metadata->category
 * - design_type, block_type, design_system, preview_url, preview_image_url, version → metadata (JSON)
 *
 * Inherited from CmsPost:
 * - AuditableTrait (created_by, updated_by, deleted_by relationships)
 * - HasMetadata (metadata JSON handling)
 * - SoftDeletes (deleted_at handling)
 * - HasNotes (polymorphic notes)
 *
 * @property string $title
 * @property string $slug
 * @property string|null $excerpt (mapped to/from description)
 * @property string|null $content (mapped to/from html)
 * @property string|null $css
 * @property string|null $js (mapped to/from scripts)
 * @property string $status
 * @property array $metadata
 * @property string|null $uuid
 * @property string|null $design_type
 * @property string|null $block_type
 * @property string|null $design_system
 * @property string|null $category_id
 * @property string|null $category_name
 * @property string|null $html
 */
class DesignBlock extends CmsPost
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'excerpt', // description from form maps to excerpt
        'content', // html from form maps to content
        'css',
        'js', // scripts from form maps to js
        'status',
        'metadata', // stores: design_type, block_type, design_system, category, preview_url, preview_image_url, version
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $appends = [
        'status_label',
        'status_badge',
        'category_name',
        'creator_name',
    ];

    // ==================== HELPER METHODS ====================

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function getPreviewImageUrl(): ?string
    {
        $previewImageUrl = $this->metadata['preview_image_url'] ?? null;

        if (! $previewImageUrl) {
            return null;
        }

        // If it's already a full URL, return it
        if (str_starts_with((string) $previewImageUrl, 'http')) {
            return $previewImageUrl;
        }

        // Media picker may store a media ID (numeric). Resolve it via the media helper.
        if (is_numeric($previewImageUrl) && function_exists('get_media_url')) {
            return get_media_url($previewImageUrl);
        }

        // If media helper exists, prefer it for non-URL strings too (IDs, paths, etc.)
        if (function_exists('get_media_url')) {
            return get_media_url($previewImageUrl);
        }

        // Otherwise, prepend storage path
        return asset('storage/'.$previewImageUrl);
    }

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public static function boot(): void
    {
        parent::boot();

        // Ensure DesignBlock always filters for design_block type
        static::addGlobalScope('design_block_type', function ($query): void {
            $query->where('type', 'design_block');
        });

        // Set type on creation (audit fields are handled by CmsPost's AuditableTrait)
        static::creating(function (self $model): void {
            $model->type = 'design_block';
        });
    }

    // Relationships (createdBy, updatedBy, deletedBy) are inherited from CmsPost

    // ==================== ACCESSORS & MUTATORS ====================

    protected function statusLabel(): Attribute
    {
        return Attribute::make(
            get: fn (): string => match ($this->status) {
                'published' => 'Published',
                'draft' => 'Draft',
                default => ucfirst($this->status ?? 'Unknown'),
            }
        );
    }

    protected function statusBadge(): Attribute
    {
        return Attribute::make(
            get: fn (): string => match ($this->status) {
                'published' => '<span class="badge bg-success-subtle text-success">Published</span>',
                'draft' => '<span class="badge bg-warning-subtle text-warning">Draft</span>',
                default => '<span class="badge bg-secondary-subtle text-secondary">'.e(ucfirst((string) ($this->status ?? 'Unknown'))).'</span>',
            },
        );
    }

    protected function categoryName(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (! $this->category_id) {
                    return 'Uncategorized';
                }

                $categories = config('cms.design_block_categories', []);

                return $categories[$this->category_id] ?? ucfirst(str_replace('-', ' ', $this->category_id));
            }
        );
    }

    protected function creatorName(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                if ($this->createdBy) {
                    return trim($this->createdBy->first_name.' '.$this->createdBy->last_name);
                }

                return 'Unknown';
            }
        );
    }

    // ==================== DESIGN BLOCK METADATA ACCESSORS ====================

    protected function designType(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->metadata['design_type'] ?? 'section',
            set: fn ($value): array => $this->metadata = array_merge($this->metadata ?? [], ['design_type' => $value])
        );
    }

    protected function blockType(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->metadata['block_type'] ?? 'static',
            set: fn ($value): array => $this->metadata = array_merge($this->metadata ?? [], ['block_type' => $value])
        );
    }

    protected function designSystem(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->metadata['design_system'] ?? 'bootstrap',
            set: fn ($value): array => $this->metadata = array_merge($this->metadata ?? [], ['design_system' => $value])
        );
    }

    protected function previewUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->metadata['preview_url'] ?? null,
            set: fn ($value): array => $this->metadata = array_merge($this->metadata ?? [], ['preview_url' => $value])
        );
    }

    protected function previewImageUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->metadata['preview_image_url'] ?? null,
            set: fn ($value): array => $this->metadata = array_merge($this->metadata ?? [], ['preview_image_url' => $value])
        );
    }

    protected function version(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->metadata['version'] ?? 1,
            set: fn ($value): array => $this->metadata = array_merge($this->metadata ?? [], ['version' => (int) $value])
        );
    }

    // ==================== LEGACY FIELD MAPPINGS ====================

    protected function html(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->content,
            set: fn ($value) => $this->content = $value
        );
    }

    protected function scripts(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->js,
            set: fn ($value) => $this->js = $value
        );
    }

    protected function categoryId(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->metadata['category'] ?? 'hero',
            set: fn ($value): array => $this->metadata = array_merge($this->metadata ?? [], ['category' => $value])
        );
    }

    protected function description(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->excerpt,
            set: fn ($value) => $this->excerpt = $value
        );
    }

    // ==================== SCOPES ====================

    protected function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    #[Scope]
    protected function draft(Builder $query): Builder
    {
        return $query->where('status', 'draft');
    }

    #[Scope]
    protected function byDesignType(Builder $query, string $designType): Builder
    {
        return $query->whereJsonContains('metadata->design_type', $designType);
    }

    #[Scope]
    protected function byBlockType(Builder $query, string $blockType): Builder
    {
        return $query->whereJsonContains('metadata->block_type', $blockType);
    }

    #[Scope]
    protected function byCategory(Builder $query, string $categoryId): Builder
    {
        return $query->whereJsonContains('metadata->category', $categoryId);
    }

    #[Scope]
    protected function byDesignSystem(Builder $query, string $designSystem): Builder
    {
        return $query->whereJsonContains('metadata->design_system', $designSystem);
    }
}
