<?php

declare(strict_types=1);

namespace Modules\Billing\Http\Resources;

use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldResource;
use Modules\Billing\Definitions\RefundDefinition;
use Modules\Billing\Models\Refund;
use Modules\Customers\Models\Customer;

/** @mixin Refund */
class RefundResource extends ScaffoldResource
{
    protected function definition(): ScaffoldDefinition
    {
        return new RefundDefinition;
    }

    protected function customFields(): array
    {
        return [
            'show_url' => route($this->scaffold()->getRoutePrefix().'.show', $this->id),

            'refund_number' => $this->refund_number,
            'reference' => $this->reference,
            'payment_id' => $this->payment_id,
            'payment_number' => $this->payment?->payment_number,
            'invoice_id' => $this->invoice_id,
            'invoice_number' => $this->invoice?->invoice_number,
            'customer_display' => $this->getCustomerDisplay(),

            'amount' => (float) $this->amount,
            'formatted_amount' => $this->formatted_amount,
            'currency' => $this->currency,

            'type' => $this->type,
            'type_label' => $this->type_label,
            'type_badge' => $this->getTypeBadge(),

            'status' => $this->status,
            'status_label' => $this->status_label,
            'status_badge' => $this->status_badge,

            'refunded_at' => $this->refunded_at ? app_date_time_format($this->refunded_at, 'datetime') : null,
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

    protected function getTypeBadge(): string
    {
        return match ($this->type) {
            Refund::TYPE_FULL => 'default',
            Refund::TYPE_PARTIAL => 'warning',
            default => 'secondary',
        };
    }
}
