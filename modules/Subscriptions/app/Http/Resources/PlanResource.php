<?php

declare(strict_types=1);

namespace Modules\Subscriptions\Http\Resources;

use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldResource;
use Modules\Subscriptions\Definitions\PlanDefinition;
use Modules\Subscriptions\Models\Plan;

/** @mixin Plan */
class PlanResource extends ScaffoldResource
{
    public function customFields(): array
    {
        $prices = $this->whenLoaded('prices', fn () => $this->prices->map(fn ($p): array => [
            'id' => $p->id,
            'billing_cycle' => $p->billing_cycle,
            'billing_cycle_label' => $p->billing_cycle_label,
            'price' => $p->price,
            'formatted_price' => $p->formatted_price,
            'currency' => $p->currency,
            'is_active' => $p->is_active,
            'sort_order' => $p->sort_order,
        ])->values()->all(), []);

        // Build a compact prices summary for the DataGrid column
        $pricesSummary = collect($this->prices ?? [])
            ->filter(fn ($p) => $p->is_active)
            ->map(fn ($p): string => $p->billing_cycle_label.': '.$p->formatted_price)
            ->implode(' / ');

        return [
            'show_url' => route('subscriptions.plans.show', $this->id),
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'prices' => $prices,
            'prices_summary' => $pricesSummary ?: '—',
            'trial_days' => $this->trial_days,
            'grace_days' => $this->grace_days,
            'sort_order' => $this->sort_order,
            'is_popular' => $this->is_popular,
            'is_active' => $this->is_active,
            'is_active_label' => $this->is_active ? 'Active' : 'Inactive',
            'status_badge' => $this->status_badge,
            'subscriptions_count' => $this->subscriptions_count ?? 0,
            'features_count' => $this->features_count ?? $this->features->count(),
        ];
    }

    protected function definition(): ScaffoldDefinition
    {
        return new PlanDefinition;
    }

    protected function getRoutePrefix(): string
    {
        return 'subscriptions.plans';
    }
}
