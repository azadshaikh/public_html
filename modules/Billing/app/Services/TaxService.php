<?php

declare(strict_types=1);

namespace Modules\Billing\Services;

use App\Contracts\ScaffoldServiceInterface;
use App\Scaffold\ScaffoldDefinition;
use App\Traits\Scaffoldable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Modules\Billing\Definitions\TaxDefinition;
use Modules\Billing\Http\Resources\TaxResource;
use Modules\Billing\Models\Tax;

class TaxService implements ScaffoldServiceInterface
{
    use Scaffoldable {
        applyFilters as traitApplyFilters;
    }

    // ================================================================
    // REQUIRED METHODS
    // ================================================================

    public function getScaffoldDefinition(): ScaffoldDefinition
    {
        return new TaxDefinition;
    }

    // ================================================================
    // STATISTICS (for tab counts)
    // ================================================================

    public function getStatistics(): array
    {
        return [
            'total' => Tax::query()->whereNull('deleted_at')->count(),
            'active' => Tax::query()->where('is_active', true)->whereNull('deleted_at')->count(),
            'inactive' => Tax::query()->where('is_active', false)->whereNull('deleted_at')->count(),
            'trash' => Tax::onlyTrashed()->count(),
        ];
    }

    // ================================================================
    // FORM OPTIONS
    // ================================================================

    public function getTypeOptions(): array
    {
        return [
            ['value' => Tax::TYPE_PERCENTAGE, 'label' => 'Percentage'],
            ['value' => Tax::TYPE_FIXED, 'label' => 'Fixed Amount'],
        ];
    }

    public function getCountryOptions(): array
    {
        return [
            ['value' => '', 'label' => 'All Countries (Global)'],
            ['value' => 'US', 'label' => 'United States'],
            ['value' => 'CA', 'label' => 'Canada'],
            ['value' => 'GB', 'label' => 'United Kingdom'],
            ['value' => 'AU', 'label' => 'Australia'],
            ['value' => 'IN', 'label' => 'India'],
            ['value' => 'DE', 'label' => 'Germany'],
            ['value' => 'FR', 'label' => 'France'],
            ['value' => 'JP', 'label' => 'Japan'],
            ['value' => 'CN', 'label' => 'China'],
        ];
    }

    public function getStateOptions(string $country): array
    {
        $states = [
            'US' => [
                ['value' => '', 'label' => 'All States'],
                ['value' => 'CA', 'label' => 'California'],
                ['value' => 'NY', 'label' => 'New York'],
                ['value' => 'TX', 'label' => 'Texas'],
                ['value' => 'FL', 'label' => 'Florida'],
                ['value' => 'WA', 'label' => 'Washington'],
                ['value' => 'OR', 'label' => 'Oregon'],
                ['value' => 'NV', 'label' => 'Nevada'],
            ],
            'CA' => [
                ['value' => '', 'label' => 'All Provinces'],
                ['value' => 'ON', 'label' => 'Ontario'],
                ['value' => 'BC', 'label' => 'British Columbia'],
                ['value' => 'AB', 'label' => 'Alberta'],
                ['value' => 'QC', 'label' => 'Quebec'],
            ],
            'AU' => [
                ['value' => '', 'label' => 'All States'],
                ['value' => 'NSW', 'label' => 'New South Wales'],
                ['value' => 'VIC', 'label' => 'Victoria'],
                ['value' => 'QLD', 'label' => 'Queensland'],
            ],
            'IN' => [
                ['value' => '', 'label' => 'All States'],
                ['value' => 'MH', 'label' => 'Maharashtra'],
                ['value' => 'KA', 'label' => 'Karnataka'],
                ['value' => 'TN', 'label' => 'Tamil Nadu'],
                ['value' => 'DL', 'label' => 'Delhi'],
            ],
        ];

        return $states[$country] ?? [['value' => '', 'label' => 'All States/Regions']];
    }

    // ================================================================
    // TAX CALCULATION UTILITIES
    // ================================================================
    /**
     * Get effective taxes for a location.
     *
     * @return Collection<int, Tax>
     */
    public function getEffectiveTaxesForLocation(?string $country, ?string $state = null): Collection
    {
        return Tax::query()->effective()
            ->forLocation($country, $state)
            ->orderBy('priority')
            ->get();
    }

    /**
     * Calculate total tax for an amount and location.
     *
     * @return array{taxes: array<int, array{id: int, name: string, rate: float, amount: float}>, total: float}
     */
    public function calculateTaxForAmount(float $amount, ?string $country, ?string $state = null): array
    {
        $taxes = $this->getEffectiveTaxesForLocation($country, $state);

        $taxDetails = [];
        $totalTax = 0;
        $taxableAmount = $amount;

        foreach ($taxes as $tax) {
            $taxAmount = $tax->calculateTax($tax->is_compound ? $taxableAmount + $totalTax : $amount);
            $totalTax += $taxAmount;

            $taxDetails[] = [
                'id' => $tax->id,
                'name' => $tax->name,
                'code' => $tax->code,
                'rate' => (float) $tax->rate,
                'type' => $tax->type,
                'amount' => round($taxAmount, 2),
            ];
        }

        return [
            'taxes' => $taxDetails,
            'total' => round($totalTax, 2),
        ];
    }

    /**
     * Get tax summary for audit/reporting.
     *
     * @return array{
     *     total_active: int,
     *     by_country: array<string, int>,
     *     by_type: array<string, int>
     * }
     */
    public function getTaxSummary(): array
    {
        $byCountry = Tax::query()
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->selectRaw("COALESCE(country, 'Global') as country, COUNT(*) as count")
            ->groupBy('country')
            ->pluck('count', 'country')
            ->toArray();

        $byType = Tax::query()
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->selectRaw('type, COUNT(*) as count')
            ->groupBy('type')
            ->pluck('count', 'type')
            ->toArray();

        return [
            'total_active' => Tax::query()->where('is_active', true)->whereNull('deleted_at')->count(),
            'by_country' => $byCountry,
            'by_type' => $byType,
        ];
    }

    protected function getResourceClass(): ?string
    {
        return TaxResource::class;
    }

    // ================================================================
    // EAGER LOADING
    // ================================================================

    protected function getEagerLoadRelationships(): array
    {
        return [
            'createdBy:id,first_name,last_name',
            'updatedBy:id,first_name,last_name',
        ];
    }

    // ================================================================
    // CUSTOM STATUS TAB HANDLING (for boolean is_active)
    // ================================================================

    protected function applyStatusFilter(Builder $query, Request $request): void
    {
        $status = $request->input('status') ?? $request->route('status') ?? 'all';

        // Map status tab values to is_active boolean
        match ($status) {
            'active' => $query->where('is_active', true),
            'inactive' => $query->where('is_active', false),
            default => null, // 'all' shows everything
        };
    }

    // ================================================================
    // CUSTOM FILTER HANDLING
    // ================================================================

    protected function applyFilters(Builder $query, Request $request): void
    {
        $this->traitApplyFilters($query, $request);

        $currentStatus = $request->input('status') ?? $request->route('status') ?? 'all';
        if ($currentStatus !== 'trash' && $request->filled('filter_is_active')) {
            $query->where('is_active', $request->input('filter_is_active'));
        }
    }

    // ================================================================
    // DATA PREPARATION
    // ================================================================

    protected function prepareCreateData(array $data): array
    {
        $data['is_active'] ??= true;
        $data['is_compound'] ??= false;
        $data['priority'] ??= 0;

        return $data;
    }

    protected function prepareUpdateData(array $data): array
    {
        return $data;
    }
}
