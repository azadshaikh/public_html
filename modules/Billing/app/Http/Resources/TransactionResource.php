<?php

declare(strict_types=1);

namespace Modules\Billing\Http\Resources;

use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldResource;
use Modules\Billing\Definitions\TransactionDefinition;
use Modules\Billing\Models\Transaction;
use Modules\Customers\Models\Customer;

/** @mixin Transaction */
class TransactionResource extends ScaffoldResource
{
    protected function definition(): ScaffoldDefinition
    {
        return new TransactionDefinition;
    }

    protected function customFields(): array
    {
        return [
            'show_url' => route($this->scaffold()->getRoutePrefix().'.show', $this->id),

            'transaction_id' => $this->transaction_id,
            'reference' => $this->reference,
            'customer_display' => $this->getCustomerDisplay(),
            'source_display' => $this->getSourceDisplay(),

            'amount' => (float) $this->amount,
            'formatted_amount' => $this->formatted_amount,
            'currency' => $this->currency,

            'type' => $this->type,
            'type_label' => $this->type_label,
            'type_badge' => $this->getTypeBadge(),

            'payment_method' => $this->payment_method,
            'payment_method_label' => $this->getMethodLabel(),
            'payment_method_badge' => $this->getMethodBadge(),

            'status' => $this->status,
            'status_label' => $this->status_label,
            'status_badge' => $this->status_badge,

            'created_at' => $this->created_at ? app_date_time_format($this->created_at, 'datetime') : null,
        ];
    }

    protected function getCustomerDisplay(): string
    {
        $customer = $this->whenLoaded('customer');

        if ($customer instanceof Customer) {
            return $customer->company_name
                ?: $customer->contact_name
                ?: $customer->email
                ?: 'Customer #'.$customer->id;
        }

        return $this->customer_id ? 'Customer #'.$this->customer_id : '—';
    }

    protected function getSourceDisplay(): string
    {
        $transactionable = $this->whenLoaded('transactionable');

        if (! $transactionable) {
            return $this->description ?? '—';
        }

        if (property_exists($transactionable, 'invoice_number')) {
            return 'Invoice '.$transactionable->invoice_number;
        }

        if (property_exists($transactionable, 'payment_number')) {
            return 'Payment '.$transactionable->payment_number;
        }

        if (property_exists($transactionable, 'refund_number')) {
            return 'Refund '.$transactionable->refund_number;
        }

        if (property_exists($transactionable, 'credit_number')) {
            return 'Credit '.$transactionable->credit_number;
        }

        return $this->description ?? class_basename($transactionable);
    }

    protected function getTypeBadge(): string
    {
        return match ($this->type) {
            Transaction::TYPE_INVOICE => 'default',
            Transaction::TYPE_PAYMENT => 'success',
            Transaction::TYPE_REFUND => 'warning',
            Transaction::TYPE_CREDIT => 'info',
            Transaction::TYPE_DEBIT => 'danger',
            Transaction::TYPE_ADJUSTMENT => 'secondary',
            default => 'secondary',
        };
    }

    protected function getMethodLabel(): string
    {
        return match ($this->payment_method) {
            'card' => 'Card',
            'bank_transfer' => 'Bank Transfer',
            'cash' => 'Cash',
            'check' => 'Check',
            'paypal' => 'PayPal',
            'other' => 'Other',
            default => $this->payment_method ? ucfirst($this->payment_method) : '—',
        };
    }

    protected function getMethodBadge(): string
    {
        return match ($this->payment_method) {
            'card' => 'default',
            'bank_transfer' => 'info',
            'cash' => 'success',
            'check' => 'warning',
            'paypal' => 'secondary',
            'other' => 'outline',
            default => 'secondary',
        };
    }
}
