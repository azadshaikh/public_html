<?php

declare(strict_types=1);

namespace Modules\Billing\Http\Resources;

use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldResource;
use Modules\Billing\Definitions\CouponDefinition;
use Modules\Billing\Models\Coupon;

/** @mixin Coupon */
class CouponResource extends ScaffoldResource
{
    protected function definition(): ScaffoldDefinition
    {
        return new CouponDefinition;
    }

    protected function customFields(): array
    {
        $typeVariants = [
            Coupon::TYPE_PERCENT => 'default',
            Coupon::TYPE_FIXED => 'info',
        ];

        $durationVariants = [
            Coupon::DURATION_ONCE => 'secondary',
            Coupon::DURATION_REPEATING => 'warning',
            Coupon::DURATION_FOREVER => 'success',
        ];

        $typeLabel = match ($this->type) {
            Coupon::TYPE_PERCENT => 'Percent',
            Coupon::TYPE_FIXED => 'Fixed',
            default => ucfirst((string) $this->type),
        };

        $durationLabel = match ($this->discount_duration) {
            Coupon::DURATION_ONCE => 'Once',
            Coupon::DURATION_REPEATING => 'Repeating',
            Coupon::DURATION_FOREVER => 'Forever',
            default => ucfirst((string) $this->discount_duration),
        };

        $valueDisplay = $this->type === Coupon::TYPE_PERCENT
            ? $this->value.'%'
            : number_format((float) $this->value, 2).($this->currency ? ' '.$this->currency : '');

        /** @var Coupon $coupon */
        $coupon = $this->resource;

        return [
            'show_url' => route($this->scaffold()->getRoutePrefix().'.show', $coupon->id),
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,

            'type' => $this->type,
            'type_label' => $typeLabel,
            'type_badge' => $typeVariants[$this->type] ?? 'secondary',

            'value' => $this->value,
            'value_display' => $valueDisplay,
            'currency' => $this->currency,

            'discount_duration' => $this->discount_duration,
            'discount_duration_label' => $durationLabel,
            'discount_duration_badge' => $durationVariants[$this->discount_duration] ?? 'secondary',
            'duration_in_months' => $this->duration_in_months,

            'max_uses' => $this->max_uses,
            'max_uses_display' => $this->max_uses ?? '—',
            'uses_count' => $this->uses_count,
            'max_uses_per_customer' => $this->max_uses_per_customer,
            'min_order_amount' => $this->min_order_amount,
            'applicable_plan_ids' => $this->applicable_plan_ids,

            'expires_at' => $this->expires_at?->toISOString(),
            'expires_at_display' => $this->expires_at
                ? app_date_time_format($this->expires_at, 'date')
                : 'Never',

            'is_active' => $this->is_active,
            'is_active_label' => $this->is_active ? 'Active' : 'Inactive',
            'is_active_badge' => $this->is_active ? 'success' : 'secondary',
        ];
    }
}
