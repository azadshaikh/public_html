<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AuditableTrait - Audit fields and relationships for models
 *
 * This trait provides:
 * - Auto-set created_by, updated_by, deleted_by on model events
 * - Standard relationships: createdBy(), updatedBy(), deletedBy()
 *
 * ⚠️ IMPORTANT: Models using this trait should NOT define their own boot()
 * method for audit fields - this trait handles that automatically.
 *
 * Required database columns:
 * - created_by (nullable, FK to users)
 * - updated_by (nullable, FK to users)
 * - deleted_by (nullable, FK to users) - only if using SoftDeletes
 *
 * @example
 * class EmailProvider extends Model
 * {
 *     use HasFactory, SoftDeletes, AuditableTrait;
 *
 *     // No need to define createdBy, updatedBy, deletedBy relationships
 *     // No need to define boot() for audit fields
 * }
 */
trait AuditableTrait
{
    // =========================================================================
    // AUDIT RELATIONSHIPS
    // =========================================================================

    /**
     * Get the user who created this record.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this record.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get the user who deleted this record.
     */
    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    // =========================================================================
    // BOOT - Auto-set audit fields
    // =========================================================================

    /**
     * Boot the auditable trait for a model.
     */
    protected static function bootAuditableTrait(): void
    {
        static::creating(function (Model $model): void {
            if (auth()->check()) {
                if ($model->getAttribute('created_by') === null) {
                    $model->setAttribute('created_by', auth()->id());
                }

                if ($model->getAttribute('updated_by') === null) {
                    $model->setAttribute('updated_by', auth()->id());
                }
            }
        });

        static::updating(function (Model $model): void {
            if (auth()->check() && ! $model->isDirty('updated_by')) {
                $model->setAttribute('updated_by', auth()->id());
            }
        });

        static::deleting(function (Model $model): void {
            if (auth()->check() && method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                $model->setAttribute('deleted_by', auth()->id());
                $model->saveQuietly(); // Use saveQuietly to avoid triggering updating event
            }
        });
    }
}
