<?php

declare(strict_types=1);

namespace Modules\ChatBot\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $conversation_id
 * @property int|null $user_id
 * @property string $tool_name
 * @property string|null $tool_invocation_id
 * @property string $request_fingerprint
 * @property string $status
 * @property array<string, mixed>|null $arguments
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $approved_at
 * @property Carbon|null $denied_at
 * @property Carbon|null $consumed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ToolPermissionRequest extends Model
{
    protected $table = 'chatbot_tool_permission_requests';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'conversation_id',
        'user_id',
        'tool_name',
        'tool_invocation_id',
        'request_fingerprint',
        'status',
        'arguments',
        'metadata',
        'approved_at',
        'denied_at',
        'consumed_at',
    ];

    protected function casts(): array
    {
        return [
            'arguments' => 'array',
            'metadata' => 'array',
            'approved_at' => 'datetime',
            'denied_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }
}
