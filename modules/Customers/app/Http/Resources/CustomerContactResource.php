<?php

declare(strict_types=1);

namespace Modules\Customers\Http\Resources;

use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldResource;
use Modules\Customers\Definitions\CustomerContactDefinition;
use Modules\Customers\Models\CustomerContact;

/** @mixin CustomerContact */
class CustomerContactResource extends ScaffoldResource
{
    protected function definition(): ScaffoldDefinition
    {
        return new CustomerContactDefinition;
    }

    protected function customFields(): array
    {
        return [
            'show_url' => route($this->scaffold()->getRoutePrefix().'.show', $this->id),
            'edit_url' => route($this->scaffold()->getRoutePrefix().'.edit', $this->id),
            'customer_show_url' => $this->customer_id
                ? route('app.customers.show', $this->customer_id)
                : null,
            'customer_name' => $this->customer?->company_name,
            'full_name' => $this->full_name,
            'created_at_formatted' => $this->created_at ? app_date_time_format($this->created_at, 'date') : null,
            'status_label' => $this->status_label,
            'status_badge' => $this->status_badge,
        ];
    }
}
