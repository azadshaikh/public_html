<?php

declare(strict_types=1);

namespace Modules\Billing\Services;

use App\Contracts\ScaffoldServiceInterface;
use App\Scaffold\ScaffoldDefinition;
use App\Traits\Scaffoldable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Modules\Billing\Definitions\TransactionDefinition;
use Modules\Billing\Http\Resources\TransactionResource;
use Modules\Billing\Models\Transaction;

class TransactionService implements ScaffoldServiceInterface
{
    use Scaffoldable {
        applyFilters as traitApplyFilters;
    }

    public function getScaffoldDefinition(): ScaffoldDefinition
    {
        return new TransactionDefinition;
    }

    public function getStatistics(): array
    {
        return [
            'total' => Transaction::query()->count(),
            'completed' => Transaction::query()->where('status', Transaction::STATUS_COMPLETED)->count(),
            'pending' => Transaction::query()->where('status', Transaction::STATUS_PENDING)->count(),
            'failed' => Transaction::query()->where('status', Transaction::STATUS_FAILED)->count(),
            'cancelled' => Transaction::query()->where('status', Transaction::STATUS_CANCELLED)->count(),
        ];
    }

    protected function getResourceClass(): ?string
    {
        return TransactionResource::class;
    }

    protected function getEagerLoadRelationships(): array
    {
        return [
            'customer',
            'transactionable',
            'createdBy:id,first_name,last_name',
            'updatedBy:id,first_name,last_name',
        ];
    }

    protected function applyFilters(Builder $query, Request $request): void
    {
        $this->traitApplyFilters($query, $request);

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->input('payment_method'));
        }
    }
}
