<?php

declare(strict_types=1);

namespace Modules\Billing\Http\Resources;

use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldResource;
use Modules\Billing\Definitions\PaymentDefinition;
use Modules\Billing\Models\Payment;
use Modules\Customers\Models\Customer;

/** @mixin Payment */
class PaymentResource extends ScaffoldResource
{
    protected function definition(): ScaffoldDefinition
    {
        return new PaymentDefinition;
    }

    protected function customFields(): array
    {
        return [
            'show_url' => route($this->scaffold()->getRoutePrefix().'.show', $this->id),

            'payment_number' => $this->payment_number,
            'reference' => $this->reference,
            'invoice_id' => $this->invoice_id,
            'invoice_number' => $this->invoice?->invoice_number,
            'customer_display' => $this->getCustomerDisplay(),

            'amount' => (float) $this->amount,
            'formatted_amount' => $this->formatted_amount,
            'currency' => $this->currency,
            'exchange_rate' => (float) $this->exchange_rate,

            'payment_method' => $this->payment_method,
            'payment_method_label' => $this->method_label,
            'payment_method_badge' => $this->getMethodBadge(),
            'payment_gateway' => $this->payment_gateway,

            'status' => $this->status,
            'status_label' => $this->status_label,
            'status_badge' => $this->status_badge,

            'paid_at' => $this->paid_at ? app_date_time_format($this->paid_at, 'datetime') : null,
            'failed_at' => $this->failed_at ? app_date_time_format($this->failed_at, 'datetime') : null,
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

        return 'Customer #'.$this->customer_id;
    }

    protected function getMethodBadge(): string
    {
        return match ($this->payment_method) {
            Payment::METHOD_CARD => 'default',
            Payment::METHOD_BANK_TRANSFER => 'info',
            Payment::METHOD_CASH => 'success',
            Payment::METHOD_CHECK => 'warning',
            Payment::METHOD_PAYPAL => 'secondary',
            Payment::METHOD_OTHER => 'outline',
            default => 'secondary',
        };
    }
}
