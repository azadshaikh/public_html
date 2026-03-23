<?php

declare(strict_types=1);

namespace Modules\Billing\Models;

use App\Traits\AuditableTrait;
use App\Traits\HasMetadata;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Customers\Models\Customer;

/**
 * @property int $id
 * @property string|null $refund_number
 * @property string|null $reference
 * @property string|null $idempotency_key
 * @property int|null $payment_id
 * @property int|null $invoice_id
 * @property int|null $customer_id
 * @property float|string $amount
 * @property string|null $currency
 * @property string|null $type
 * @property string|null $status
 * @property string|null $gateway_refund_id
 * @property array<string, mixed>|null $gateway_response
 * @property Carbon|null $refunded_at
 * @property Carbon|null $failed_at
 * @property string|null $reason
 * @property string|null $notes
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Payment|null $payment
 * @property-read Invoice|null $invoice
 * @property-read Customer|null $customer
 * @property-read string $status_label
 * @property-read string $status_badge
 * @property-read string $type_label
 * @property-read string $formatted_amount
 */
class Refund extends Model
{
    use AuditableTrait;
    use HasFactory;
    use HasMetadata;
    use SoftDeletes;

    public const TYPE_FULL = 'full';

    public const TYPE_PARTIAL = 'partial';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'billing_refunds';

    protected $fillable = [
        'refund_number',
        'reference',
        'idempotency_key',
        'payment_id',
        'invoice_id',
        'customer_id',
        'amount',
        'currency',
        'type',
        'status',
        'gateway_refund_id',
        'gateway_response',
        'refunded_at',
        'failed_at',
        'reason',
        'notes',
        'metadata',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $appends = [
        'status_label',
        'status_badge',
        'type_label',
        'formatted_amount',
    ];

    /**
     * Original payment.
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    /**
     * Related invoice.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    /**
     * Customer relationship.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * Transactions for this refund.
     */
    public function transactions(): MorphMany
    {
        return $this->morphMany(Transaction::class, 'transactionable');
    }

    /**
     * Mark refund as completed.
     */
    public function markAsCompleted(): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'refunded_at' => now(),
        ]);

        // Update payment status
        $this->payment?->update(['status' => Payment::STATUS_REFUNDED]);

        // Update invoice if exists
        if ($this->invoice) {
            $this->invoice->decrement('amount_paid', $this->amount);
            $this->invoice->increment('amount_due', $this->amount);

            if ($this->type === self::TYPE_FULL) {
                $this->invoice->update([
                    'status' => Invoice::STATUS_REFUNDED,
                    'payment_status' => Invoice::PAYMENT_STATUS_REFUNDED,
                ]);
            }
        }
    }

    /**
     * Mark refund as failed.
     */
    public function markAsFailed(array $response = []): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'failed_at' => now(),
            'gateway_response' => $response,
        ]);
    }

    /**
     * Generate a unique refund number.
     */
    public static function generateRefundNumber(): string
    {
        $prefix = 'REF-';
        $date = now()->format('Ymd');
        $lastRefund = self::query()->where('refund_number', 'like', $prefix.$date.'%')
            ->orderBy('refund_number', 'desc')
            ->first();

        if ($lastRefund) {
            $lastNumber = (int) substr((string) $lastRefund->refund_number, -4);
            $newNumber = str_pad((string) ($lastNumber + 1), 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }

        return $prefix.$date.$newNumber;
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'gateway_response' => 'array',
            'refunded_at' => 'datetime',
            'failed_at' => 'datetime',
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    protected function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_PROCESSING => 'Processing',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_FAILED => 'Failed',
            self::STATUS_CANCELLED => 'Cancelled',
            default => ucfirst($this->status ?? 'unknown'),
        };
    }

    protected function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'warning',
            self::STATUS_PROCESSING => 'info',
            self::STATUS_COMPLETED => 'success',
            self::STATUS_FAILED => 'danger',
            self::STATUS_CANCELLED => 'secondary',
            default => 'secondary',
        };
    }

    protected function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_FULL => 'Full Refund',
            self::TYPE_PARTIAL => 'Partial Refund',
            default => ucfirst($this->type ?? 'unknown'),
        };
    }

    protected function getFormattedAmountAttribute(): string
    {
        return number_format((float) $this->amount, 2).' '.$this->currency;
    }
}
