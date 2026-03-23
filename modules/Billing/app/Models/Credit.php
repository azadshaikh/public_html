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
use Illuminate\Support\Facades\DB;
use Modules\Customers\Models\Customer;

/**
 * @property int $id
 * @property string|null $credit_number
 * @property string|null $reference
 * @property int|null $customer_id
 * @property int|null $invoice_id
 * @property float|string $amount
 * @property float|string $amount_used
 * @property float|string $amount_remaining
 * @property string|null $currency
 * @property string|null $type
 * @property string|null $status
 * @property Carbon|null $expires_at
 * @property string|null $reason
 * @property string|null $notes
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Invoice|null $invoice
 * @property-read Customer|null $customer
 * @property-read string $status_label
 * @property-read string $status_badge
 * @property-read string $type_label
 * @property-read string $formatted_amount
 * @property-read string $formatted_remaining
 */
class Credit extends Model
{
    use AuditableTrait;
    use HasFactory;
    use HasMetadata;
    use SoftDeletes;

    public const TYPE_CREDIT_NOTE = 'credit_note';

    public const TYPE_REFUND_CREDIT = 'refund_credit';

    public const TYPE_PROMO_CREDIT = 'promo_credit';

    public const TYPE_GOODWILL = 'goodwill';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_EXHAUSTED = 'exhausted';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'billing_credits';

    protected $fillable = [
        'credit_number',
        'reference',
        'customer_id',
        'invoice_id',
        'amount',
        'amount_used',
        'amount_remaining',
        'currency',
        'type',
        'status',
        'expires_at',
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
        'formatted_remaining',
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
     * Transactions for this credit.
     */
    public function transactions(): MorphMany
    {
        return $this->morphMany(Transaction::class, 'transactionable');
    }

    /**
     * Apply credit towards an invoice.
     */
    public function applyToInvoice(Invoice $invoice, float $amount): float
    {
        return DB::transaction(function () use ($invoice, $amount): float {
            $applicableAmount = min($amount, $this->amount_remaining, $invoice->amount_due);

            if ($applicableAmount <= 0) {
                return 0;
            }

            $this->amount_used += $applicableAmount;
            $this->amount_remaining -= $applicableAmount;

            if ($this->amount_remaining <= 0) {
                $this->status = self::STATUS_EXHAUSTED;
            }

            $this->save();

            $newAmountPaid = (float) $invoice->amount_paid + $applicableAmount;
            $newAmountDue = max(0.0, (float) $invoice->amount_due - $applicableAmount);

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
            } elseif ($newAmountPaid > 0) {
                $invoice->update([
                    'payment_status' => Invoice::PAYMENT_STATUS_PARTIAL,
                    'status' => Invoice::STATUS_PARTIAL,
                ]);
            }

            return $applicableAmount;
        });
    }

    /**
     * Check if credit is valid for use.
     */
    public function isValid(): bool
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return $this->amount_remaining > 0;
    }

    /**
     * Generate a unique credit number.
     */
    public static function generateCreditNumber(): string
    {
        $prefix = 'CRD-';
        $date = now()->format('Ymd');
        $lastCredit = self::query()->where('credit_number', 'like', $prefix.$date.'%')
            ->orderBy('credit_number', 'desc')
            ->first();

        if ($lastCredit) {
            $lastNumber = (int) substr((string) $lastCredit->credit_number, -4);
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
            'amount_used' => 'decimal:2',
            'amount_remaining' => 'decimal:2',
            'expires_at' => 'date',
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    protected function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_EXHAUSTED => 'Exhausted',
            self::STATUS_EXPIRED => 'Expired',
            self::STATUS_CANCELLED => 'Cancelled',
            default => ucfirst($this->status ?? 'unknown'),
        };
    }

    protected function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_ACTIVE => 'success',
            self::STATUS_EXHAUSTED => 'secondary',
            self::STATUS_EXPIRED => 'warning',
            self::STATUS_CANCELLED => 'danger',
            default => 'secondary',
        };
    }

    protected function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_CREDIT_NOTE => 'Credit Note',
            self::TYPE_REFUND_CREDIT => 'Refund Credit',
            self::TYPE_PROMO_CREDIT => 'Promotional Credit',
            self::TYPE_GOODWILL => 'Goodwill Credit',
            default => ucfirst($this->type ?? 'unknown'),
        };
    }

    protected function getFormattedAmountAttribute(): string
    {
        $amount = is_numeric($this->amount) ? (float) $this->amount : 0.0;

        return number_format($amount, 2).' '.$this->currency;
    }

    protected function getFormattedRemainingAttribute(): string
    {
        $remaining = is_numeric($this->amount_remaining) ? (float) $this->amount_remaining : 0.0;

        return number_format($remaining, 2).' '.$this->currency;
    }
}
