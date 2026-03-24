<?php

declare(strict_types=1);

namespace Modules\ChatBot\Models;

use App\Traits\AuditableTrait;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $conversation_id
 * @property int|null $user_id
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property string|null $agent
 * @property string $role
 * @property string $status
 * @property string|null $finish_reason
 * @property string|null $content
 * @property string|null $attachments
 * @property string|null $tool_calls
 * @property string|null $tool_results
 * @property string|null $usage
 * @property string|null $meta
 * @property string|null $error_code
 * @property string|null $error_message
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $interrupted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class ConversationMessage extends Model
{
    use AuditableTrait;
    use SoftDeletes;

    protected $table = 'agent_conversation_messages';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'conversation_id',
        'user_id',
        'agent',
        'role',
        'status',
        'finish_reason',
        'content',
        'attachments',
        'tool_calls',
        'tool_results',
        'usage',
        'meta',
        'started_at',
        'completed_at',
        'interrupted_at',
        'error_code',
        'error_message',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'interrupted_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }
}
