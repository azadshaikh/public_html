<?php

namespace Modules\CMS\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Modules\CMS\Models\QueryBuilders\FormSubmissionQueryBuilder;

/**
 * @property int $id
 * @property int|null $form_id
 * @property int|null $user_id
 * @property int|null $entry_number
 * @property array<string, mixed>|null $data
 * @property array<string, mixed>|null $metadata
 * @property string|null $status
 * @property bool $is_starred
 * @property bool $is_spam
 * @property string|null $user_agent
 * @property string|null $ip_address
 * @property string|null $referer_url
 * @property string|null $notes
 * @property Carbon|null $read_at
 * @property int|null $read_by
 * @property int|null $deleted_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Form|null $form
 * @property-read User|null $user
 * @property-read User|null $readBy
 * @property-read User|null $deletedBy
 * @property-read string $status_label
 * @property-read string $status_badge
 *
 * @method static FormSubmissionQueryBuilder query()
 */
class FormSubmission extends Model
{
    use HasFactory;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'cms_form_submissions';

    protected $fillable = [
        'form_id',
        'user_id',
        'entry_number',
        'data',
        'metadata',
        'status',
        'is_starred',
        'is_spam',
        'user_agent',
        'ip_address',
        'referer_url',
        'notes',
        'read_at',
        'read_by',
        'deleted_by',
    ];

    protected $appends = [
        'status_label',
        'status_badge',
    ];

    // ==================== RELATIONSHIPS ====================

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class, 'form_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function readBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'read_by');
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    // ==================== HELPER METHODS ====================

    public function markAsRead(?int $userId = null): void
    {
        if ($this->status === 'unread') {
            $this->status = 'read';
            $this->read_at = now();
            $this->read_by = $userId ?? auth()->id();
            $this->save();

            // Decrement unread count on form
            if ($this->form) {
                $this->form->decrementUnread();
            }
        }
    }

    public function markAsUnread(): void
    {
        if ($this->status === 'read') {
            $this->status = 'unread';
            $this->read_at = null;
            $this->read_by = null;
            $this->save();

            // Increment unread count on form
            if ($this->form) {
                $this->form->increment('unread_count');
            }
        }
    }

    public function toggleStar(): void
    {
        $this->is_starred = ! $this->is_starred;
        $this->save();
    }

    public function markAsSpam(): void
    {
        $this->is_spam = true;
        $this->status = 'spam';
        $this->save();
    }

    public function markAsNotSpam(): void
    {
        $this->is_spam = false;
        if ($this->status === 'spam') {
            $this->status = 'unread';
        }

        $this->save();
    }

    public function getFieldValue(string $fieldName, $default = null)
    {
        return $this->data[$fieldName] ?? $default;
    }

    // ==================== QUERY BUILDER ====================

    public function newEloquentBuilder($query): FormSubmissionQueryBuilder
    {
        return new FormSubmissionQueryBuilder($query);
    }

    public static function getAllData(array $filter_arr = []): LengthAwarePaginator
    {
        return static::query()
            ->search($filter_arr['search_text'] ?? null)
            ->filterByForm($filter_arr['form_id'] ?? null)
            ->filterByDate($filter_arr['created_at'] ?? null)
            ->filterBySortable($filter_arr['sortable'] ?? null)
            ->sortBy($filter_arr['sort_by'] ?? null)
            ->orderResults($filter_arr['order'] ?? null)
            ->paginateResults($filter_arr['pagelimit'] ?? null);
    }

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'metadata' => 'array',
            'is_starred' => 'boolean',
            'is_spam' => 'boolean',
            'read_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model): void {
            // Auto-generate entry number for the form
            if (! $model->entry_number && $model->form_id) {
                $model->entry_number = static::query()->where('form_id', $model->form_id)->max('entry_number') + 1;
            }

            // Capture request data
            $model->user_agent ??= request()->userAgent();
            $model->ip_address ??= request()->ip();
            $model->referer_url ??= request()->header('referer');

            // Set user if authenticated
            if (! $model->user_id && auth()->check()) {
                $model->user_id = auth()->id();
            }
        });

        static::created(function (self $model): void {
            // Increment form submission count
            if ($model->form) {
                $model->form->incrementSubmissions();
            }
        });

        static::deleting(function (self $model): void {
            if (! $model->isForceDeleting()) {
                $model->deleted_by = auth()->id();
                $model->save();
            }
        });
    }

    // ==================== ACCESSORS ====================

    protected function statusLabel(): Attribute
    {
        return Attribute::make(
            get: fn (): string => match ($this->status) {
                'unread' => 'Unread',
                'read' => 'Read',
                'starred' => 'Starred',
                'spam' => 'Spam',
                'trash' => 'Trash',
                default => ucfirst($this->status ?? 'Unknown'),
            }
        );
    }

    protected function statusBadge(): Attribute
    {
        return Attribute::make(
            get: fn (): string => match ($this->status) {
                'unread' => 'primary',
                'read' => 'success',
                'starred' => 'warning',
                'spam' => 'danger',
                'trash' => 'secondary',
                default => 'secondary',
            }
        );
    }

    // ==================== SCOPES ====================

    #[Scope]
    protected function unread(FormSubmissionQueryBuilder $query): FormSubmissionQueryBuilder
    {
        return $query->where('status', 'unread');
    }

    #[Scope]
    protected function read(FormSubmissionQueryBuilder $query): FormSubmissionQueryBuilder
    {
        return $query->where('status', 'read');
    }

    #[Scope]
    protected function starred(FormSubmissionQueryBuilder $query): FormSubmissionQueryBuilder
    {
        return $query->where('is_starred', true);
    }

    #[Scope]
    protected function notSpam(FormSubmissionQueryBuilder $query): FormSubmissionQueryBuilder
    {
        return $query->where('is_spam', false);
    }

    #[Scope]
    protected function spam(FormSubmissionQueryBuilder $query): FormSubmissionQueryBuilder
    {
        return $query->where('is_spam', true);
    }
}
