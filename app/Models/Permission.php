<?php

namespace App\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * @property int $id
 * @property string $name
 * @property string $guard_name
 * @property string|null $display_name
 * @property string|null $group
 * @property string|null $module_slug
 * @property string|null $description
 */
class Permission extends SpatiePermission
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'guard_name',
        'display_name',
        'group',
        'module_slug',
        'description',
    ];
}
