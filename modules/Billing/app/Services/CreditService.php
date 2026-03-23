<?php

declare(strict_types=1);

namespace Modules\Billing\Services;

use App\Contracts\ScaffoldServiceInterface;
use App\Scaffold\ScaffoldDefinition;
use App\Traits\Scaffoldable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Modules\Billing\Definitions\CreditDefinition;
use Modules\Billing\Http\Resources\CreditResource;
use Modules\Billing\Models\Credit;

class CreditService implements ScaffoldServiceInterface
{
    use Scaffoldable {
        applyFilters as traitApplyFilters;
    }

    public function __construct(
        private readonly BillingService $billingService
    ) {}

    public function getScaffoldDefinition(): ScaffoldDefinition
    {
        return new CreditDefinition;
    }

    public function getStatistics(): array
    {
        return [
            'total' => Credit::query()->whereNull('deleted_at')->count(),
            'active' => Credit::query()->where('status', Credit::STATUS_ACTIVE)->whereNull('deleted_at')->count(),
            'exhausted' => Credit::query()->where('status', Credit::STATUS_EXHAUSTED)->whereNull('deleted_at')->count(),
            'expired' => Credit::query()->where('status', Credit::STATUS_EXPIRED)->whereNull('deleted_at')->count(),
            'cancelled' => Credit::query()->where('status', Credit::STATUS_CANCELLED)->whereNull('deleted_at')->count(),
            'trash' => Credit::onlyTrashed()->count(),
        ];
    }

    public function getStatusOptions(): array
    {
        return [
            ['value' => Credit::STATUS_ACTIVE, 'label' => 'Active'],
            ['value' => Credit::STATUS_EXHAUSTED, 'label' => 'Exhausted'],
            ['value' => Credit::STATUS_EXPIRED, 'label' => 'Expired'],
            ['value' => Credit::STATUS_CANCELLED, 'label' => 'Cancelled'],
        ];
    }

    public function getTypeOptions(): array
    {
        return [
            ['value' => Credit::TYPE_CREDIT_NOTE, 'label' => 'Credit Note'],
            ['value' => Credit::TYPE_REFUND_CREDIT, 'label' => 'Refund Credit'],
            ['value' => Credit::TYPE_PROMO_CREDIT, 'label' => 'Promotional Credit'],
            ['value' => Credit::TYPE_GOODWILL, 'label' => 'Goodwill Credit'],
        ];
    }

    public function getCurrencyOptions(): array
    {
        return $this->billingService->getCurrencyOptions();
    }

    protected function getResourceClass(): ?string
    {
        return CreditResource::class;
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
        if ($currentStatus !== 'trash' && $request->filled('type')) {
            $query->where('type', $request->input('type'));
        }
    }

    protected function prepareCreateData(array $data): array
    {
        $currency = $data['currency'] ?? $this->billingService->getDefaultCurrency();

        $data['credit_number'] ??= Credit::generateCreditNumber();
        $data['amount_used'] ??= 0;
        $data['amount_remaining'] ??= $data['amount'];
        $data['status'] ??= Credit::STATUS_ACTIVE;
        $data['currency'] = $currency;

        return $data;
    }

    protected function prepareUpdateData(array $data): array
    {
        if (! empty($data['amount']) && empty($data['amount_remaining'])) {
            $data['amount_remaining'] = max(0, (float) $data['amount'] - (float) ($data['amount_used'] ?? 0));
        }

        return $data;
    }
}
