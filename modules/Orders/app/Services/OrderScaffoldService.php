<?php

declare(strict_types=1);

namespace Modules\Orders\Services;

use App\Contracts\ScaffoldServiceInterface;
use App\Scaffold\ScaffoldDefinition;
use App\Traits\Scaffoldable;
use Modules\Orders\Definitions\OrderDefinition;
use Modules\Orders\Http\Resources\OrderResource;
use Modules\Orders\Models\Order;

class OrderScaffoldService implements ScaffoldServiceInterface
{
    use Scaffoldable;

    // ================================================================
    // REQUIRED METHODS
    // ================================================================

    public function getScaffoldDefinition(): ScaffoldDefinition
    {
        return new OrderDefinition;
    }

    protected function getResourceClass(): ?string
    {
        return OrderResource::class;
    }

    // ================================================================
    // EAGER LOADING
    // ================================================================

    protected function getEagerLoadRelationships(): array
    {
        return ['customer:id,company_name,contact_first_name,contact_last_name,email'];
    }

    // ================================================================
    // STATISTICS (for tab counts)
    // ================================================================

    public function getStatistics(): array
    {
        return [
            'total' => Order::query()->count(),
            'pending' => Order::query()->where('status', Order::STATUS_PENDING)->count(),
            'processing' => Order::query()->where('status', Order::STATUS_PROCESSING)->count(),
            'active' => Order::query()->where('status', Order::STATUS_ACTIVE)->count(),
            'cancelled' => Order::query()->where('status', Order::STATUS_CANCELLED)->count(),
            'refunded' => Order::query()->where('status', Order::STATUS_REFUNDED)->count(),
            'trash' => Order::onlyTrashed()->count(),
        ];
    }

    // ================================================================
    // FILTER OPTIONS
    // ================================================================

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function getTypeOptions(): array
    {
        return collect(Order::typeOptions())
            ->map(fn ($label, $value): array => ['value' => $value, 'label' => $label])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function getStatusOptions(): array
    {
        return collect(Order::statusOptions())
            ->map(fn ($label, $value): array => ['value' => $value, 'label' => $label])
            ->values()
            ->all();
    }
}
