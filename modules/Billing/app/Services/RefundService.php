<?php

declare(strict_types=1);

namespace Modules\Billing\Services;

use App\Contracts\ScaffoldServiceInterface;
use App\Scaffold\ScaffoldDefinition;
use App\Traits\Scaffoldable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Modules\Billing\Definitions\RefundDefinition;
use Modules\Billing\Http\Resources\RefundResource;
use Modules\Billing\Models\Payment;
use Modules\Billing\Models\Refund;

class RefundService implements ScaffoldServiceInterface
{
    use Scaffoldable {
        applyFilters as traitApplyFilters;
    }

    public function __construct(
        private readonly BillingService $billingService
    ) {}

    public function getScaffoldDefinition(): ScaffoldDefinition
    {
        return new RefundDefinition;
    }

    public function getStatistics(): array
    {
        return [
            'total' => Refund::query()->whereNull('deleted_at')->count(),
            'pending' => Refund::query()->where('status', Refund::STATUS_PENDING)->whereNull('deleted_at')->count(),
            'processing' => Refund::query()->where('status', Refund::STATUS_PROCESSING)->whereNull('deleted_at')->count(),
            'completed' => Refund::query()->where('status', Refund::STATUS_COMPLETED)->whereNull('deleted_at')->count(),
            'failed' => Refund::query()->where('status', Refund::STATUS_FAILED)->whereNull('deleted_at')->count(),
            'cancelled' => Refund::query()->where('status', Refund::STATUS_CANCELLED)->whereNull('deleted_at')->count(),
            'trash' => Refund::onlyTrashed()->count(),
        ];
    }

    public function getStatusOptions(): array
    {
        return [
            ['value' => Refund::STATUS_PENDING, 'label' => 'Pending'],
            ['value' => Refund::STATUS_PROCESSING, 'label' => 'Processing'],
            ['value' => Refund::STATUS_COMPLETED, 'label' => 'Completed'],
            ['value' => Refund::STATUS_FAILED, 'label' => 'Failed'],
            ['value' => Refund::STATUS_CANCELLED, 'label' => 'Cancelled'],
        ];
    }

    public function getTypeOptions(): array
    {
        return [
            ['value' => Refund::TYPE_FULL, 'label' => 'Full'],
            ['value' => Refund::TYPE_PARTIAL, 'label' => 'Partial'],
        ];
    }

    public function getCurrencyOptions(): array
    {
        return $this->billingService->getCurrencyOptions();
    }

    protected function getResourceClass(): ?string
    {
        return RefundResource::class;
    }

    protected function getEagerLoadRelationships(): array
    {
        return [
            'payment',
            'invoice',
            'customer',
            'createdBy:id,first_name,last_name',
            'updatedBy:id,first_name,last_name',
        ];
    }

    protected function applyFilters(Builder $query, Request $request): void
    {
        $this->traitApplyFilters($query, $request);

        $currentStatus = $request->input('status') ?? $request->route('status') ?? 'all';
        if ($currentStatus !== 'trash' && $request->filled('type')) {
            $query->where('type', $request->input('type'));
        }
    }

    protected function prepareCreateData(array $data): array
    {
        $data['refund_number'] ??= Refund::generateRefundNumber();
        $data['status'] ??= Refund::STATUS_PENDING;

        if (! empty($data['payment_id'])) {
            $payment = Payment::query()->with('invoice')->find($data['payment_id']);

            if ($payment) {
                $data['invoice_id'] ??= $payment->invoice_id;
                $data['customer_id'] ??= $payment->customer_id;
                $data['currency'] ??= $payment->currency;
            }
        }

        $data['currency'] ??= $this->billingService->getDefaultCurrency();

        return $data;
    }
}
