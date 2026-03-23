<?php

declare(strict_types=1);

namespace Modules\Billing\Services;

use App\Contracts\ScaffoldServiceInterface;
use App\Scaffold\ScaffoldDefinition;
use App\Traits\Scaffoldable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Modules\Billing\Definitions\InvoiceDefinition;
use Modules\Billing\Http\Resources\InvoiceResource;
use Modules\Billing\Models\Invoice;

class InvoiceService implements ScaffoldServiceInterface
{
    use Scaffoldable {
        applyFilters as traitApplyFilters;
    }

    public function __construct(
        private readonly BillingService $billingService
    ) {}

    public function getScaffoldDefinition(): ScaffoldDefinition
    {
        return new InvoiceDefinition;
    }

    public function getStatistics(): array
    {
        return [
            'total' => Invoice::query()->whereNull('deleted_at')->count(),
            'draft' => Invoice::query()->where('status', Invoice::STATUS_DRAFT)->whereNull('deleted_at')->count(),
            'pending' => Invoice::query()->where('status', Invoice::STATUS_PENDING)->whereNull('deleted_at')->count(),
            'sent' => Invoice::query()->where('status', Invoice::STATUS_SENT)->whereNull('deleted_at')->count(),
            'partial' => Invoice::query()->where('status', Invoice::STATUS_PARTIAL)->whereNull('deleted_at')->count(),
            'paid' => Invoice::query()->where('status', Invoice::STATUS_PAID)->whereNull('deleted_at')->count(),
            'overdue' => Invoice::query()->where('status', Invoice::STATUS_OVERDUE)->whereNull('deleted_at')->count(),
            'cancelled' => Invoice::query()->where('status', Invoice::STATUS_CANCELLED)->whereNull('deleted_at')->count(),
            'refunded' => Invoice::query()->where('status', Invoice::STATUS_REFUNDED)->whereNull('deleted_at')->count(),
            'trash' => Invoice::onlyTrashed()->count(),
        ];
    }

    public function findModelForCrud(int|string $id, Request $request): Invoice
    {
        return Invoice::query()
            ->with([
                'customer',
                'items',
                'items.invoiceable',
                'payments',
                'credits',
                'refunds',
                'transactions',
                'createdBy:id,first_name,last_name',
                'updatedBy:id,first_name,last_name',
            ])
            ->withTrashed()
            ->where('id', $id)
            ->firstOrFail();
    }

    public function getStatusOptions(): array
    {
        return $this->billingService->getInvoiceStatusOptions();
    }

    public function getPaymentStatusOptions(): array
    {
        return [
            ['value' => Invoice::PAYMENT_STATUS_UNPAID, 'label' => 'Unpaid'],
            ['value' => Invoice::PAYMENT_STATUS_PARTIAL, 'label' => 'Partial'],
            ['value' => Invoice::PAYMENT_STATUS_PAID, 'label' => 'Paid'],
            ['value' => Invoice::PAYMENT_STATUS_REFUNDED, 'label' => 'Refunded'],
        ];
    }

    public function getCurrencyOptions(): array
    {
        return $this->billingService->getCurrencyOptions();
    }

    protected function getResourceClass(): ?string
    {
        return InvoiceResource::class;
    }

    protected function getEagerLoadRelationships(): array
    {
        return [
            'customer',
            'createdBy:id,first_name,last_name',
            'updatedBy:id,first_name,last_name',
        ];
    }

    protected function applyFilters(Builder $query, Request $request): void
    {
        $this->traitApplyFilters($query, $request);

        $currentStatus = $request->input('status') ?? $request->route('status') ?? 'all';
        if ($currentStatus !== 'trash' && $request->filled('payment_status')) {
            $query->where('payment_status', $request->input('payment_status'));
        }
    }

    protected function prepareCreateData(array $data): array
    {
        $currency = $data['currency'] ?? $this->billingService->getDefaultCurrency();

        $data['invoice_number'] ??= Invoice::generateInvoiceNumber();
        $data['issue_date'] ??= now()->toDateString();
        $data['due_date'] ??= now()->addDays((int) config('billing.invoice.default_due_days', 30))->toDateString();
        $data['status'] ??= Invoice::STATUS_DRAFT;
        $data['payment_status'] ??= Invoice::PAYMENT_STATUS_UNPAID;
        $data['currency'] = $currency;
        $data['exchange_rate'] ??= $this->billingService->getExchangeRate($currency);
        $data['amount_paid'] ??= 0;
        $data['amount_due'] ??= 0;
        $data['subtotal'] ??= 0;
        $data['tax_amount'] ??= 0;
        $data['discount_amount'] ??= 0;
        $data['total'] ??= 0;

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
