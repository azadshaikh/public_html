<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\AuditableTrait;
use App\Traits\HasMetadata;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * EmailLog Model
 *
 * Uses standard Scaffold traits:
 * - AuditableTrait: Auto-sets created_by, updated_by, deleted_by + relationships
 * - HasMetadata: Provides getMetadata(), setMetadata(), hasMetadata() helpers
 * - SoftDeletes: Provides soft delete functionality
 *
 * Note: Status labels and badge classes are handled by EmailLogResource
 */
class EmailLog extends Model
{
    use AuditableTrait;
    use HasFactory;
    use HasMetadata;
    use SoftDeletes;

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_QUEUED = 'queued';

    protected $fillable = [
        'email_template_id',
        'template_name',
        'email_provider_id',
        'provider_name',
        'sent_by',
        'status',
        'subject',
        'body',
        'recipients',
        'error_message',
        'context',
        'sent_at',
        'metadata',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    /**
     * Appended attributes for JSON serialization
     */
    protected $appends = [
        'recipient_list',
        'status_label',
        'status_badge',
    ];

    // ================================================================
    // RELATIONSHIPS
    // Note: createdBy, updatedBy, deletedBy are provided by AuditableTrait
    // ================================================================

    public function template(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class, 'email_template_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(EmailProvider::class, 'email_provider_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    /**
     * ⚠️ Use casts() method, NOT $casts property (Laravel 11+)
     */
    protected function casts(): array
    {
        return [
            'recipients' => 'array',
            'context' => 'array',
            'metadata' => 'array',
            'sent_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    // ================================================================
    // ACCESSORS
    // ================================================================

    /**
     * Get recipient list as array
     * Handles both JSON-encoded arrays (via cast) and legacy comma-separated strings
     */
    protected function getRecipientListAttribute(): array
    {
        $recipients = $this->getAttribute('recipients');

        if (is_array($recipients)) {
            return $recipients;
        }

        if (is_string($recipients) && $recipients !== '') {
            return array_values(array_filter(array_map(trim(...), explode(',', $recipients))));
        }

        return [];
    }

    /**
     * Get status label for display
     * Note: EmailLogResource has its own implementation for API responses
     */
    protected function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'sent', 'success' => 'Sent',
            'queued' => 'Queued',
            'failed', 'error' => 'Failed',
            default => ucfirst(str_replace('_', ' ', (string) $this->status)),
        };
    }

    /**
     * Get status badge type (for old badge format compatibility)
     * Note: EmailLogResource uses status_class for DataGrid
     */
    protected function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'sent', 'success' => 'success',
            'queued' => 'warning',
            'failed', 'error' => 'danger',
            default => 'secondary',
        };
    }
}
