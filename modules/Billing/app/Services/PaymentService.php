<?php

declare(strict_types=1);

namespace Modules\Billing\Services;

use App\Contracts\ScaffoldServiceInterface;
use App\Scaffold\ScaffoldDefinition;
use App\Traits\Scaffoldable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Modules\Billing\Definitions\PaymentDefinition;
use Modules\Billing\Http\Resources\PaymentResource;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Payment;

class PaymentService implements ScaffoldServiceInterface
{
    use Scaffoldable {
        applyFilters as traitApplyFilters;
    }

    public function __construct(
        private readonly BillingService $billingService
    ) {}

    public function getScaffoldDefinition(): ScaffoldDefinition
    {
        return new PaymentDefinition;
    }

    public function getStatistics(): array
    {
        return [
            'total' => Payment::query()->whereNull('deleted_at')->count(),
            'pending' => Payment::query()->where('status', Payment::STATUS_PENDING)->whereNull('deleted_at')->count(),
            'processing' => Payment::query()->where('status', Payment::STATUS_PROCESSING)->whereNull('deleted_at')->count(),
            'completed' => Payment::query()->where('status', Payment::STATUS_COMPLETED)->whereNull('deleted_at')->count(),
            'failed' => Payment::query()->where('status', Payment::STATUS_FAILED)->whereNull('deleted_at')->count(),
            'cancelled' => Payment::query()->where('status', Payment::STATUS_CANCELLED)->whereNull('deleted_at')->count(),
            'refunded' => Payment::query()->where('status', Payment::STATUS_REFUNDED)->whereNull('deleted_at')->count(),
            'trash' => Payment::onlyTrashed()->count(),
        ];
    }

    public function getStatusOptions(): array
    {
        return $this->billingService->getPaymentStatusOptions();
    }

    public function getPaymentMethodOptions(): array
    {
        return $this->billingService->getPaymentMethodOptions();
    }

    public function getCurrencyOptions(): array
    {
        return $this->billingService->getCurrencyOptions();
    }

    public function getGatewayOptions(): array
    {
        return [
            ['value' => 'stripe', 'label' => 'Stripe'],
            ['value' => 'manual', 'label' => 'Manual'],
        ];
    }

    protected function getResourceClass(): ?string
    {
        return PaymentResource::class;
    }

    protected function getEagerLoadRelationships(): array
    {
        return [
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
        if ($currentStatus !== 'trash') {
            if ($request->filled('payment_method')) {
                $query->where('payment_method', $request->input('payment_method'));
            }

            if ($request->filled('payment_gateway')) {
                $query->where('payment_gateway', $request->input('payment_gateway'));
            }
        }
    }

    protected function prepareCreateData(array $data): array
    {
        $currency = $data['currency'] ?? $this->billingService->getDefaultCurrency();

        $data['payment_number'] ??= Payment::generatePaymentNumber();
        $data['status'] ??= Payment::STATUS_PENDING;
        $data['currency'] = $currency;
        $data['exchange_rate'] ??= $this->billingService->getExchangeRate($currency);
        $data['payment_gateway'] ??= 'manual';
        $data['payment_method'] ??= Payment::METHOD_CARD;
        if (! empty($data['invoice_id'])) {
            $invoice = Invoice::query()->find($data['invoice_id']);
            if ($invoice) {
                $data['customer_id'] ??= $invoice->customer_id;
            }
        }

        return $data;
    }

    protected function prepareUpdateData(array $data): array
    {
        if (! empty($data['currency']) && empty($data['exchange_rate'])) {
            $data['exchange_rate'] = $this->billingService->getExchangeRate($data['currency']);
        }

        return $data;
    }
}
