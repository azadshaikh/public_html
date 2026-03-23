<?php

declare(strict_types=1);

namespace Modules\Billing\Http\Controllers;

use App\Scaffold\ScaffoldController;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Controllers\HasMiddleware;
use Modules\Billing\Definitions\CouponDefinition;
use Modules\Billing\Models\Coupon;
use Modules\Billing\Services\CouponScaffoldService;
use Modules\Subscriptions\Models\Plan;

class CouponController extends ScaffoldController implements HasMiddleware
{
    public function __construct(
        private readonly CouponScaffoldService $couponScaffoldService,
    ) {}

    public static function middleware(): array
    {
        return (new CouponDefinition)->getMiddleware();
    }

    protected function service(): CouponScaffoldService
    {
        return $this->couponScaffoldService;
    }

    protected function getFormViewData(Model $model): array
    {
        $coupon = $model instanceof Coupon ? $model : new Coupon;

        return [
            'initialValues' => [
                'code' => $coupon->code ?? '',
                'name' => $coupon->name ?? '',
                'description' => $coupon->description ?? '',
                'type' => $coupon->type ?? '',
                'value' => $coupon->value ?? '',
                'currency' => $coupon->currency ?? 'USD',
                'discount_duration' => $coupon->discount_duration ?? '',
                'duration_in_months' => $coupon->duration_in_months ?? '',
                'max_uses' => $coupon->max_uses ?? '',
                'max_uses_per_customer' => $coupon->max_uses_per_customer ?? 1,
                'min_order_amount' => $coupon->min_order_amount ?? '',
                'applicable_plan_ids' => $coupon->applicable_plan_ids ?? [],
                'expires_at' => $coupon->expires_at?->format('Y-m-d') ?? '',
                'is_active' => (bool) ($coupon->is_active ?? true),
            ],
            'typeOptions' => $this->couponScaffoldService->getTypeOptions(),
            'durationOptions' => $this->couponScaffoldService->getDurationOptions(),
            'planOptions' => $this->getAvailablePlanOptions(),
        ];
    }

    protected function transformModelForEdit(Model $model): array
    {
        return [
            'id' => $model->id,
            'name' => $model instanceof Coupon ? ($model->name ?: $model->code ?: "Coupon #{$model->id}") : "#{$model->id}",
        ];
    }

    /**
     * Fetch active plans for the "Applicable Plans" multi-select.
     * Only loaded if Subscriptions module is enabled.
     */
    private function getAvailablePlanOptions(): array
    {
        if (! module_enabled('Subscriptions')) {
            return [];
        }

        /** @var class-string $planClass */
        $planClass = Plan::class;

        if (! class_exists($planClass)) {
            return [];
        }

        return $planClass::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn ($p): array => ['value' => $p->id, 'label' => $p->name.' ('.$p->code.')'])
            ->toArray();
    }
}
