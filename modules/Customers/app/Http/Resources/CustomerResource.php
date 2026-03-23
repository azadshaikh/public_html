<?php

declare(strict_types=1);

namespace Modules\Customers\Http\Resources;

use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldResource;
use Modules\Customers\Definitions\CustomerDefinition;
use Modules\Customers\Models\Customer;

/** @mixin Customer */
class CustomerResource extends ScaffoldResource
{
    protected function definition(): ScaffoldDefinition
    {
        return new CustomerDefinition;
    }

    protected function customFields(): array
    {
        $billingSummary = $this->billing_summary ?? null;

        return [
            'show_url' => route($this->scaffold()->getRoutePrefix().'.show', $this->id),
            'edit_url' => route($this->scaffold()->getRoutePrefix().'.edit', $this->id),

            'unique_id' => $this->unique_id,
            'type' => $this->type,
            'company_name' => $this->company_name,
            'company_name_display' => $this->company_name_display,
            'contact_name' => $this->contact_name,

            'tier' => $this->tier?->value,
            'status' => $this->status?->value,
            'industry' => $this->industry?->value,
            'customer_group' => $this->customer_group?->value,

            'billing_total' => data_get($billingSummary, 'total_spent', 0),
            'billing_total_formatted' => $this->formatCurrency(data_get($billingSummary, 'total_spent', 0), data_get($billingSummary, 'currency', 'USD')),

            'last_contacted_at' => $this->last_contacted_at,
            'last_contacted_at_formatted' => $this->last_contacted_at ? app_date_time_format($this->last_contacted_at, 'datetime') : null,
            'next_action_date' => $this->next_action_date,

            'created_at_formatted' => $this->created_at ? app_date_time_format($this->created_at, 'date') : null,
            'updated_at_formatted' => $this->updated_at ? app_date_time_format($this->updated_at, 'date') : null,

            'status_label' => $this->status_label,
            'status_badge' => $this->status_badge,
        ];
    }

    private function formatCurrency(float|int $amount, string $currency): string
    {
        return strtoupper($currency).' '.number_format((float) $amount, 2);
    }
}
