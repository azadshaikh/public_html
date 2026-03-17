<?php

namespace Modules\Platform\Models;

use App\Models\User;
use App\Traits\AuditableTrait;
use App\Traits\HasMetadata;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Platform\Models\Presenters\DomainDnsPresenters;
use Modules\Platform\Models\QueryBuilders\DomainDnsRecordQueryBuilder;

/**
 * @property int $id
 * @property int $domain_id
 * @property string|null $type
 * @property string|null $name
 * @property string|null $value
 * @property int|null $ttl
 * @property int|null $priority
 * @property int|null $weight
 * @property int|null $port
 * @property bool $disabled
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Domain|null $domain
 */
class DomainDnsRecord extends Model
{
    use AuditableTrait;
    use DomainDnsPresenters;
    use HasFactory;
    use HasMetadata;
    use SoftDeletes;

    protected $table = 'platform_dns_records';

    protected $guarded = ['id'];

    // ==========================================
    // Relationships
    // ==========================================

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class, 'domain_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    // ==========================================
    // Query Builder
    // ==========================================

    public function newEloquentBuilder($query): DomainDnsRecordQueryBuilder
    {
        return new DomainDnsRecordQueryBuilder($query);
    }

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'disabled' => 'boolean',
            'ttl' => 'integer',
            'priority' => 'integer',
            'weight' => 'integer',
            'port' => 'integer',
        ];
    }
}
