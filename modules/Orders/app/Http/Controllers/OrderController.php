<?php

declare(strict_types=1);

namespace Modules\Orders\Http\Controllers;

use App\Scaffold\ScaffoldController;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Controllers\HasMiddleware;
use Modules\Orders\Definitions\OrderDefinition;
use Modules\Orders\Models\Order;
use Modules\Orders\Services\OrderScaffoldService;

class OrderController extends ScaffoldController implements HasMiddleware
{
    public function __construct(
        private readonly OrderScaffoldService $orderScaffoldService
    ) {}

    public static function middleware(): array
    {
        return (new OrderDefinition)->getMiddleware();
    }

    protected function service(): OrderScaffoldService
    {
        return $this->orderScaffoldService;
    }

    protected function transformModelForShow(Model $model): array
    {
        $model->load(['items', 'customer']);

        $data = $model->toArray();

        if ($model instanceof Order) {
            $data['type_label'] = Order::typeLabel($model->type);
            $data['type_badge'] = Order::typeBadgeVariant($model->type);
            $data['total_display'] = number_format((float) $model->total, 2).' '.$model->currency;
            $data['subtotal_display'] = number_format((float) $model->subtotal, 2).' '.$model->currency;
            $data['discount_display'] = number_format((float) $model->discount_amount, 2).' '.$model->currency;
            $data['tax_display'] = number_format((float) $model->tax_amount, 2).' '.$model->currency;
            $data['paid_at_formatted'] = $model->paid_at?->format('M j, Y g:i A');
            $data['created_at_formatted'] = $model->created_at?->format('M j, Y g:i A');
            $data['customer_display'] = $this->getCustomerDisplay($model);
        }

        return $data;
    }

    private function getCustomerDisplay(Order $order): string
    {
        if ($order->relationLoaded('customer') && $order->customer) {
            $customer = $order->customer;
            $contactName = trim(($customer->contact_first_name ?? '').' '.($customer->contact_last_name ?? ''));

            return $customer->company_name ?: ($contactName ?: '') ?: ($customer->email ?? '') ?: 'Customer #'.$order->customer_id;
        }

        return $order->customer_id ? 'Customer #'.$order->customer_id : '—';
    }
}
