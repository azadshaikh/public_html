<?php

namespace Modules\Platform\Models;

use App\Traits\AuditableTrait;
use App\Traits\HasMetadata;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $tld
 * @property string|null $whois_server
 * @property string|null $pattern
 * @property bool $is_main
 * @property bool $is_suggested
 * @property float|string|null $price
 * @property float|string|null $sale_price
 * @property string|null $affiliate_link
 * @property bool $status
 * @property int|null $tld_order
 * @property array<string, mixed>|null $metadata
 */
class Tld extends Model
{
    use AuditableTrait;
    use HasFactory;
    use HasMetadata;
    use SoftDeletes;

    protected $table = 'platform_tlds';

    protected $fillable = [
        'tld',
        'whois_server',
        'pattern',
        'is_main',
        'is_suggested',
        'price',
        'sale_price',
        'affiliate_link',
        'status',
        'tld_order',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'is_main' => 'boolean',
            'is_suggested' => 'boolean',
            'status' => 'boolean',
            'price' => 'decimal:2',
            'sale_price' => 'decimal:2',
        ];
    }
}
