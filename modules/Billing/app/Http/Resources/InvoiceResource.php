<?php

declare(strict_types=1);

namespace Modules\Billing\Http\Resources;

use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldResource;
use Modules\Billing\Definitions\InvoiceDefinition;
use Modules\Billing\Models\Invoice;
use Modules\Customers\Models\Customer;

/** @mixin Invoice */
class InvoiceResource extends ScaffoldResource
{
    protected function definition(): ScaffoldDefinition
    {
        return new InvoiceDefinition;
    }

    protected function customFields(): array
    {
        return [
            'show_url' => route($this->scaffold()->getRoutePrefix().'.show', $this->id),

            'invoice_number' => $this->invoice_number,
            'reference' => $this->reference,
            'customer_display' => $this->getCustomerDisplay(),
            'billing_email' => $this->billing_email,
            'billing_phone' => $this->billing_phone,

            'subtotal' => (float) $this->subtotal,
            'tax_amount' => (float) $this->tax_amount,
            'discount_amount' => (float) $this->discount_amount,
            'total' => (float) $this->total,
            'amount_paid' => (float) $this->amount_paid,
            'amount_due' => (float) $this->amount_due,
            'formatted_total' => $this->formatted_total,
            'formatted_amount_due' => number_format((float) $this->amount_due, 2).' '.$this->currency,

            'currency' => $this->currency,
            'exchange_rate' => (float) $this->exchange_rate,

            'issue_date' => $this->issue_date ? app_date_time_format($this->issue_date, 'date') : null,
            'due_date' => $this->due_date ? app_date_time_format($this->due_date, 'date') : null,
            'paid_at' => $this->paid_at ? app_date_time_format($this->paid_at, 'datetime') : null,

            'status' => $this->status,
            'status_label' => $this->status_label,
            'status_badge' => $this->status_badge,
            'payment_status' => $this->payment_status,
            'payment_status_label' => $this->payment_status_label,
            'payment_status_badge' => $this->payment_status_badge,
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

        return $this->billing_name
            ?: $this->billing_email
            ?: 'Customer #'.$this->customer_id;
    }
}
