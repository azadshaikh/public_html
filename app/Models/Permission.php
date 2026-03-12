<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Permission Model
 *
 * Extends Spatie Permission model with custom fields and audit tracking.
 * Handles permission management with group categorization and audit fields.
 */
class Permission extends \Spatie\Permission\Models\Permission
{
    use HasFactory;

    // ==================== CONFIGURATION ====================

    /**
     * The table associated with the model.
     */
    protected $table = 'permissions';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'display_name',
        'guard_name',
        'group',
        'module_slug',
        'created_by',
        'updated_by',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ==================== RELATIONSHIPS ====================

    /**
     * Get the user who created this permission.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this permission.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // ==================== HELPER METHODS ====================

    /**
     * Get permissions by group.
     */
    public static function getByGroup(string $group): Collection
    {
        return static::query()->where('group', $group)->get();
    }

    /**
     * Get permissions by module.
     */
    public static function getByModule(string $moduleSlug): Collection
    {
        return static::query()->where('module_slug', $moduleSlug)->get();
    }

    /**
     * Get all unique groups.
     */
    public static function getGroups(): \Illuminate\Support\Collection
    {
        return static::query()->distinct('group')->pluck('group')->filter()->sort();
    }

    /**
     * Get all unique module slugs.
     */
    public static function getModules(): \Illuminate\Support\Collection
    {
        return static::query()->distinct('module_slug')->pluck('module_slug')->filter()->sort();
    }

    // ==================== ACCESSORS & MUTATORS ====================

    /**
     * Set the name attribute (ensure lowercase).
     */
    protected function setNameAttribute(string $value): void
    {
        $this->attributes['name'] = strtolower($value);
    }

    /**
     * Get the display name attribute with fallback.
     */
    protected function getDisplayNameAttribute(?string $value): string
    {
        return $value ?? ucwords(str_replace(['_', '-'], ' ', $this->name));
    }
}
