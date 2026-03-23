<?php

declare(strict_types=1);

namespace Modules\Billing\Http\Resources;

use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldResource;
use Modules\Billing\Definitions\CreditDefinition;
use Modules\Billing\Models\Credit;
use Modules\Customers\Models\Customer;

/** @mixin Credit */
class CreditResource extends ScaffoldResource
{
    protected function definition(): ScaffoldDefinition
    {
        return new CreditDefinition;
    }

    protected function customFields(): array
    {
        return [
            'show_url' => route($this->scaffold()->getRoutePrefix().'.show', $this->id),

            'credit_number' => $this->credit_number,
            'reference' => $this->reference,
            'invoice_id' => $this->invoice_id,
            'customer_display' => $this->getCustomerDisplay(),

            'amount' => (float) $this->amount,
            'amount_used' => (float) $this->amount_used,
            'amount_remaining' => (float) $this->amount_remaining,
            'formatted_amount' => $this->formatted_amount,
            'formatted_remaining' => $this->formatted_remaining,
            'currency' => $this->currency,

            'type' => $this->type,
            'type_label' => $this->type_label,
            'type_badge' => $this->getTypeBadge(),

            'status' => $this->status,
            'status_label' => $this->status_label,
            'status_badge' => $this->status_badge,

            'expires_at' => $this->expires_at ? app_date_time_format($this->expires_at, 'date') : null,
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
            Credit::TYPE_CREDIT_NOTE => 'default',
            Credit::TYPE_REFUND_CREDIT => 'info',
            Credit::TYPE_PROMO_CREDIT => 'success',
            Credit::TYPE_GOODWILL => 'warning',
            default => 'secondary',
        };
    }
}
