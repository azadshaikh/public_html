<?php

namespace App\Models;

use App\Enums\Status;
use App\Traits\AuditableTrait;
use App\Traits\HasNotes;
use App\Traits\HasStatusAccessors;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * Role Model
 *
 * Extends Spatie Permission Role with custom fields and soft deletes.
 * Handles role management with status tracking and audit fields.
 */
class Role extends \Spatie\Permission\Models\Role
{
    use AuditableTrait;
    use HasFactory;
    use HasNotes;
    use HasStatusAccessors;
    use SoftDeletes;

    // ==================== CONFIGURATION ====================

    /**
     * The table associated with the model
     */
    protected $table = 'roles';

    /**
     * The attributes that are mass assignable
     */
    protected $fillable = [
        'name',
        'guard_name',
        'display_name',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    /**
     * The accessors to append to the model's array form
     */
    protected $appends = [
        'status_label',
        'status_badge',
        'status_class',
    ];

    /**
     * The attributes that should be cast
     */
    protected function casts(): array
    {
        return [
            'status' => Status::class,
        ];
    }

    // ==================== BOOT & EVENTS ====================

    /**
     * Boot method to handle model events.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $role): void {
            // Auto-generate name from display_name if not provided
            if (empty($role->name) && ! empty($role->display_name)) {
                $role->name = self::generateRoleName($role->display_name);
            }
        });

        static::updating(function (self $role): void {
            // Protect super user role (ID 1) from being deactivated
            throw_if($role->id === 1 && $role->isDirty('status') && (string) $role->getAttribute('status') !== Status::ACTIVE->value, RuntimeException::class, 'Cannot deactivate the super user role.');
        });

        static::deleting(function (self $role): bool {
            // Protect super user role (ID 1) from being deleted
            throw_if($role->id === 1, RuntimeException::class, 'Cannot delete the super user role.');

            return true;
        });
    }

    // ==================== ACCESSORS & MUTATORS ====================

    /**
     * Set the name attribute (always lowercase)
     */
    protected function setNameAttribute(string $value): void
    {
        $this->attributes['name'] = strtolower($value);
    }

    // ==================== SCOPES ====================

    /**
     * Scope to filter out super_user role from non-super user views.
     * Super users can see all roles, but normal users cannot see the super_user role.
     */
    #[Scope]
    protected function visibleToCurrentUser($query)
    {
        $currentUser = Auth::user();

        // If no user is authenticated or current user is a super user, show all
        if (! $currentUser || $currentUser->isSuperUser()) {
            return $query;
        }

        // For non-super users, exclude the super_user role
        return $query->where('id', '!=', User::superUserRoleId());
    }

    // ==================== HELPERS ====================

    /**
     * Generate role name from display name
     */
    private static function generateRoleName(string $displayName): string
    {
        return strtolower((string) preg_replace('/[^a-zA-Z0-9]/', '_', $displayName));
    }
}
