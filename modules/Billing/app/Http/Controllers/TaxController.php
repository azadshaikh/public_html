<?php

declare(strict_types=1);

namespace Modules\Billing\Http\Controllers;

use App\Scaffold\ScaffoldController;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Controllers\HasMiddleware;
use Modules\Billing\Definitions\TaxDefinition;
use Modules\Billing\Models\Tax;
use Modules\Billing\Services\TaxService;

class TaxController extends ScaffoldController implements HasMiddleware
{
    public function __construct(
        private readonly TaxService $taxService
    ) {}

    // ================================================================
    // MIDDLEWARE
    // ================================================================

    public static function middleware(): array
    {
        return (new TaxDefinition)->getMiddleware();
    }

    protected function service(): TaxService
    {
        return $this->taxService;
    }

    // ================================================================
    // FORM VIEW DATA
    // ================================================================

    protected function getFormViewData(Model $model): array
    {
        /** @var Tax $model */
        $tax = $model instanceof Tax ? $model : new Tax;

        return [
            'initialValues' => [
                'name' => $tax->name ?? '',
                'code' => $tax->code ?? '',
                'type' => $tax->type ?? Tax::TYPE_PERCENTAGE,
                'rate' => $tax->rate ?? '',
                'country' => $tax->country ?? '',
                'state' => $tax->state ?? '',
                'postal_code' => $tax->postal_code ?? '',
                'description' => $tax->description ?? '',
                'is_compound' => (bool) ($tax->is_compound ?? false),
                'priority' => $tax->priority ?? 0,
                'is_active' => (bool) ($tax->is_active ?? true),
                'effective_from' => $tax->effective_from?->format('Y-m-d') ?? '',
                'effective_to' => $tax->effective_to?->format('Y-m-d') ?? '',
            ],
            'typeOptions' => $this->taxService->getTypeOptions(),
            'countryOptions' => $this->taxService->getCountryOptions(),
            'stateOptions' => $tax->country
                ? $this->taxService->getStateOptions($tax->country)
                : [['value' => '', 'label' => 'Select country first']],
        ];
    }

    protected function transformModelForEdit(Model $model): array
    {
        return [
            'id' => $model->id,
            'name' => $model instanceof Tax ? ($model->name ?: "Tax #{$model->id}") : "#{$model->id}",
        ];
    }
}
