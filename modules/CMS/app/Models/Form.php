<?php

namespace Modules\CMS\Models;

use App\Models\User;
use App\Traits\HasMetadata;
use App\Traits\HasNotes;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $title
 * @property string|null $slug
 * @property string|null $shortcode
 * @property string|null $html
 * @property string|null $template
 * @property string|null $form_type
 * @property string|null $css
 * @property string|null $js
 * @property array<string, mixed>|null $fields
 * @property array<string, mixed>|null $settings
 * @property bool $store_in_database
 * @property array<string, mixed>|null $email_template
 * @property array<int, mixed>|null $notification_emails
 * @property bool $send_autoresponder
 * @property array<string, mixed>|null $autoresponder_config
 * @property array<string, mixed>|null $confirmations
 * @property array<string, mixed>|null $conditional_logic
 * @property array<string, mixed>|null $metadata
 * @property string|null $status
 * @property string|null $feature_image_url
 * @property bool $is_active
 * @property bool $has_spam_protection
 * @property bool $requires_login
 * @property bool $limit_one_submission_per_user
 * @property int $submissions_count
 * @property int $unread_count
 * @property int $views_count
 * @property float|string|null $conversion_rate
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property Carbon|null $last_submission_at
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read string $status_badge
 * @property-read string $published_at_formatted
 * @property-read string $creator_name
 * @property-read User|null $createdBy
 * @property-read User|null $updatedBy
 * @property-read User|null $deletedBy
 * @property-read Collection<int, FormSubmission> $submissions
 * @property-read string|null $status_label
 * @property-read string|null $status_class
 */
class Form extends Model
{
    use HasFactory;
    use HasMetadata;
    use HasNotes;
    use SoftDeletes;

    protected $table = 'cms_forms';

    protected $fillable = [
        'title',
        'slug',
        'shortcode',
        'template',
        'form_type',
        'html',
        'css',
        'js',
        'fields',
        'settings',
        'feature_image_url',
        'store_in_database',
        'email_template',
        'notification_emails',
        'send_autoresponder',
        'autoresponder_config',
        'confirmations',
        'conditional_logic',
        'metadata',
        'status',
        'is_active',
        'has_spam_protection',
        'requires_login',
        'limit_one_submission_per_user',
        'submissions_count',
        'unread_count',
        'views_count',
        'conversion_rate',
        'last_submission_at',
        'published_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $appends = [
        'status_label',
        'status_badge',
        'published_at_formatted',
        'creator_name',
    ];

    // ==================== RELATIONSHIPS ====================

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

    public function submissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class, 'form_id');
    }

    // ==================== HELPER METHODS ====================

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function getSubmissionsCount(): int
    {
        return $this->submissions()->count();
    }

    public function getLastSubmissionDate(): ?string
    {
        /** @var FormSubmission|null $lastSubmission */
        $lastSubmission = $this->submissions()->latest()->first();

        return $lastSubmission ? $lastSubmission->created_at->format('M d, Y g:i A') : null;
    }

    // ==================== WPFORMS-LIKE METHODS ====================

    /**
     * Check if form is accepting submissions
     */
    public function isAcceptingSubmissions(): bool
    {
        return $this->is_active && $this->isPublished();
    }

    /**
     * Check if form has conditional logic
     */
    public function hasConditionalLogic(): bool
    {
        return ! empty($this->conditional_logic);
    }

    /**
     * Get form fields as array
     */
    public function getFields(): array
    {
        return $this->fields ?? [];
    }

    /**
     * Get specific field by ID
     */
    public function getField(string $fieldId): ?array
    {
        $fields = $this->getFields();

        foreach ($fields as $field) {
            if (($field['id'] ?? null) === $fieldId) {
                return $field;
            }
        }

        return null;
    }

    /**
     * Get unread submissions count
     */
    public function getUnreadCount(): int
    {
        return $this->unread_count ?? 0;
    }

    /**
     * Update conversion rate
     */
    public function updateConversionRate(): void
    {
        if ($this->views_count > 0) {
            $this->conversion_rate = round($this->submissions_count / $this->views_count * 100, 2);
            $this->saveQuietly();
        }
    }

    /**
     * Increment views count
     */
    public function incrementViews(): void
    {
        $this->increment('views_count');
        $this->updateConversionRate();
    }

    /**
     * Increment submissions count
     */
    public function incrementSubmissions(): void
    {
        $this->increment('submissions_count');
        $this->increment('unread_count');
        $this->last_submission_at = now();
        $this->save();
        $this->updateConversionRate();
    }

    /**
     * Decrement unread count
     */
    public function decrementUnread(): void
    {
        if ($this->unread_count > 0) {
            $this->decrement('unread_count');
        }
    }

    /**
     * Get form template name
     */
    public function getTemplateName(): string
    {
        $templates = config('cms.forms.templates', []);

        return $templates[$this->template]['label'] ?? ucfirst((string) $this->template);
    }

    /**
     * Get form type name
     */
    public function getFormTypeName(): string
    {
        $types = config('cms.forms.form_types', []);

        return $types[$this->form_type]['label'] ?? ucfirst((string) $this->form_type);
    }

    /**
     * Check if user can submit (for logged-in user restriction)
     */
    public function canUserSubmit(?int $userId = null): bool
    {
        if (! $this->isAcceptingSubmissions()) {
            return false;
        }

        if ($this->requires_login && ! $userId) {
            return false;
        }

        if ($this->limit_one_submission_per_user && $userId) {
            return ! $this->submissions()->where('user_id', $userId)->exists();
        }

        return true;
    }

    protected function casts(): array
    {
        return [
            'fields' => 'array',
            'settings' => 'array',
            'email_template' => 'array',
            'notification_emails' => 'array',
            'autoresponder_config' => 'array',
            'confirmations' => 'array',
            'conditional_logic' => 'array',
            'metadata' => 'array',
            'published_at' => 'datetime',
            'last_submission_at' => 'datetime',
            'store_in_database' => 'boolean',
            'is_active' => 'boolean',
            'has_spam_protection' => 'boolean',
            'requires_login' => 'boolean',
            'limit_one_submission_per_user' => 'boolean',
            'send_autoresponder' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model): void {
            $model->created_by = auth()->id();
        });

        static::updating(function (self $model): void {
            $model->updated_by = auth()->id();
        });

        static::deleting(function (self $model): void {
            if (! $model->isForceDeleting()) {
                $model->deleted_by = auth()->id();
                $model->save();
            }
        });
    }

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

    protected function publishedAtFormatted(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->published_at ? $this->published_at->format('M d, Y g:i A') : 'Not published'
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

    // ==================== SCOPES ====================

    #[Scope]
    protected function published(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    #[Scope]
    protected function draft(Builder $query): Builder
    {
        return $query->where('status', 'draft');
    }

    protected function getShortcodeAttribute(): string
    {
        $shortcode = $this->attributes['shortcode'] ?? null;

        return $shortcode ? sprintf('[form id="%s"]', $shortcode) : '';
    }
}
