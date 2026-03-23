<?php

declare(strict_types=1);

namespace Modules\Subscriptions\Http\Resources;

use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldResource;
use Exception;
use Modules\Subscriptions\Definitions\SubscriptionDefinition;
use Modules\Subscriptions\Models\Subscription;

/** @mixin Subscription */
class SubscriptionResource extends ScaffoldResource
{
    public function customFields(): array
    {
        $plan = $this->plan;
        $billingCycle = $this->planPrice?->billing_cycle_label;

        if (! $billingCycle && $this->billing_cycle) {
            $billingCycle = ucfirst((string) $this->billing_cycle);
        }

        return [
            'unique_id' => $this->unique_id,
            'show_url' => route('subscriptions.subscriptions.show', $this->id),
            'plan_id' => $this->plan_id,
            'plan_name' => $plan ? $plan->name : 'Unknown',
            'plan_code' => $plan?->code,
            'billing_cycle' => $billingCycle ?: 'Unknown',
            'price' => $this->price,
            'formatted_price' => $this->getFormattedPrice(),
            'currency' => $this->currency,
            'status' => $this->status,
            'status_label' => $this->status_label,
            'status_badge' => $this->status_badge,
            'subscriber_name' => $this->getSubscriberName(),
            'subscriber_url' => $this->getSubscriberUrl(),
            'trial_ends_at' => $this->trial_ends_at?->toIso8601String(),
            'trial_ends_at_formatted' => $this->trial_ends_at?->format('M d, Y'),
            'current_period_start' => $this->current_period_start?->toIso8601String(),
            'current_period_start_formatted' => $this->current_period_start?->format('M d, Y'),
            'current_period_end' => $this->current_period_end?->toIso8601String(),
            'current_period_end_formatted' => $this->current_period_end?->format('M d, Y'),
            'canceled_at' => $this->canceled_at?->toIso8601String(),
            'canceled_at_formatted' => $this->canceled_at?->format('M d, Y'),
            'cancels_at' => $this->cancels_at?->toIso8601String(),
            'cancels_at_formatted' => $this->cancels_at?->format('M d, Y'),
            'on_trial' => $this->on_trial,
            'on_grace_period' => $this->on_grace_period,
        ];
    }

    protected function definition(): ScaffoldDefinition
    {
        return new SubscriptionDefinition;
    }

    protected function getRoutePrefix(): string
    {
        return 'subscriptions.subscriptions';
    }

    protected function getFormattedPrice(): string
    {
        $symbol = match ($this->currency) {
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'INR' => '₹',
            'JPY' => '¥',
            default => $this->currency.' ',
        };

        return $symbol.number_format((float) $this->price, 2);
    }

    protected function getSubscriberName(): string
    {
        $customer = $this->customer;

        if (! $customer) {
            return 'Unknown';
        }

        $companyName = $customer->getAttribute('company_name');
        $contactName = $customer->getAttribute('contact_name');
        $email = $customer->getAttribute('email');

        if ($companyName) {
            return $companyName;
        }

        if ($contactName) {
            return $contactName;
        }

        if ($email) {
            return $email;
        }

        return 'Customer #'.$customer->id;
    }

    protected function getSubscriberUrl(): ?string
    {
        if (! $this->customer_id) {
            return null;
        }

        try {
            return route('app.customers.show', $this->customer_id);
        } catch (Exception) {
            return null;
        }
    }
}
