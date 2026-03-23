<?php

namespace Modules\Subscriptions\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Subscriptions\Models\Plan;
use Modules\Subscriptions\Models\PlanFeature;
use Modules\Subscriptions\Models\PlanPrice;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedPlans();
    }

    protected function seedPlans(): void
    {
        // Remove Enterprise plan if it exists from a previous seed
        $enterprisePlan = Plan::query()->where('code', 'enterprise')->first();
        if ($enterprisePlan) {
            $enterprisePlan->features()->delete();
            $enterprisePlan->prices()->delete();
            $enterprisePlan->delete();
        }

        $plans = [
            [
                'code' => 'basic',
                'name' => 'Basic',
                'description' => 'Perfect for freelancers and personal projects',
                'trial_days' => 7,
                'grace_days' => 3,
                'sort_order' => 1,
                'is_popular' => false,
                'is_active' => true,
                'prices' => [
                    ['billing_cycle' => Plan::CYCLE_MONTHLY, 'price' => 2.99, 'currency' => 'USD', 'sort_order' => 0],
                    ['billing_cycle' => Plan::CYCLE_YEARLY, 'price' => 29.99, 'currency' => 'USD', 'sort_order' => 1],
                ],
                'features' => [
                    ['code' => 'storage', 'name' => 'Storage (GB)', 'description' => '1 GB Storage', 'type' => PlanFeature::TYPE_VALUE, 'value' => '1'],
                    ['code' => 'bandwidth', 'name' => 'Bandwidth (GB)', 'description' => '10 GB Bandwidth', 'type' => PlanFeature::TYPE_VALUE, 'value' => '10'],
                    ['code' => 'cdn', 'name' => 'Basic CDN', 'description' => 'Basic CDN Features', 'type' => PlanFeature::TYPE_BOOLEAN, 'value' => '1'],
                    ['code' => 'support', 'name' => 'Support Level', 'description' => 'Ticket Based Support', 'type' => PlanFeature::TYPE_BOOLEAN, 'value' => '1'],
                ],
            ],
            [
                'code' => 'premium',
                'name' => 'Premium',
                'description' => 'Ideal for growing businesses and agencies',
                'trial_days' => 7,
                'grace_days' => 7,
                'sort_order' => 2,
                'is_popular' => true,
                'is_active' => true,
                'prices' => [
                    ['billing_cycle' => Plan::CYCLE_MONTHLY, 'price' => 4.99, 'currency' => 'USD', 'sort_order' => 0],
                    ['billing_cycle' => Plan::CYCLE_YEARLY, 'price' => 49.99, 'currency' => 'USD', 'sort_order' => 1],
                ],
                'features' => [
                    ['code' => 'storage', 'name' => 'Storage (GB)', 'description' => '5 GB Storage', 'type' => PlanFeature::TYPE_LIMIT, 'value' => '5'],
                    ['code' => 'bandwidth', 'name' => 'Bandwidth (GB)', 'description' => '50 GB Bandwidth', 'type' => PlanFeature::TYPE_LIMIT, 'value' => '50'],
                    ['code' => 'cdn', 'name' => 'Premium CDN', 'description' => 'Premium CDN Features', 'type' => PlanFeature::TYPE_BOOLEAN, 'value' => '1'],
                    ['code' => 'support', 'name' => 'Support Level', 'description' => 'Ticket + Email Support', 'type' => PlanFeature::TYPE_BOOLEAN, 'value' => '1'],
                ],
            ],
            [
                'code' => 'business',
                'name' => 'Business',
                'description' => 'For established businesses with high traffic',
                'trial_days' => 7,
                'grace_days' => 14,
                'sort_order' => 3,
                'is_popular' => false,
                'is_active' => true,
                'prices' => [
                    ['billing_cycle' => Plan::CYCLE_MONTHLY, 'price' => 9.99, 'currency' => 'USD', 'sort_order' => 0],
                    ['billing_cycle' => Plan::CYCLE_YEARLY, 'price' => 99.99, 'currency' => 'USD', 'sort_order' => 1],
                ],
                'features' => [
                    ['code' => 'storage', 'name' => 'Storage (GB)', 'description' => '20 GB Storage', 'type' => PlanFeature::TYPE_LIMIT, 'value' => '20'],
                    ['code' => 'bandwidth', 'name' => 'Bandwidth (GB)', 'description' => 'Unlimited Bandwidth', 'type' => PlanFeature::TYPE_UNLIMITED, 'value' => null],
                    ['code' => 'cdn', 'name' => 'Business CDN', 'description' => 'Business CDN', 'type' => PlanFeature::TYPE_BOOLEAN, 'value' => '1'],
                    ['code' => 'support', 'name' => 'Support Level', 'description' => 'Ticket + Email + Phone Support', 'type' => PlanFeature::TYPE_VALUE, 'value' => 'dedicated'],
                    ['code' => 'api_access', 'name' => 'API Access', 'description' => 'API Access', 'type' => PlanFeature::TYPE_BOOLEAN, 'value' => '1'],
                    ['code' => 'white_label', 'name' => 'White Label', 'description' => 'White Label', 'type' => PlanFeature::TYPE_BOOLEAN, 'value' => '1'],
                ],
            ],
        ];

        foreach ($plans as $planData) {
            $features = $planData['features'];
            $prices = $this->normalizePrices($planData['prices']);
            unset($planData['features'], $planData['prices']);

            $plan = Plan::query()->updateOrCreate(['code' => $planData['code']], $planData);

            // Sync features
            $featureCodes = array_column($features, 'code');
            $plan->features()->whereNotIn('code', $featureCodes)->delete();

            foreach ($features as $index => $featureData) {
                PlanFeature::query()->updateOrCreate(['plan_id' => $plan->id, 'code' => $featureData['code']], array_merge($featureData, ['sort_order' => $index]));
            }

            // Sync prices
            $priceCycles = array_column($prices, 'billing_cycle');
            $plan->prices()->whereNotIn('billing_cycle', $priceCycles)->delete();

            foreach ($prices as $priceData) {
                PlanPrice::query()->updateOrCreate(['plan_id' => $plan->id, 'billing_cycle' => $priceData['billing_cycle']], array_merge($priceData, ['plan_id' => $plan->id, 'is_active' => true]));
            }
        }
    }

    /**
     * @param  array<int, mixed>  $prices
     * @return array<int, array{billing_cycle: string, price: mixed, currency: string, sort_order: int}>
     */
    private function normalizePrices(array $prices): array
    {
        $normalized = [];

        foreach ($prices as $index => $priceData) {
            if (! is_array($priceData)) {
                continue;
            }

            $billingCycle = $this->normalizeBillingCycle($priceData['billing_cycle'] ?? '');
            if ($billingCycle === '') {
                continue;
            }

            $normalized[$billingCycle] = [
                'billing_cycle' => $billingCycle,
                'price' => $priceData['price'] ?? 0,
                'currency' => strtoupper(trim((string) ($priceData['currency'] ?? 'USD'))),
                'sort_order' => isset($priceData['sort_order']) ? (int) $priceData['sort_order'] : $index,
            ];
        }

        return array_values($normalized);
    }

    private function normalizeBillingCycle(mixed $billingCycle): string
    {
        $normalized = strtolower(trim((string) $billingCycle));

        if ($normalized === '') {
            return '';
        }

        return match (true) {
            str_contains($normalized, 'month') => Plan::CYCLE_MONTHLY,
            str_contains($normalized, 'quarter') => Plan::CYCLE_QUARTERLY,
            str_contains($normalized, 'year'),
            str_contains($normalized, 'annual') => Plan::CYCLE_YEARLY,
            str_contains($normalized, 'life') => Plan::CYCLE_LIFETIME,
            default => $normalized,
        };
    }
}
