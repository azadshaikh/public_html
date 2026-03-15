<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NoteType;
use App\Enums\NoteVisibility;
use App\Traits\AuditableTrait;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Note Model - Polymorphic notes for any model
 *
 * @property int $id
 * @property string $noteable_type
 * @property int $noteable_id
 * @property string $content
 * @property NoteType $type
 * @property NoteVisibility $visibility
 * @property bool $is_pinned
 * @property Carbon|null $pinned_at
 * @property int|null $pinned_by
 * @property array|null $metadata
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Model $noteable
 * @property-read User|null $author
 * @property-read User|null $editor
 * @property-read User|null $pinner
 * @property-read string $excerpt
 * @property-read bool $is_editable
 * @property-read bool $is_deletable
 */
class Note extends Model
{
    use AuditableTrait;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'notes';

    protected $fillable = [
        'noteable_type',
        'noteable_id',
        'content',
        'type',
        'visibility',
        'is_pinned',
        'pinned_at',
        'pinned_by',
        'metadata',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * Get the parent noteable model.
     */
    public function noteable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the user who created this note.
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this note.
     */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get the user who pinned this note.
     */
    public function pinner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pinned_by');
    }

    /**
     * Alias for author() - backwards compatibility.
     */
    public function owner(): BelongsTo
    {
        return $this->author();
    }

    // =========================================================================
    // PIN METHODS
    // =========================================================================

    /**
     * Pin this note.
     */
    public function pin(): void
    {
        $this->update([
            'is_pinned' => true,
            'pinned_at' => now(),
            'pinned_by' => auth()->id(),
        ]);
    }

    /**
     * Unpin this note.
     */
    public function unpin(): void
    {
        $this->update([
            'is_pinned' => false,
            'pinned_at' => null,
            'pinned_by' => null,
        ]);
    }

    /**
     * Toggle pin status.
     */
    public function togglePin(): void
    {
        $this->is_pinned ? $this->unpin() : $this->pin();
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Check if this note is a system-generated note.
     */
    public function isSystem(): bool
    {
        return $this->type === NoteType::System;
    }

    /**
     * Check if this note is private.
     */
    public function isPrivate(): bool
    {
        return $this->visibility === NoteVisibility::Private;
    }

    /**
     * Check if this note is visible to customers.
     */
    public function isCustomerVisible(): bool
    {
        return $this->visibility === NoteVisibility::Customer;
    }

    /**
     * Get metadata value by key.
     */
    public function getMeta(string $key, mixed $default = null): mixed
    {
        return data_get($this->metadata, $key, $default);
    }

    /**
     * Set metadata value by key.
     */
    public function setMeta(string $key, mixed $value): void
    {
        $metadata = $this->metadata ?? [];
        data_set($metadata, $key, $value);
        $this->metadata = $metadata;
    }

    public static function sanitizeContent(string $content): string
    {
        $trimmedContent = trim($content);

        if ($trimmedContent === '') {
            return '';
        }

        if (! self::containsHtml($trimmedContent)) {
            return $trimmedContent;
        }

        // Remove dangerous elements and their content before stripping tags
        $cleaned = preg_replace('/<(script|style|iframe|object|embed|form|input|textarea|select|button)\b[^>]*>.*?<\/\1>/is', '', $trimmedContent) ?? $trimmedContent;
        $cleaned = preg_replace('/<(script|style|iframe|object|embed|form|input|textarea|select|button)\b[^>]*\/?>/is', '', $cleaned);

        return trim(strip_tags(
            $cleaned,
            '<a><blockquote><br><em><h1><h2><h3><hr><i><li><ol><p><pre><strong><u><ul>',
        ));
    }

    public static function contentHasText(string $content): bool
    {
        $plainText = html_entity_decode(
            strip_tags(self::contentHtmlFromRaw($content)),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8',
        );
        $plainText = str_replace("\xc2\xa0", ' ', $plainText);
        $normalizedText = preg_replace('/\s+/u', ' ', $plainText) ?? $plainText;

        return trim($normalizedText) !== '';
    }

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'type' => NoteType::class,
            'visibility' => NoteVisibility::class,
            'is_pinned' => 'boolean',
            'pinned_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    /**
     * Scope to pinned notes only.
     */
    #[Scope]
    protected function pinned(Builder $query): Builder
    {
        return $query->where('is_pinned', true);
    }

    /**
     * Scope to unpinned notes only.
     */
    #[Scope]
    protected function unpinned(Builder $query): Builder
    {
        return $query->where('is_pinned', false);
    }

    /**
     * Scope to notes of a specific type.
     */
    #[Scope]
    protected function ofType(Builder $query, NoteType $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to notes with specific visibility.
     */
    #[Scope]
    protected function visibleTo(Builder $query, NoteVisibility $visibility): Builder
    {
        return $query->where('visibility', $visibility);
    }

    /**
     * Scope to internal notes only (private + team).
     */
    #[Scope]
    protected function internal(Builder $query): Builder
    {
        return $query->whereIn('visibility', [
            NoteVisibility::Private,
            NoteVisibility::Team,
        ]);
    }

    /**
     * Scope to customer visible notes.
     */
    #[Scope]
    protected function customerVisible(Builder $query): Builder
    {
        return $query->where('visibility', NoteVisibility::Customer);
    }

    /**
     * Scope to notes by a specific author.
     */
    #[Scope]
    protected function byAuthor(Builder $query, int $userId): Builder
    {
        return $query->where('created_by', $userId);
    }

    /**
     * Default ordering: pinned first, then by created_at desc.
     */
    #[Scope]
    protected function defaultOrder(Builder $query): Builder
    {
        return $query->orderByDesc('is_pinned')->latest();
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    /**
     * Get a truncated excerpt of the content.
     */
    protected function getExcerptAttribute(): string
    {
        return Str::limit(strip_tags($this->content ?? ''), 100);
    }

    protected function getContentHtmlAttribute(): string
    {
        return self::contentHtmlFromRaw((string) ($this->content ?? ''));
    }

    /**
     * Check if current user can edit this note.
     */
    protected function getIsEditableAttribute(): bool
    {
        return $this->created_by === auth()->id();
    }

    /**
     * Check if current user can delete this note.
     */
    protected function getIsDeletableAttribute(): bool
    {
        if ($this->created_by === auth()->id()) {
            return true;
        }

        return (bool) auth()->user()?->can('delete_any_notes');
    }

    private static function contentHtmlFromRaw(string $content): string
    {
        $sanitizedContent = self::sanitizeContent($content);

        if ($sanitizedContent === '') {
            return '';
        }

        if (! self::containsHtml($sanitizedContent)) {
            return self::plainTextToHtml($sanitizedContent);
        }

        // Inline-only HTML (e.g. legacy content with <strong> but no <p> wrapper)
        // must be wrapped in a block element for proper rendering.
        if (! self::hasBlockElement($sanitizedContent)) {
            return '<p>'.$sanitizedContent.'</p>';
        }

        return $sanitizedContent;
    }

    private static function hasBlockElement(string $content): bool
    {
        return (bool) preg_match('/<(p|h[1-6]|blockquote|div|ul|ol|li|hr|pre|table)[\/\s>]/i', $content);
    }

    private static function containsHtml(string $content): bool
    {
        return preg_match('/<[^>]+>/', $content) === 1;
    }

    private static function plainTextToHtml(string $content): string
    {
        $paragraphs = preg_split('/\R{2,}/', trim($content)) ?: [];
        $htmlParagraphs = array_values(array_filter(array_map(
            static function (string $paragraph): string {
                $escapedParagraph = e(trim($paragraph));

                if ($escapedParagraph === '') {
                    return '';
                }

                return '<p>'.str_replace(["\r\n", "\r", "\n"], '<br />', $escapedParagraph).'</p>';
            },
            $paragraphs,
        )));

        return implode('', $htmlParagraphs);
    }
}
