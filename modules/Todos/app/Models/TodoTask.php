<?php

namespace Modules\Todos\Models;

use Illuminate\Database\Eloquent\Model;

class TodoTask extends Model
{
    public const STATUSES = [
        'backlog' => 'Backlog',
        'in_progress' => 'In progress',
        'done' => 'Done',
    ];

    public const PRIORITIES = [
        'low' => 'Low',
        'medium' => 'Medium',
        'high' => 'High',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'slug',
        'details',
        'status',
        'priority',
        'owner',
        'due_date',
        'is_blocked',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'is_blocked' => 'boolean',
        ];
    }

    /**
     * @return array<string, bool|string>
     */
    public static function defaultFormData(): array
    {
        return [
            'title' => '',
            'slug' => '',
            'details' => '',
            'status' => 'backlog',
            'priority' => 'medium',
            'owner' => '',
            'due_date' => '',
            'is_blocked' => false,
        ];
    }
}
