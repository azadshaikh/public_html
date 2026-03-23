<?php

declare(strict_types=1);

namespace Modules\Subscriptions\Http\Controllers;

use App\Scaffold\ScaffoldController;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Date;
use Modules\Subscriptions\Definitions\SubscriptionDefinition;
use Modules\Subscriptions\Models\Subscription;
use Modules\Subscriptions\Services\SubscriptionScaffoldService;
use RuntimeException;

class SubscriptionController extends ScaffoldController implements HasMiddleware
{
    public function __construct(
        private readonly SubscriptionScaffoldService $subscriptionService
    ) {}

    // ================================================================
    // MIDDLEWARE
    // ================================================================

    public static function middleware(): array
    {
        return (new SubscriptionDefinition)->getMiddleware();
    }

    // ================================================================
    // CUSTOM ACTIONS
    // ================================================================

    /**
     * Cancel a subscription.
     */
    public function cancel(Request $request, Subscription $subscription): RedirectResponse
    {
        $immediately = $request->boolean('immediately', false);
        $subscription->cancel($immediately);

        return to_route('subscriptions.subscriptions.show', $subscription)
            ->with('success', $immediately
                ? 'Subscription cancelled immediately.'
                : 'Subscription will be cancelled at the end of the billing period.');
    }

    /**
     * Resume a cancelled subscription.
     */
    public function resume(Subscription $subscription): RedirectResponse
    {
        try {
            $subscription->resume();

            return to_route('subscriptions.subscriptions.show', $subscription)
                ->with('success', 'Subscription resumed successfully.');
        } catch (RuntimeException $runtimeException) {
            return to_route('subscriptions.subscriptions.show', $subscription)
                ->with('error', $runtimeException->getMessage());
        }
    }

    /**
     * Pause a subscription.
     */
    public function pause(Request $request, Subscription $subscription): RedirectResponse
    {
        $resumeAt = $request->has('resume_at')
            ? Date::parse($request->input('resume_at'))
            : null;

        $subscription->pause($resumeAt);

        return to_route('subscriptions.subscriptions.show', $subscription)
            ->with('success', 'Subscription paused successfully.');
    }

    protected function service(): SubscriptionScaffoldService
    {
        return $this->subscriptionService;
    }

    // ================================================================
    // FORM VIEW DATA
    // ================================================================

    protected function getFormViewData(Model $model): array
    {
        $sub = $model instanceof Subscription ? $model : new Subscription;

        return [
            'initialValues' => [
                'plan_id' => $sub->plan_id ?? '',
                'plan_price_id' => $sub->plan_price_id ?? '',
                'customer_id' => $sub->customer_id ?? '',
                'price' => $sub->price ?? '',
                'currency' => $sub->currency ?? 'USD',
                'status' => $sub->status ?? '',
                'trial_days' => $sub->trial_days ?? 0,
            ],
            'planOptions' => $this->subscriptionService->getPlanOptions(),
            'planPriceOptionsByPlan' => $this->subscriptionService->getPlanPriceOptionsByPlan(),
            'statusOptions' => $this->subscriptionService->getStatusOptions(),
            'customerOptions' => $this->subscriptionService->getCustomerOptions(),
        ];
    }

    // ================================================================
    // MODEL TRANSFORMS
    // ================================================================

    protected function transformModelForShow(Model $model): array
    {
        $model->load(['plan', 'planPrice', 'customer']);

        return $model->toArray();
    }

    protected function transformModelForEdit(Model $model): array
    {
        return [
            'id' => $model->id,
            'name' => $model instanceof Subscription
                ? ($model->unique_id ?: "Subscription #{$model->id}")
                : "#{$model->id}",
        ];
    }
}
