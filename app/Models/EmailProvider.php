<?php

namespace App\Models;

use App\Enums\Status;
use App\Traits\AuditableTrait;
use App\Traits\HasMetadata;
use App\Traits\HasStatusAccessors;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\App;
use Throwable;

/**
 * EmailProvider - SMTP email provider configuration
 *
 * Uses standard Scaffold traits:
 * - AuditableTrait: Auto-sets created_by, updated_by, deleted_by + relationships
 * - HasMetadata: Provides getMetadata(), setMetadata(), hasMetadata() helpers
 * - HasStatusAccessors: Provides status_label, status_badge, status_class accessors + scopes
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string|null $sender_name
 * @property string|null $sender_email
 * @property string|null $smtp_host
 * @property string|null $smtp_user
 * @property string|null $smtp_password
 * @property string|null $smtp_port
 * @property string|null $smtp_encryption
 * @property string|null $reply_to
 * @property string|null $bcc
 * @property string|null $signature
 * @property Status $status
 * @property int|null $order
 * @property array|null $metadata
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read string $status_label
 * @property-read string $status_badge
 * @property-read string $status_class
 * @property-read User|null $createdBy
 * @property-read User|null $updatedBy
 * @property-read User|null $deletedBy
 */
class EmailProvider extends Model
{
    use AuditableTrait;
    use HasFactory;
    use HasMetadata;
    use HasStatusAccessors;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'sender_name',
        'sender_email',
        'smtp_host',
        'smtp_user',
        'smtp_password',
        'smtp_port',
        'smtp_encryption',
        'reply_to',
        'bcc',
        'signature',
        'status',
        'order',
        'metadata',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    /**
     * Appended attributes for JSON serialization.
     */
    protected $appends = [
        'status_label',
        'status_badge',
        'status_class',
    ];

    protected $hidden = [
        'smtp_password',
    ];

    // =========================================================================
    // STATIC HELPERS
    // =========================================================================

    /**
     * Get active providers for dropdowns and selections.
     */
    public static function getActiveProviders()
    {
        return self::query()
            ->select('id', 'name', 'sender_name', 'sender_email')
            ->active()
            ->get()
            ->keyBy('id');
    }

    /**
     * Get active providers formatted for select dropdowns.
     */
    public static function getActiveProvidersForSelect(): array
    {
        try {
            return self::query()
                ->select('id', 'name')
                ->active()
                ->orderBy('name')
                ->get()
                ->map(fn ($provider): array => [
                    'value' => (string) $provider->id,
                    'label' => $provider->name,
                ])
                ->toArray();
        } catch (Throwable $throwable) {
            if (App::runningInConsole()) {
                return [];
            }

            throw $throwable;
        }
    }

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'status' => Status::class,
            'order' => 'integer',
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }
}
