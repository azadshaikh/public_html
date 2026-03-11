<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;

/**
 * @property int $id
 * @property string $name
 * @property string $guard_name
 * @property string|null $display_name
 * @property string|null $description
 * @property bool $is_system
 */
class Role extends SpatieRole
{
    public const SUPER_USER = 'super_user';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'guard_name',
        'display_name',
        'description',
        'is_system',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }
}
