<?php

declare(strict_types=1);

namespace Modules\Billing\Models;

use App\Traits\HasMetadata;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Modules\Billing\Contracts\Invoiceable;

/**
 * @property int $id
 * @property int|null $invoice_id
 * @property string|null $name
 * @property string|null $description
 * @property float|string $quantity
 * @property float|string $unit_price
 * @property float|string $subtotal
 * @property float|string $tax_rate
 * @property float|string $tax_amount
 * @property float|string $discount_rate
 * @property float|string $discount_amount
 * @property float|string $total
 * @property int|null $invoiceable_id
 * @property string|null $invoiceable_type
 * @property int|null $sort_order
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Invoice|null $invoice
 * @property-read string $formatted_unit_price
 * @property-read string $formatted_total
 */
class InvoiceItem extends Model
{
    use HasFactory;
    use HasMetadata;

    protected $table = 'billing_invoice_items';

    protected $fillable = [
        'invoice_id',
        'name',
        'description',
        'quantity',
        'unit_price',
        'subtotal',
        'tax_rate',
        'tax_amount',
        'discount_rate',
        'discount_amount',
        'total',
        'invoiceable_id',
        'invoiceable_type',
        'sort_order',
        'metadata',
    ];

    protected $appends = [
        'formatted_unit_price',
        'formatted_total',
    ];

    /**
     * Parent invoice.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    /**
     * Invoiceable item (polymorphic).
     */
    public function invoiceable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Create item from an Invoiceable model.
     */
    public static function createFromInvoiceable(Invoice $invoice, Invoiceable $invoiceable, int $sortOrder = 0): self
    {
        $lineItem = $invoiceable->toInvoiceLineItem();

        return self::query()->create([
            'invoice_id' => $invoice->id,
            'name' => $lineItem['description'],
            'description' => $lineItem['description'],
            'quantity' => $lineItem['quantity'],
            'unit_price' => $lineItem['unit_price'],
            'subtotal' => $lineItem['quantity'] * $lineItem['unit_price'],
            'tax_rate' => $lineItem['tax_rate'],
            'tax_amount' => $lineItem['tax_amount'],
            'total' => $lineItem['total'],
            'invoiceable_id' => $invoiceable->getKey(),
            'invoiceable_type' => $invoiceable::class,
            'sort_order' => $sortOrder,
            'metadata' => $lineItem['metadata'],
        ]);
    }

    /**
     * Calculate totals based on quantity and unit price.
     */
    public function calculateTotals(): void
    {
        $this->subtotal = $this->quantity * $this->unit_price;
        $this->tax_amount = $this->subtotal * $this->tax_rate / 100;
        $this->discount_amount = $this->subtotal * $this->discount_rate / 100;
        $this->total = $this->subtotal + $this->tax_amount - $this->discount_amount;
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'discount_rate' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'sort_order' => 'integer',
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    protected function getFormattedUnitPriceAttribute(): string
    {
        $currency = $this->invoice->currency ?? 'USD';

        return number_format($this->unit_price, 2).' '.$currency;
    }

    protected function getFormattedTotalAttribute(): string
    {
        $currency = $this->invoice->currency ?? 'USD';

        return number_format($this->total, 2).' '.$currency;
    }

    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (self $item): void {
            $item->calculateTotals();
        });

        static::saved(function (self $item): void {
            $item->invoice?->recalculateTotals();
        });

        static::deleted(function (self $item): void {
            $item->invoice?->recalculateTotals();
        });
    }
}
