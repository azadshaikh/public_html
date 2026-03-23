<?php

declare(strict_types=1);

namespace Modules\Billing\Models;

use App\Traits\AuditableTrait;
use App\Traits\HasMetadata;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Customers\Models\Customer;

/**
 * @property int $id
 * @property string|null $payment_number
 * @property string|null $reference
 * @property string|null $idempotency_key
 * @property int|null $invoice_id
 * @property int|null $customer_id
 * @property float|string $amount
 * @property string|null $currency
 * @property float|string|null $exchange_rate
 * @property string|null $payment_method
 * @property string|null $payment_gateway
 * @property string|null $status
 * @property string|null $gateway_transaction_id
 * @property array<string, mixed>|null $gateway_response
 * @property Carbon|null $paid_at
 * @property Carbon|null $failed_at
 * @property string|null $notes
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Invoice|null $invoice
 * @property-read Customer|null $customer
 * @property-read Collection<int, Refund> $refunds
 * @property-read string $status_label
 * @property-read string $status_badge
 * @property-read string $method_label
 * @property-read string $formatted_amount
 */
class Payment extends Model
{
    use AuditableTrait;
    use HasFactory;
    use HasMetadata;
    use SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_REFUNDED = 'refunded';

    public const METHOD_CARD = 'card';

    public const METHOD_BANK_TRANSFER = 'bank_transfer';

    public const METHOD_CASH = 'cash';

    public const METHOD_CHECK = 'check';

    public const METHOD_PAYPAL = 'paypal';

    public const METHOD_OTHER = 'other';

    protected $table = 'billing_payments';

    protected $fillable = [
        'payment_number',
        'reference',
        'idempotency_key',
        'invoice_id',
        'customer_id',
        'amount',
        'currency',
        'exchange_rate',
        'payment_method',
        'payment_gateway',
        'status',
        'gateway_transaction_id',
        'gateway_response',
        'paid_at',
        'failed_at',
        'notes',
        'metadata',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $appends = [
        'status_label',
        'status_badge',
        'method_label',
        'formatted_amount',
    ];

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
     * Refunds for this payment.
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class, 'payment_id');
    }

    /**
     * Transactions for this payment.
     */
    public function transactions(): MorphMany
    {
        return $this->morphMany(Transaction::class, 'transactionable');
    }

    /**
     * Mark payment as completed.
     */
    public function markAsCompleted(): void
    {
        DB::transaction(function (): void {
            $this->update([
                'status' => self::STATUS_COMPLETED,
                'paid_at' => now(),
            ]);

            // Update invoice payment status
            if ($this->invoice) {
                $invoice = $this->invoice->fresh();

                if (! $invoice) {
                    return;
                }

                $newAmountPaid = (float) $invoice->amount_paid + (float) $this->amount;
                $newAmountDue = max(0.0, (float) $invoice->amount_due - (float) $this->amount);

                $invoice->update([
                    'amount_paid' => $newAmountPaid,
                    'amount_due' => $newAmountDue,
                ]);

                if ($newAmountDue <= 0) {
                    $invoice->update([
                        'payment_status' => Invoice::PAYMENT_STATUS_PAID,
                        'status' => Invoice::STATUS_PAID,
                        'paid_at' => now(),
                    ]);
                } else {
                    $invoice->update([
                        'payment_status' => Invoice::PAYMENT_STATUS_PARTIAL,
                        'status' => Invoice::STATUS_PARTIAL,
                    ]);
                }
            }
        });
    }

    /**
     * Mark payment as failed.
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
     * Generate a unique payment number.
     */
    public static function generatePaymentNumber(): string
    {
        $prefix = 'PAY-';
        $date = now()->format('Ymd');
        $lastPayment = self::query()->where('payment_number', 'like', $prefix.$date.'%')
            ->orderBy('payment_number', 'desc')
            ->first();

        if ($lastPayment) {
            $lastNumber = (int) substr((string) $lastPayment->payment_number, -4);
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
            'exchange_rate' => 'decimal:6',
            'gateway_response' => 'array',
            'paid_at' => 'datetime',
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
            self::STATUS_REFUNDED => 'Refunded',
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
            self::STATUS_REFUNDED => 'secondary',
            default => 'secondary',
        };
    }

    protected function getMethodLabelAttribute(): string
    {
        return match ($this->payment_method) {
            self::METHOD_CARD => 'Credit Card',
            self::METHOD_BANK_TRANSFER => 'Bank Transfer',
            self::METHOD_CASH => 'Cash',
            self::METHOD_CHECK => 'Check',
            self::METHOD_PAYPAL => 'PayPal',
            self::METHOD_OTHER => 'Other',
            default => ucfirst($this->payment_method ?? 'unknown'),
        };
    }

    protected function getFormattedAmountAttribute(): string
    {
        return number_format((float) $this->amount, 2).' '.$this->currency;
    }
}
