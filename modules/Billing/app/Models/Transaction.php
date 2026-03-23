<?php

declare(strict_types=1);

namespace Modules\Billing\Models;

use App\Traits\AuditableTrait;
use App\Traits\HasMetadata;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Modules\Customers\Models\Customer;

/**
 * @property int $id
 * @property string|null $transaction_id
 * @property string|null $reference
 * @property int|null $transactionable_id
 * @property string|null $transactionable_type
 * @property int|null $customer_id
 * @property string|null $type
 * @property float|string $amount
 * @property string|null $currency
 * @property float|string|null $exchange_rate
 * @property float|string|null $balance_before
 * @property float|string|null $balance_after
 * @property string|null $payment_method
 * @property string|null $payment_gateway
 * @property string|null $gateway_transaction_id
 * @property array<string, mixed>|null $gateway_response
 * @property string|null $status
 * @property string|null $description
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Customer|null $customer
 * @property-read string $status_label
 * @property-read string $status_badge
 * @property-read string $type_label
 * @property-read string $formatted_amount
 */
class Transaction extends Model
{
    use AuditableTrait;
    use HasFactory;
    use HasMetadata;

    public const TYPE_INVOICE = 'invoice';

    public const TYPE_PAYMENT = 'payment';

    public const TYPE_REFUND = 'refund';

    public const TYPE_CREDIT = 'credit';

    public const TYPE_DEBIT = 'debit';

    public const TYPE_ADJUSTMENT = 'adjustment';

    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'billing_transactions';

    protected $fillable = [
        'transaction_id',
        'reference',
        'transactionable_id',
        'transactionable_type',
        'customer_id',
        'type',
        'amount',
        'currency',
        'exchange_rate',
        'balance_before',
        'balance_after',
        'payment_method',
        'payment_gateway',
        'gateway_transaction_id',
        'gateway_response',
        'status',
        'description',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected $appends = [
        'status_label',
        'status_badge',
        'type_label',
        'formatted_amount',
    ];

    /**
     * Source entity (invoice, payment, refund, credit).
     */
    public function transactionable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Customer relationship.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * Generate a unique transaction ID.
     */
    public static function generateTransactionId(): string
    {
        $prefix = 'TXN-';
        $date = now()->format('Ymd');
        $random = strtoupper(substr(md5(uniqid((string) mt_rand(), true)), 0, 6));

        return $prefix.$date.$random;
    }

    /**
     * Create transaction from a payment.
     */
    public static function createFromPayment(Payment $payment): self
    {
        return self::query()->create([
            'transaction_id' => self::generateTransactionId(),
            'transactionable_id' => $payment->id,
            'transactionable_type' => Payment::class,
            'customer_id' => $payment->customer_id,
            'type' => self::TYPE_PAYMENT,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'exchange_rate' => $payment->exchange_rate ?? 1.0,
            'payment_method' => $payment->payment_method,
            'payment_gateway' => $payment->payment_gateway,
            'gateway_transaction_id' => $payment->gateway_transaction_id,
            'status' => $payment->status === Payment::STATUS_COMPLETED
                ? self::STATUS_COMPLETED
                : self::STATUS_PENDING,
            'description' => 'Payment '.$payment->payment_number,
        ]);
    }

    /**
     * Create transaction from a refund.
     */
    public static function createFromRefund(Refund $refund): self
    {
        return self::query()->create([
            'transaction_id' => self::generateTransactionId(),
            'transactionable_id' => $refund->id,
            'transactionable_type' => Refund::class,
            'customer_id' => $refund->customer_id,
            'type' => self::TYPE_REFUND,
            'amount' => $refund->amount,
            'currency' => $refund->currency,
            'exchange_rate' => $refund->payment->exchange_rate ?? 1.0,
            'payment_gateway' => $refund->payment->payment_gateway ?? null,
            'gateway_transaction_id' => $refund->gateway_refund_id,
            'status' => $refund->status === Refund::STATUS_COMPLETED
                ? self::STATUS_COMPLETED
                : self::STATUS_PENDING,
            'description' => 'Refund '.$refund->refund_number,
        ]);
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'exchange_rate' => 'decimal:6',
            'balance_before' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'gateway_response' => 'array',
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    protected function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Pending',
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
            self::STATUS_COMPLETED => 'success',
            self::STATUS_FAILED => 'danger',
            self::STATUS_CANCELLED => 'secondary',
            default => 'secondary',
        };
    }

    protected function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_INVOICE => 'Invoice',
            self::TYPE_PAYMENT => 'Payment',
            self::TYPE_REFUND => 'Refund',
            self::TYPE_CREDIT => 'Credit',
            self::TYPE_DEBIT => 'Debit',
            self::TYPE_ADJUSTMENT => 'Adjustment',
            default => ucfirst($this->type ?? 'unknown'),
        };
    }

    protected function getFormattedAmountAttribute(): string
    {
        $prefix = in_array($this->type, [self::TYPE_REFUND, self::TYPE_CREDIT]) ? '+' : '';
        if (in_array($this->type, [self::TYPE_INVOICE, self::TYPE_DEBIT])) {
            $prefix = '-';
        }

        return $prefix.number_format(abs((float) $this->amount), 2).' '.$this->currency;
    }

    /**
     * Scope for a specific customer.
     */
    #[Scope]
    protected function forCustomer(Builder $query, int $customerId): Builder
    {
        return $query->where('customer_id', $customerId);
    }
}
