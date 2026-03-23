<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Models;

use App\Models\User;
use App\Traits\AuditableTrait;
use App\Traits\HasMetadata;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Helpdesk\Database\Factories\TicketFactory;

/**
 * @property int $id
 * @property string $ticket_number
 * @property int|null $department_id
 * @property int|null $user_id
 * @property string|null $subject
 * @property string|null $description
 * @property string|null $priority
 * @property int|null $assigned_to
 * @property string|null $status
 * @property Carbon|null $opened_at
 * @property Carbon|null $closed_at
 * @property int|null $closed_by
 * @property array<int, array<string, mixed>>|null $attachments
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Department|null $department
 * @property-read User|null $user
 * @property-read User|null $assignedTo
 * @property-read Collection<int, TicketReplies> $replies
 */
class Ticket extends Model
{
    use AuditableTrait;
    use HasFactory;
    use HasMetadata;
    use SoftDeletes;

    protected static function newFactory(): Factory
    {
        return TicketFactory::new();
    }

    protected $table = 'helpdesk_tickets';

    protected $fillable = [
        'ticket_number',
        'department_id',
        'user_id',
        'subject',
        'description',
        'priority',
        'assigned_to',
        'status',
        'opened_at',
        'closed_at',
        'closed_by',
        'attachments',
        'metadata',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(TicketReplies::class, 'ticket_id');
    }

    protected function casts(): array
    {
        return [
            'attachments' => 'array',
            'metadata' => 'array',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }
}
