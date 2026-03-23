<?php

declare(strict_types=1);

namespace Modules\Subscriptions\Services;

use App\Contracts\ScaffoldServiceInterface;
use App\Scaffold\ScaffoldDefinition;
use App\Traits\Scaffoldable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\Subscriptions\Definitions\PlanDefinition;
use Modules\Subscriptions\Http\Resources\PlanResource;
use Modules\Subscriptions\Models\Plan;
use Modules\Subscriptions\Models\PlanFeature;
use Modules\Subscriptions\Models\PlanPrice;
use RuntimeException;

class PlanService implements ScaffoldServiceInterface
{
    use Scaffoldable {
        applyFilters as traitApplyFilters;
    }

    // ================================================================
    // REQUIRED METHODS
    // ================================================================

    public function getScaffoldDefinition(): ScaffoldDefinition
    {
        return new PlanDefinition;
    }

    // ================================================================
    // STATISTICS (for tab counts)
    // ================================================================

    public function getStatistics(): array
    {
        return [
            'total' => Plan::query()->whereNull('deleted_at')->count(),
            'active' => Plan::query()->where('is_active', true)->whereNull('deleted_at')->count(),
            'inactive' => Plan::query()->where('is_active', false)->whereNull('deleted_at')->count(),
            'trash' => Plan::onlyTrashed()->count(),
        ];
    }

    // ================================================================
    // FORM OPTIONS
    // ================================================================

    /**
     * Get price options for subscription creation (flat list per plan).
     *
     * @return array<int, array{value: int, label: string}>
     */
    public function getPlanPriceOptions(int $planId): array
    {
        return PlanPrice::query()->where('plan_id', $planId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (PlanPrice $p): array => [
                'value' => $p->id,
                'label' => $p->billing_cycle_label.' — '.$p->formatted_price,
            ])
            ->values()
            ->all();
    }

    /**
     * Get billing cycle options for forms.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function getBillingCycleOptions(): array
    {
        return [
            ['value' => Plan::CYCLE_MONTHLY, 'label' => 'Monthly'],
            ['value' => Plan::CYCLE_QUARTERLY, 'label' => 'Quarterly'],
            ['value' => Plan::CYCLE_YEARLY, 'label' => 'Yearly'],
            ['value' => Plan::CYCLE_LIFETIME, 'label' => 'Lifetime'],
        ];
    }

    /**
     * Get currency options for forms.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function getCurrencyOptions(): array
    {
        return [
            ['value' => 'USD', 'label' => 'USD - US Dollar'],
            ['value' => 'EUR', 'label' => 'EUR - Euro'],
            ['value' => 'GBP', 'label' => 'GBP - British Pound'],
            ['value' => 'INR', 'label' => 'INR - Indian Rupee'],
            ['value' => 'CAD', 'label' => 'CAD - Canadian Dollar'],
            ['value' => 'AUD', 'label' => 'AUD - Australian Dollar'],
            ['value' => 'JPY', 'label' => 'JPY - Japanese Yen'],
        ];
    }

    /**
     * Get feature type options for plan feature editing.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function getFeatureTypeOptions(): array
    {
        return [
            ['value' => PlanFeature::TYPE_BOOLEAN, 'label' => 'Boolean (On/Off)'],
            ['value' => PlanFeature::TYPE_LIMIT, 'label' => 'Usage Limit'],
            ['value' => PlanFeature::TYPE_VALUE, 'label' => 'Value'],
            ['value' => PlanFeature::TYPE_UNLIMITED, 'label' => 'Unlimited'],
        ];
    }

    protected function getResourceClass(): ?string
    {
        return PlanResource::class;
    }

    // ================================================================
    // EAGER LOADING
    // ================================================================

    protected function getEagerLoadRelationships(): array
    {
        return [
            'createdBy:id,first_name,last_name',
            'updatedBy:id,first_name,last_name',
            'prices',
        ];
    }

    protected function getEagerLoadCounts(): array
    {
        return ['subscriptions'];
    }

    // ================================================================
    // CUSTOM STATUS TAB HANDLING (for boolean is_active)
    // ================================================================

    protected function applyStatusFilter(Builder $query, Request $request): void
    {
        $status = $request->input('status') ?? $request->route('status') ?? 'all';

        match ($status) {
            'active' => $query->where('is_active', true),
            'inactive' => $query->where('is_active', false),
            default => null,
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
        $data['is_popular'] ??= false;
        $data['trial_days'] ??= 0;
        $data['grace_days'] ??= 0;
        $data['sort_order'] ??= 0;

        return $data;
    }

    protected function prepareUpdateData(array $data): array
    {
        return $data;
    }

    // ================================================================
    // POST-PERSIST HOOKS
    // ================================================================

    protected function afterCreate(Model $model, array $data): void
    {
        if ($model instanceof Plan) {
            $this->syncPlanFeatures($model, $data);
            $this->syncPlanPrices($model, $data);
        }
    }

    protected function afterUpdate(Model $model, array $data): void
    {
        if ($model instanceof Plan) {
            $this->syncPlanFeatures($model, $data);
            $this->syncPlanPrices($model, $data);
        }
    }

    // ================================================================
    // PRE-DELETE HOOKS
    // ================================================================

    protected function beforeForceDelete(Model $model): void
    {
        if (! $model instanceof Plan) {
            return;
        }

        $subscriptionCount = $model->subscriptions()->count();

        throw_if($subscriptionCount > 0, RuntimeException::class, sprintf('Cannot permanently delete this plan — it has %d subscription(s) linked to it. ', $subscriptionCount)
        .'Please reassign or delete those subscriptions first.');

        // Clean up child records that have no FK constraints blocking deletion
        $model->features()->delete();
        $model->prices()->delete();
    }

    // ================================================================
    // PRICE SYNCING
    // ================================================================

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncPlanPrices(Plan $plan, array $data): void
    {
        if (! array_key_exists('prices', $data) || ! is_array($data['prices'])) {
            return;
        }

        $incoming = [];
        foreach ($data['prices'] as $index => $priceData) {
            if (! is_array($priceData)) {
                continue;
            }

            $billingCycle = $this->normalizeBillingCycle((string) ($priceData['billing_cycle'] ?? ''));
            $price = $priceData['price'] ?? null;
            if ($billingCycle === '') {
                continue;
            }

            if ($price === null) {
                continue;
            }

            if ($price === '') {
                continue;
            }

            $incoming[] = [
                'id' => isset($priceData['id']) && $priceData['id'] !== '' ? (int) $priceData['id'] : null,
                'billing_cycle' => $billingCycle,
                'price' => (float) $price,
                'currency' => strtoupper(trim((string) ($priceData['currency'] ?? 'USD'))),
                'is_active' => isset($priceData['is_active']) && (bool) $priceData['is_active'],
                'sort_order' => isset($priceData['sort_order']) && $priceData['sort_order'] !== '' ? (int) $priceData['sort_order'] : $index,
            ];
        }

        /** @var Collection<int, PlanPrice> $existing */
        $existing = $plan->prices()->get()->keyBy('id');
        $keptIds = [];

        foreach ($incoming as $entry) {
            $payload = [
                'billing_cycle' => $entry['billing_cycle'],
                'price' => $entry['price'],
                'currency' => $entry['currency'],
                'is_active' => $entry['is_active'],
                'sort_order' => $entry['sort_order'],
            ];

            if ($entry['id'] && $existing->has($entry['id'])) {
                /** @var PlanPrice $existingPrice */
                $existingPrice = $existing->get($entry['id']);
                $existingPrice->update($payload);
                $keptIds[] = $entry['id'];
            } else {
                /** @var PlanPrice $created */
                $created = $plan->prices()->create($payload);
                $keptIds[] = $created->id;
            }
        }

        // Delete removed prices (only if no active subscriptions are using them)
        $plan->prices()
            ->whereNotIn('id', $keptIds)
            ->whereDoesntHave('subscriptions')
            ->delete();

        // Deactivate prices that have active subscriptions but were removed from form
        $plan->prices()
            ->whereNotIn('id', $keptIds)
            ->whereHas('subscriptions')
            ->update(['is_active' => false]);
    }

    // ================================================================
    // FEATURE SYNCING
    // ================================================================

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncPlanFeatures(Plan $plan, array $data): void
    {
        if (! array_key_exists('features', $data)) {
            return;
        }

        if (! is_array($data['features'])) {
            return;
        }

        $features = [];
        foreach ($data['features'] as $index => $featureData) {
            if (! is_array($featureData)) {
                continue;
            }

            $payload = $this->normalizeFeaturePayload($featureData, $index);
            if ($payload === null) {
                continue;
            }

            $features[] = [
                'id' => $featureData['id'] ?? null,
                'payload' => $payload,
            ];
        }

        if ($features === []) {
            $plan->features()->delete();

            return;
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, PlanFeature> $existing */
        $existing = $plan->features()->get();
        $existingById = $existing->keyBy('id');
        $existingByCode = $existing->keyBy('code');
        $keptIds = [];

        foreach ($features as $featureEntry) {
            $payload = $featureEntry['payload'];
            $featureId = $featureEntry['id'] ? (int) $featureEntry['id'] : null;

            if ($featureId && $existingById->has($featureId)) {
                /** @var PlanFeature $feature */
                $feature = $existingById->get($featureId);
                $feature->update($payload);
                $keptIds[] = $feature->id;

                continue;
            }

            $code = $payload['code'] ?? null;
            if ($code && $existingByCode->has($code)) {
                /** @var PlanFeature $feature */
                $feature = $existingByCode->get($code);
                $feature->update($payload);
                $keptIds[] = $feature->id;

                continue;
            }

            /** @var PlanFeature $feature */
            $feature = $plan->features()->create($payload);
            $keptIds[] = $feature->id;
        }

        $plan->features()
            ->whereNotIn('id', $keptIds)
            ->delete();
    }

    /**
     * @param  array<string, mixed>  $featureData
     * @return array<string, mixed>|null
     */
    private function normalizeFeaturePayload(array $featureData, int $index): ?array
    {
        $code = trim((string) ($featureData['code'] ?? ''));
        $name = trim((string) ($featureData['name'] ?? ''));
        $type = $this->normalizeFeatureType((string) ($featureData['type'] ?? PlanFeature::TYPE_BOOLEAN));

        if ($code === '' && $name === '') {
            return null;
        }

        $description = trim((string) ($featureData['description'] ?? ''));
        $sortOrder = $featureData['sort_order'] ?? null;
        $sortOrder = $sortOrder === null || $sortOrder === '' ? $index : (int) $sortOrder;

        return [
            'code' => $code,
            'name' => $name,
            'description' => $description !== '' ? $description : null,
            'type' => $type,
            'value' => $this->normalizeFeatureValue($type, $featureData['value'] ?? null),
            'sort_order' => $sortOrder,
        ];
    }

    private function normalizeFeatureType(string $type): string
    {
        return match ($type) {
            PlanFeature::TYPE_BOOLEAN,
            PlanFeature::TYPE_LIMIT,
            PlanFeature::TYPE_VALUE,
            PlanFeature::TYPE_UNLIMITED => $type,
            default => PlanFeature::TYPE_BOOLEAN,
        };
    }

    private function normalizeFeatureValue(string $type, mixed $value): ?string
    {
        return match ($type) {
            PlanFeature::TYPE_BOOLEAN => $this->normalizeBooleanValue($value),
            PlanFeature::TYPE_UNLIMITED => null,
            default => $value === null ? null : (string) $value,
        };
    }

    private function normalizeBillingCycle(string $billingCycle): string
    {
        $normalized = strtolower(trim($billingCycle));

        return match (true) {
            str_contains($normalized, 'month') => Plan::CYCLE_MONTHLY,
            str_contains($normalized, 'quarter') => Plan::CYCLE_QUARTERLY,
            str_contains($normalized, 'year'),
            str_contains($normalized, 'annual') => Plan::CYCLE_YEARLY,
            str_contains($normalized, 'life') => Plan::CYCLE_LIFETIME,
            default => $normalized,
        };
    }

    private function normalizeBooleanValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_numeric($value)) {
            return (int) $value !== 0 ? '1' : '0';
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true) ? '1' : '0';
        }

        return '0';
    }
}
