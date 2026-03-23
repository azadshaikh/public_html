<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Models;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Helpdesk\Database\Factories\TicketRepliesFactory;

/**
 * @property int $id
 * @property int $ticket_id
 * @property string $content
 * @property array<int, array<string, mixed>>|string|null $attachments
 * @property bool $is_internal
 * @property int|null $reply_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Ticket|null $ticket
 * @property-read User|null $replyBy
 */
class TicketReplies extends Model
{
    use HasFactory;

    protected static function newFactory(): Factory
    {
        return TicketRepliesFactory::new();
    }

    protected $table = 'helpdesk_tickets_replies';

    protected $fillable = [
        'ticket_id',
        'content',
        'attachments',
        'is_internal',
        'reply_by',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function replyBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reply_by');
    }

    protected function casts(): array
    {
        return [
            'attachments' => 'array',
            'is_internal' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
