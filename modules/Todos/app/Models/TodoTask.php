<?php

namespace Modules\Todos\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string|null $details
 * @property string $status
 * @property string $priority
 * @property string|null $owner
 * @property Carbon|null $due_date
 * @property bool $is_blocked
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class TodoTask extends Model
{
    use HasFactory;

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
