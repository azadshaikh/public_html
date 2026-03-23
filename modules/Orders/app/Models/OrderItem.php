<?php

declare(strict_types=1);

namespace Modules\Orders\Models;

use App\Traits\HasMetadata;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $order_id
 * @property int|null $plan_id
 * @property string $name
 * @property string|null $description
 * @property int $quantity
 * @property float $unit_price
 * @property float $total
 */
class OrderItem extends Model
{
    use HasFactory;
    use HasMetadata;

    protected $table = 'orders_order_items';

    protected $fillable = [
        'order_id',
        'plan_id',
        'name',
        'description',
        'quantity',
        'unit_price',
        'total',
        'metadata',
    ];

    public function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'total' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
