<?php

declare(strict_types=1);

namespace Modules\Subscriptions\Http\Controllers;

use App\Scaffold\ScaffoldController;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Controllers\HasMiddleware;
use Modules\Subscriptions\Definitions\PlanDefinition;
use Modules\Subscriptions\Models\Plan;
use Modules\Subscriptions\Services\PlanService;

class PlanController extends ScaffoldController implements HasMiddleware
{
    public function __construct(
        private readonly PlanService $planService
    ) {}

    // ================================================================
    // MIDDLEWARE
    // ================================================================

    public static function middleware(): array
    {
        return (new PlanDefinition)->getMiddleware();
    }

    protected function service(): PlanService
    {
        return $this->planService;
    }

    // ================================================================
    // FORM VIEW DATA
    // ================================================================

    protected function getFormViewData(Model $model): array
    {
        $plan = $model instanceof Plan ? $model : new Plan;

        if ($plan->exists) {
            $plan->load(['prices', 'features']);
        }

        return [
            'initialValues' => [
                'code' => $plan->code ?? '',
                'name' => $plan->name ?? '',
                'description' => $plan->description ?? '',
                'trial_days' => $plan->trial_days ?? 0,
                'grace_days' => $plan->grace_days ?? 0,
                'sort_order' => $plan->sort_order ?? 0,
                'is_popular' => (bool) ($plan->is_popular ?? false),
                'is_active' => (bool) ($plan->is_active ?? true),
                'prices' => $plan->exists
                    ? $plan->prices->map(fn ($p): array => [
                        'id' => $p->id,
                        'billing_cycle' => $p->billing_cycle,
                        'price' => $p->price,
                        'currency' => $p->currency,
                        'is_active' => (bool) $p->is_active,
                        'sort_order' => $p->sort_order ?? 0,
                    ])->values()->all()
                    : [],
                'features' => $plan->exists
                    ? $plan->features->map(fn ($f): array => [
                        'id' => $f->id,
                        'code' => $f->code,
                        'name' => $f->name,
                        'description' => $f->description ?? '',
                        'type' => $f->type,
                        'value' => $f->value ?? '',
                        'sort_order' => $f->sort_order ?? 0,
                    ])->values()->all()
                    : [],
            ],
            'billingCycleOptions' => $this->planService->getBillingCycleOptions(),
            'currencyOptions' => $this->planService->getCurrencyOptions(),
            'featureTypeOptions' => $this->planService->getFeatureTypeOptions(),
        ];
    }

    // ================================================================
    // MODEL TRANSFORMS
    // ================================================================

    protected function transformModelForShow(Model $model): array
    {
        $model->load(['prices', 'features']);
        $model->loadCount('subscriptions');

        return $model->toArray();
    }

    protected function transformModelForEdit(Model $model): array
    {
        return [
            'id' => $model->id,
            'name' => $model instanceof Plan ? ($model->name ?: $model->code ?: "Plan #{$model->id}") : "#{$model->id}",
        ];
    }
}
