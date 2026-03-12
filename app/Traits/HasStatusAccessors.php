<?php

declare(strict_types=1);

namespace App\Traits;

use App\Enums\Status;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * HasStatusAccessors - Status field accessors for models using Status enum
 *
 * This trait provides:
 * - status_label: Human-readable status label (e.g., "Active", "Inactive")
 * - status_badge: Color name for badge (e.g., "success", "warning")
 * - status_class: Full Bootstrap CSS classes (e.g., "bg-success-subtle text-success")
 *
 * ⚠️ REQUIREMENTS:
 * - Model must have a 'status' field cast to App\Enums\Status::class
 * - Add to $appends: ['status_label', 'status_badge', 'status_class']
 *
 * @example
 * class EmailProvider extends Model
 * {
 *     use HasFactory, SoftDeletes, AuditableTrait, HasStatusAccessors;
 *
 *     protected function casts(): array
 *     {
 *         return [
 *             'status' => Status::class,
 *             // ...
 *         ];
 *     }
 *
 *     protected $appends = ['status_label', 'status_badge', 'status_class'];
 * }
 *
 * @requires status field cast to Status::class
 */
trait HasStatusAccessors
{
    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Check if the model is active.
     */
    public function isActive(): bool
    {
        return $this->resolveStatusValue() === Status::ACTIVE->value;
    }

    /**
     * Check if the model is inactive.
     */
    public function isInactive(): bool
    {
        return $this->resolveStatusValue() === Status::INACTIVE->value;
    }

    /**
     * Activate the model.
     */
    public function activate(): bool
    {
        return $this->update(['status' => Status::ACTIVE]);
    }

    /**
     * Deactivate the model.
     */
    public function deactivate(): bool
    {
        return $this->update(['status' => Status::INACTIVE]);
    }

    /**
     * Get the human-readable status label.
     */
    protected function statusLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => Status::labels()[$this->resolveStatusValue() ?? ''] ?? 'Unknown'
        );
    }

    /**
     * Get the status badge color name (for legacy/simple badges).
     * Returns just the color: 'success', 'warning', 'danger', etc.
     */
    protected function statusBadge(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getStatusColorName()
        );
    }

    /**
     * Get full CSS class for status badge (Bootstrap 5 subtle variant).
     *
     * ⚠️ CRITICAL: DataGrid templates expect full Bootstrap classes!
     * Returns: 'bg-success-subtle text-success', etc.
     */
    protected function statusClass(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                $color = $this->getStatusColorName();

                return sprintf('bg-%s-subtle text-%s', $color, $color);
            }
        );
    }

    /**
     * Get the color name for the current status.
     * Override this method in your model to customize colors.
     */
    protected function getStatusColorName(): string
    {
        return match ($this->resolveStatusValue() ?? 'inactive') {
            'active' => 'success',
            'inactive' => 'secondary',
            'pending' => 'warning',
            'deploying' => 'info',
            'success' => 'success',
            'failed' => 'danger',
            'rolled_back' => 'secondary',
            'suspended' => 'danger',
            'draft' => 'info',
            'banned' => 'danger',
            default => 'secondary',
        };
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    /**
     * Scope to get only active records.
     */
    protected function scopeActive($query)
    {
        return $query->where('status', Status::ACTIVE);
    }

    /**
     * Scope to get only inactive records.
     */
    protected function scopeInactive($query)
    {
        return $query->where('status', Status::INACTIVE);
    }

    /**
     * Scope to filter by specific status.
     */
    protected function scopeByStatus($query, string|Status $status)
    {
        $statusValue = $status instanceof Status ? $status->value : $status;

        return $query->where('status', $statusValue);
    }

    private function resolveStatusValue(): ?string
    {
        $status = $this->getAttribute('status');

        if ($status instanceof Status) {
            return $status->value;
        }

        if (is_string($status) && $status !== '') {
            return $status;
        }

        return null;
    }
}
