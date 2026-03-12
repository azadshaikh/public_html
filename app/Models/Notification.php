<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NotificationCategory;
use App\Enums\NotificationPriority;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Modules\CMS\Services\ContentSanitizer;

/**
 * Notification Model
 *
 * Enhanced notification model with categories, priorities, and filtering capabilities.
 *
 * @property string $id
 * @property string $type
 * @property string $notifiable_type
 * @property int $notifiable_id
 * @property array $data
 * @property NotificationCategory $category
 * @property NotificationPriority $priority
 * @property string|null $title
 * @property Carbon|null $read_at
 * @property Carbon|null $deleted_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read bool $is_read
 * @property-read string $title_text
 * @property-read string $message
 * @property-read string $icon
 * @property-read string|null $url
 * @property-read string $category_label
 * @property-read string $category_color
 * @property-read string $category_badge
 * @property-read string $priority_label
 * @property-read string $priority_badge
 */
class Notification extends Model
{
    use HasFactory;
    use HasFactory;
    use SoftDeletes;

    /**
     * Indicates if the IDs are auto-incrementing.
     */
    public $incrementing = false;

    /**
     * The table associated with the model.
     */
    protected $table = 'notifications';

    /**
     * The primary key type.
     */
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'id',
        'type',
        'notifiable_type',
        'notifiable_id',
        'data',
        'category',
        'priority',
        'title',
        'read_at',
    ];

    /**
     * The attributes that should be appended to the model.
     */
    protected $appends = [
        'is_read',
        'title_text',
        'message',
        'sanitized_message',
        'icon',
        'url',
        'category_label',
        'category_color',
        'category_badge',
        'priority_label',
        'priority_badge',
    ];

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * Get the notifiable entity (user, etc.).
     */
    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    // =========================================================================
    // METHODS
    // =========================================================================

    /**
     * Mark notification as read.
     */
    public function markAsRead(): void
    {
        if ($this->read_at === null) {
            $this->update(['read_at' => now()]);
        }
    }

    /**
     * Mark notification as unread.
     */
    public function markAsUnread(): void
    {
        if ($this->read_at !== null) {
            $this->update(['read_at' => null]);
        }
    }

    /**
     * Check if notification belongs to a user.
     */
    public function belongsToUser(int $userId): bool
    {
        return $this->notifiable_type === User::class
            && $this->notifiable_id === $userId;
    }

    /**
     * Get time ago string.
     */
    public function getTimeAgo(): string
    {
        return $this->created_at->diffForHumans();
    }

    /**
     * Convert to array for JSON response (dropdown).
     */
    public function toDropdownArray(): array
    {
        $priorityValue = $this->data['priority'] ?? $this->priority->value;
        $priorityEnum = NotificationPriority::tryFrom((string) $priorityValue) ?? NotificationPriority::Medium;

        return [
            'id' => $this->id,
            'title' => $this->title_text,
            'message' => $this->sanitized_message,
            'icon' => $this->icon,
            'url' => $this->url,
            'category' => $this->category_label,
            'category_color' => $this->category_color,
            'priority' => $priorityEnum->label(),
            'priority_value' => $priorityEnum->value,
            'time_ago' => $this->getTimeAgo(),
            'is_read' => $this->is_read,
            'created_at' => $this->created_at->toISOString(),
        ];
    }

    /**
     * Get the list of all the Columns of the table.
     *
     * @return array Column names array
     */
    public function getTableColumns(): array
    {
        return DB::select('SHOW COLUMNS FROM '.$this->getTable());
    }

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'data' => 'array',
            'category' => NotificationCategory::class,
            'priority' => NotificationPriority::class,
            'read_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    /**
     * Scope to get only unread notifications.
     */
    #[Scope]
    protected function unread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    /**
     * Scope to get only read notifications.
     */
    #[Scope]
    protected function read(Builder $query): Builder
    {
        return $query->whereNotNull('read_at');
    }

    /**
     * Scope to get notifications for a specific user.
     */
    #[Scope]
    protected function forUser(Builder $query, int $userId): Builder
    {
        return $query->where('notifiable_type', User::class)
            ->where('notifiable_id', $userId);
    }

    /**
     * Scope to filter by category.
     */
    #[Scope]
    protected function ofCategory(Builder $query, NotificationCategory|string $category): Builder
    {
        $value = $category instanceof NotificationCategory ? $category->value : $category;

        return $query->where('category', $value);
    }

    /**
     * Scope to filter by priority.
     */
    #[Scope]
    protected function ofPriority(Builder $query, NotificationPriority|string $priority): Builder
    {
        $value = $priority instanceof NotificationPriority ? $priority->value : $priority;

        return $query->where('priority', $value);
    }

    /**
     * Scope to search notifications by title or content.
     */
    #[Scope]
    protected function search(Builder $query, string $search): Builder
    {
        $search = '%'.trim($search).'%';
        $driver = DB::getDriverName();

        return $query->where(function (Builder $q) use ($driver, $search): void {
            $q->where('title', 'ilike', $search);

            if ($driver === 'pgsql') {
                $q
                    ->orWhereRaw("(data::jsonb->>'title') ILIKE ?", [$search])
                    ->orWhereRaw("(data::jsonb->>'text') ILIKE ?", [$search])
                    ->orWhereRaw("(data::jsonb->>'message') ILIKE ?", [$search]);

                return;
            }

            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                $q
                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(data, '$.title')) LIKE ?", [$search])
                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(data, '$.text')) LIKE ?", [$search])
                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(data, '$.message')) LIKE ?", [$search]);

                return;
            }

            $q->orWhere('data', 'ilike', $search);
        });
    }

    /**
     * Scope to filter by date range.
     */
    #[Scope]
    protected function inDateRange(Builder $query, ?string $from, ?string $to): Builder
    {
        if ($from) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to) {
            $query->whereDate('created_at', '<=', $to);
        }

        return $query;
    }

    /**
     * Scope to get high priority notifications.
     */
    #[Scope]
    protected function highPriority(Builder $query): Builder
    {
        return $query->where('priority', NotificationPriority::High->value);
    }

    /**
     * Scope to order by latest first.
     */
    #[Scope]
    protected function latestFirst(Builder $query): Builder
    {
        return $query->latest();
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    /**
     * Check if notification is read.
     */
    protected function getIsReadAttribute(): bool
    {
        return $this->read_at !== null;
    }

    /**
     * Get the notification title (from title column or data).
     */
    protected function getTitleTextAttribute(): string
    {
        return $this->title ?? $this->data['title'] ?? 'Notification';
    }

    /**
     * Get the notification message/body.
     */
    protected function getMessageAttribute(): string
    {
        return $this->data['text'] ?? $this->data['message'] ?? '';
    }

    /**
     * Get sanitized notification message/body.
     */
    protected function getSanitizedMessageAttribute(): string
    {
        // Use ContentSanitizer if CMS module is available, otherwise strip tags
        if (active_modules('cms') && class_exists(ContentSanitizer::class)) {
            return ContentSanitizer::sanitizeHTML(
                $this->message,
                ['a', 'b', 'br', 'em', 'i', 'li', 'ol', 'p', 'span', 'strong', 'u', 'ul']
            );
        }

        // Fallback: strip all HTML tags
        return strip_tags($this->message);
    }

    /**
     * Get the notification icon.
     */
    protected function getIconAttribute(): string
    {
        return $this->data['icon'] ?? $this->category->icon();
    }

    /**
     * Get the notification URL (if any).
     */
    protected function getUrlAttribute(): ?string
    {
        return $this->sanitizeUrl($this->data['url_backend'] ?? $this->data['url'] ?? null);
    }

    /**
     * Get the backend URL (if any).
     */
    protected function getUrlBackendAttribute(): ?string
    {
        return $this->sanitizeUrl($this->data['url_backend'] ?? null);
    }

    /**
     * Get the frontend URL (if any).
     */
    protected function getUrlFrontendAttribute(): ?string
    {
        return $this->sanitizeUrl($this->data['url_frontend'] ?? null);
    }

    /**
     * Get the category label.
     */
    protected function getCategoryLabelAttribute(): string
    {
        return $this->category->label();
    }

    /**
     * Get the category color.
     */
    protected function getCategoryColorAttribute(): string
    {
        return $this->category->color();
    }

    /**
     * Get the category badge class.
     */
    protected function getCategoryBadgeAttribute(): string
    {
        return $this->category->badgeClass();
    }

    /**
     * Get the priority label.
     */
    protected function getPriorityLabelAttribute(): string
    {
        return $this->priority->label();
    }

    /**
     * Get the priority badge class.
     */
    protected function getPriorityBadgeAttribute(): string
    {
        return $this->priority->badgeClass();
    }

    /**
     * Basic URL sanitization for notification links.
     */
    private function sanitizeUrl(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        if (preg_match('/^(javascript|data|vbscript):/i', $url)) {
            return null;
        }

        return $url;
    }
}
