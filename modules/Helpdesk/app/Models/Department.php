<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Models;

use App\Enums\Status;
use App\Models\User;
use App\Traits\AuditableTrait;
use App\Traits\HasMetadata;
use App\Traits\HasStatusAccessors;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Helpdesk\Database\Factories\DepartmentFactory;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property int|null $department_head
 * @property string|null $visibility
 * @property Status|string|null $status
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User|null $departmentHead
 */
class Department extends Model
{
    use AuditableTrait;
    use HasFactory;
    use HasMetadata;
    use HasStatusAccessors;
    use SoftDeletes;

    protected static function newFactory(): Factory
    {
        return DepartmentFactory::new();
    }

    protected $table = 'helpdesk_departments';

    protected $fillable = [
        'name',
        'description',
        'department_head',
        'visibility',
        'status',
        'metadata',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $appends = [
        'status_label',
        'status_badge',
        'status_class',
    ];

    public function departmentHead(): BelongsTo
    {
        return $this->belongsTo(User::class, 'department_head');
    }

    protected function casts(): array
    {
        return [
            'status' => Status::class,
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }
}
