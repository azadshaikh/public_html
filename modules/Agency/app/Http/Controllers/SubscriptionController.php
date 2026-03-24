<?php

declare(strict_types=1);

namespace Modules\Agency\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Customers\Models\Customer;
use Modules\Subscriptions\Models\Subscription;

class SubscriptionController extends Controller
{
    public function index(): Response|RedirectResponse
    {
        if (! module_enabled('subscriptions')) {
            return to_route('agency.websites.index')
                ->with('error', 'Subscription management is not available.');
        }

        $user = auth()->user();
        $customer = $this->resolveCustomer($user);

        if (! $customer instanceof Customer) {
            return Inertia::render('agency/subscriptions/index', [
                'subscriptions' => [],
                'pagination' => $this->paginationPayload(new LengthAwarePaginator([], 0, 15)),
                'statusCounts' => ['total' => 0, 'active' => 0, 'trialing' => 0, 'canceled' => 0],
            ]);
        }

        $subscriptions = Subscription::query()
            ->where('customer_id', $customer->id)
            ->with('plan')
            ->latest()
            ->paginate(15);

        $statusCounts = Subscription::query()
            ->where('customer_id', $customer->id)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return Inertia::render('agency/subscriptions/index', [
            'subscriptions' => $subscriptions->through(fn (Subscription $subscription): array => $this->serializeSubscriptionListItem($subscription))->values()->all(),
            'pagination' => $this->paginationPayload($subscriptions),
            'statusCounts' => [
                'total' => array_sum($statusCounts),
                'active' => $statusCounts['active'] ?? 0,
                'trialing' => $statusCounts['trialing'] ?? 0,
                'canceled' => $statusCounts['canceled'] ?? 0,
            ],
        ]);
    }

    public function show(int $id): Response|RedirectResponse
    {
        if (! module_enabled('subscriptions')) {
            return to_route('agency.websites.index')
                ->with('error', 'Subscription management is not available.');
        }

        $user = auth()->user();
        $customer = $this->resolveCustomer($user);

        if (! $customer instanceof Customer) {
            return to_route('agency.websites.index')
                ->with('error', 'Customer profile not found.');
        }

        $subscription = Subscription::query()
            ->where('customer_id', $customer->id)
            ->where('id', $id)
            ->with(['plan', 'plan.features'])
            ->firstOrFail();

        return Inertia::render('agency/subscriptions/show', [
            'subscription' => $this->serializeSubscriptionDetail($subscription),
        ]);
    }

    private function resolveCustomer($user): ?Customer
    {
        return Customer::query()->where('user_id', $user->id)->first();
    }

    private function paginationPayload(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }

    private function serializeSubscriptionListItem(Subscription $subscription): array
    {
        return [
            'id' => $subscription->id,
            'plan_name' => $subscription->plan?->name ?? 'Unknown Plan',
            'formatted_price' => $subscription->plan?->formatted_price,
            'billing_cycle_label' => $subscription->plan?->billing_cycle_label ?? 'N/A',
            'status' => $subscription->status,
            'next_billing_date' => $subscription->next_billing_date?->toDateString(),
        ];
    }

    private function serializeSubscriptionDetail(Subscription $subscription): array
    {
        return [
            'id' => $subscription->id,
            'plan_name' => $subscription->plan?->name ?? 'Subscription',
            'formatted_price' => $subscription->plan?->formatted_price,
            'billing_cycle_label' => $subscription->plan?->billing_cycle_label ?? 'N/A',
            'status' => $subscription->status,
            'started_at' => $subscription->started_at?->toDateString(),
            'next_billing_date' => $subscription->next_billing_date?->toDateString(),
            'trial_ends_at' => $subscription->trial_ends_at?->toDateString(),
            'canceled_at' => $subscription->canceled_at?->toDateString(),
            'features' => $subscription->plan?->features?->map(fn ($feature): array => [
                'id' => $feature->id,
                'name' => $feature->name,
                'value' => $feature->value,
            ])->values()->all() ?? [],
        ];
    }
}
