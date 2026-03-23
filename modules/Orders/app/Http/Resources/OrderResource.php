<?php

declare(strict_types=1);

namespace Modules\Orders\Http\Resources;

use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldResource;
use Modules\Customers\Models\Customer;
use Modules\Orders\Definitions\OrderDefinition;
use Modules\Orders\Models\Order;

class OrderResource extends ScaffoldResource
{
    protected function definition(): ScaffoldDefinition
    {
        return new OrderDefinition;
    }

    /**
     * @return array<string, mixed>
     */
    protected function customFields(): array
    {
        /** @var Order $order */
        $order = $this->resource;

        return [
            'show_url' => route($this->scaffold()->getRoutePrefix().'.show', $order->id),
            'customer_display' => $this->getCustomerDisplay($order),

            'type' => $order->type,
            'type_label' => Order::typeLabel($order->type),
            'type_badge' => Order::typeBadgeVariant($order->type),

            'status' => $order->status,
            'status_label' => $order->status_label,
            'status_badge' => $order->status_badge,

            'total_display' => number_format((float) $order->total, 2).' '.$order->currency,

            'paid_at_formatted' => $order->paid_at?->format('M j, Y g:i A'),
        ];
    }

    private function getCustomerDisplay(Order $order): string
    {
        $customer = $this->whenLoaded('customer');

        if ($customer instanceof Customer) {
            $contactName = trim(($customer->contact_first_name ?? '').' '.($customer->contact_last_name ?? ''));

            return $customer->company_name
                ?: ($contactName ?: '')
                ?: ($customer->email ?? '')
                ?: 'Customer #'.$order->customer_id;
        }

        return $order->customer_id
            ? 'Customer #'.$order->customer_id
            : '—';
    }
}
