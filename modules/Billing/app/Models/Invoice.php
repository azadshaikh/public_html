<?php

declare(strict_types=1);

namespace Modules\Billing\Models;

use App\Traits\AuditableTrait;
use App\Traits\HasMetadata;
use App\Traits\HasNotes;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Customers\Models\Customer;

/**
 * @property int $id
 * @property string|null $invoice_number
 * @property string|null $reference
 * @property int|null $customer_id
 * @property string|null $billing_name
 * @property string|null $billing_email
 * @property string|null $billing_phone
 * @property string|null $billing_address
 * @property float|string $subtotal
 * @property float|string $tax_amount
 * @property float|string $discount_amount
 * @property float|string $total
 * @property float|string $amount_paid
 * @property float|string $amount_due
 * @property string|null $currency
 * @property float|string|null $exchange_rate
 * @property Carbon|null $issue_date
 * @property Carbon|null $due_date
 * @property Carbon|null $paid_at
 * @property string|null $status
 * @property string|null $payment_status
 * @property string|null $stripe_invoice_id
 * @property string|null $notes
 * @property string|null $terms
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Customer|null $customer
 * @property-read Collection<int, InvoiceItem> $items
 * @property-read Collection<int, Payment> $payments
 * @property-read Collection<int, Refund> $refunds
 * @property-read Collection<int, Credit> $credits
 * @property-read string $status_label
 * @property-read string $status_badge
 * @property-read string $payment_status_label
 * @property-read string $payment_status_badge
 * @property-read string $formatted_total
 */
class Invoice extends Model
{
    use AuditableTrait;
    use HasFactory;
    use HasMetadata;
    use HasNotes;
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_PAID = 'paid';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_OVERDUE = 'overdue';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_REFUNDED = 'refunded';

    public const PAYMENT_STATUS_UNPAID = 'unpaid';

    public const PAYMENT_STATUS_PARTIAL = 'partial';

    public const PAYMENT_STATUS_PAID = 'paid';

    public const PAYMENT_STATUS_REFUNDED = 'refunded';

    protected $table = 'billing_invoices';

    protected $fillable = [
        'invoice_number',
        'reference',
        'customer_id',
        'billing_name',
        'billing_email',
        'billing_phone',
        'billing_address',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'total',
        'amount_paid',
        'amount_due',
        'currency',
        'exchange_rate',
        'issue_date',
        'due_date',
        'paid_at',
        'status',
        'payment_status',
        'stripe_invoice_id',
        'notes',
        'terms',
        'metadata',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $appends = [
        'status_label',
        'status_badge',
        'payment_status_label',
        'payment_status_badge',
        'formatted_total',
    ];

    /**
     * Customer relationship.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * Invoice items.
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class, 'invoice_id')->orderBy('sort_order');
    }

    /**
     * Payments associated with this invoice.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'invoice_id');
    }

    /**
     * Refunds associated with this invoice.
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class, 'invoice_id');
    }

    /**
     * Credits associated with this invoice.
     */
    public function credits(): HasMany
    {
        return $this->hasMany(Credit::class, 'invoice_id');
    }

    /**
     * Transactions for this invoice.
     */
    public function transactions(): MorphMany
    {
        return $this->morphMany(Transaction::class, 'transactionable');
    }

    public function formatCurrency(float|string $amount): string
    {
        $normalized = is_numeric($amount) ? (float) $amount : 0.0;

        return number_format($normalized, 2).' '.$this->currency;
    }

    /**
     * Check if invoice is overdue.
     */
    public function isOverdue(): bool
    {
        return $this->due_date->isPast() && $this->payment_status !== self::PAYMENT_STATUS_PAID;
    }

    /**
     * Check if invoice can be edited.
     */
    public function canEdit(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_PENDING]);
    }

    /**
     * Recalculate totals from items.
     */
    public function recalculateTotals(): void
    {
        $items = $this->items;
        $subtotal = $items->sum('subtotal');
        $taxAmount = $items->sum('tax_amount');
        $discountAmount = $items->sum('discount_amount');
        $itemsTotal = $items->sum('total');
        $total = $items->isNotEmpty() ? $itemsTotal : ($subtotal + $taxAmount - $discountAmount);

        $this->update([
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'discount_amount' => $discountAmount,
            'total' => $total,
            'amount_due' => $total - $this->amount_paid,
        ]);
    }

    /**
     * Generate a unique invoice number based on the configured format.
     */
    public static function generateInvoiceNumber(): string
    {
        $prefix = (string) setting('billing_invoice_prefix', 'INV-');
        $digitLength = (int) setting('billing_invoice_digit_length', 3);
        $format = (string) setting('billing_invoice_format', 'date_sequence');
        $now = now();

        [$datePart, $pattern] = match ($format) {
            'year_sequence' => [$now->format('Y').'-',  $prefix.$now->format('Y').'-'.'%'],
            'year_month_sequence' => [$now->format('Ym').'-', $prefix.$now->format('Ym').'-'.'%'],
            'sequence_only' => ['',                        $prefix.'%'],
            default => [$now->format('Ymd'),       $prefix.$now->format('Ymd').'%'],
        };

        $lastInvoice = self::query()
            ->where('invoice_number', 'like', $pattern)
            ->orderBy('invoice_number', 'desc')
            ->first();

        if ($lastInvoice) {
            $lastNumber = (int) substr((string) $lastInvoice->invoice_number, -$digitLength);
            $next = $lastNumber + 1;
        } else {
            $next = (int) setting('billing_invoice_serial_number', 1);
        }

        $seq = str_pad((string) $next, $digitLength, '0', STR_PAD_LEFT);

        return $prefix.$datePart.$seq;
    }

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'amount_due' => 'decimal:2',
            'exchange_rate' => 'decimal:6',
            'issue_date' => 'date',
            'due_date' => 'date',
            'paid_at' => 'datetime',
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    protected function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_PENDING => 'Pending',
            self::STATUS_SENT => 'Sent',
            self::STATUS_PAID => 'Paid',
            self::STATUS_PARTIAL => 'Partial',
            self::STATUS_OVERDUE => 'Overdue',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_REFUNDED => 'Refunded',
            default => ucfirst($this->status ?? 'unknown'),
        };
    }

    protected function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'secondary',
            self::STATUS_PENDING => 'warning',
            self::STATUS_SENT => 'info',
            self::STATUS_PAID => 'success',
            self::STATUS_PARTIAL => 'default',
            self::STATUS_OVERDUE => 'danger',
            self::STATUS_CANCELLED => 'secondary',
            self::STATUS_REFUNDED => 'warning',
            default => 'secondary',
        };
    }

    protected function getPaymentStatusLabelAttribute(): string
    {
        return match ($this->payment_status) {
            self::PAYMENT_STATUS_UNPAID => 'Unpaid',
            self::PAYMENT_STATUS_PARTIAL => 'Partial',
            self::PAYMENT_STATUS_PAID => 'Paid',
            self::PAYMENT_STATUS_REFUNDED => 'Refunded',
            default => ucfirst($this->payment_status ?? 'unknown'),
        };
    }

    protected function getPaymentStatusBadgeAttribute(): string
    {
        return match ($this->payment_status) {
            self::PAYMENT_STATUS_UNPAID => 'danger',
            self::PAYMENT_STATUS_PARTIAL => 'warning',
            self::PAYMENT_STATUS_PAID => 'success',
            self::PAYMENT_STATUS_REFUNDED => 'info',
            default => 'secondary',
        };
    }

    protected function getFormattedTotalAttribute(): string
    {
        return $this->formatCurrency($this->total);
    }
}
