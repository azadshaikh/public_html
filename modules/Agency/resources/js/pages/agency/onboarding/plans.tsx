import { useForm } from '@inertiajs/react';
import { CheckIcon } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cn } from '@/lib/utils';
import AgencyOnboardingLayout from '../../../components/agency-onboarding-layout';

type PlanFeature = {
    id: number;
    name: string;
    description: string | null;
    formatted_value: string | null;
};

type PlanPrice = {
    id: number;
    billing_cycle: string;
    billing_cycle_label: string;
    formatted_price: string;
};

type PlanData = {
    id: number;
    name: string;
    description: string | null;
    is_popular: boolean;
    trial_days: number;
    features: PlanFeature[];
    prices: PlanPrice[];
};

type AgencyOnboardingPlansPageProps = {
    plans: PlanData[];
    selectedPlanId: number | null;
    selectedPlanPriceId: number | null;
};

export default function AgencyOnboardingPlans({
    plans,
    selectedPlanId,
    selectedPlanPriceId,
}: AgencyOnboardingPlansPageProps) {
    const form = useForm({
        plan_id: 0,
        plan_price_id: 0,
    });
    const cycleOrder = ['monthly', 'quarterly', 'yearly', 'lifetime'];
    const availableCycles = cycleOrder.filter((cycle) =>
        plans.some((plan) => plan.prices.some((price) => price.billing_cycle === cycle)),
    );
    const initiallySelectedCycle =
        plans
            .flatMap((plan) => plan.prices)
            .find((price) => price.id === selectedPlanPriceId)?.billing_cycle
            ?? availableCycles[0]
            ?? 'monthly';
    const [billingCycle, setBillingCycle] = useState(initiallySelectedCycle);

    const handleChoose = (planId: number, priceId: number) => {
        form.setData({
            plan_id: planId,
            plan_price_id: priceId,
        });
        form.post(route('agency.onboarding.plans.store'));
    };

    return (
        <AgencyOnboardingLayout
            title="Choose Your Plan"
            description="Pick the plan and billing cadence you want to launch with."
            currentStep="plans"
            backHref={route('agency.onboarding.domain')}
        >
            <div className="space-y-6">
                <div className="flex flex-col gap-4 rounded-[2rem] border border-black/6 bg-white/88 p-5 shadow-[0_16px_60px_rgba(33,30,22,0.06)] dark:border-white/10 dark:bg-white/5 dark:shadow-none lg:flex-row lg:items-center lg:justify-between">
                    <div className="space-y-2">
                        <Badge variant="info" className="rounded-full px-3 py-1">
                            Pricing
                        </Badge>
                        <p className="max-w-2xl text-sm leading-6 text-muted-foreground">
                            Every plan includes managed website setup. Pick the cadence that fits
                            the client’s budget now and change it later if needed.
                        </p>
                    </div>

                    {availableCycles.length > 1 ? (
                        <div className="inline-flex flex-wrap gap-2 rounded-full border border-black/6 bg-background p-1 dark:border-white/10">
                            {availableCycles.map((cycle) => (
                                <button
                                    key={cycle}
                                    type="button"
                                    onClick={() => setBillingCycle(cycle)}
                                    className={cn(
                                        'rounded-full px-4 py-2 text-sm font-medium capitalize transition-colors',
                                        billingCycle === cycle
                                            ? 'bg-primary text-primary-foreground'
                                            : 'text-muted-foreground hover:text-foreground',
                                    )}
                                >
                                    {cycle}
                                </button>
                            ))}
                        </div>
                    ) : null}
                </div>

                {plans.length === 0 ? (
                    <Card className="rounded-[2rem] border-black/6 bg-white/92 text-center shadow-[0_20px_80px_rgba(33,30,22,0.08)] dark:border-white/10 dark:bg-white/5 dark:shadow-none">
                        <CardHeader>
                            <CardTitle>No plans available</CardTitle>
                            <CardDescription>
                                There are no active launch plans right now. Contact support and we
                                will help you finish provisioning manually.
                            </CardDescription>
                        </CardHeader>
                    </Card>
                ) : (
                    <div className="grid gap-6 xl:grid-cols-3">
                        {plans.map((plan) => {
                            const selectedPrice =
                                plan.prices.find(
                                    (price) => price.billing_cycle === billingCycle,
                                ) ?? plan.prices[0];
                            const isSelected =
                                plan.id === selectedPlanId
                                || selectedPrice?.id === selectedPlanPriceId;

                            return (
                                <Card
                                    key={plan.id}
                                    className={cn(
                                        'rounded-[2rem] border-black/6 bg-white/92 shadow-[0_20px_80px_rgba(33,30,22,0.08)] dark:border-white/10 dark:bg-white/5 dark:shadow-none',
                                        plan.is_popular && 'border-primary shadow-[0_24px_90px_rgba(42,95,255,0.16)]',
                                        isSelected && 'ring-2 ring-primary/20',
                                    )}
                                >
                                    <CardHeader className="space-y-4 border-b border-black/6 pb-6 dark:border-white/10">
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <CardTitle className="text-2xl tracking-[-0.03em]">
                                                {plan.name}
                                            </CardTitle>
                                            <div className="flex flex-wrap items-center gap-2">
                                                {plan.is_popular ? (
                                                    <Badge variant="default">Most Popular</Badge>
                                                ) : null}
                                                {isSelected ? (
                                                    <Badge variant="outline">
                                                        Selected
                                                    </Badge>
                                                ) : null}
                                            </div>
                                        </div>

                                        <CardDescription className="text-sm leading-6">
                                            {plan.description ?? 'Managed website hosting plan'}
                                        </CardDescription>

                                        <div className="space-y-2">
                                            <div className="flex items-end gap-2">
                                                <span className="text-4xl font-semibold tracking-[-0.04em]">
                                                    {selectedPrice?.formatted_price ?? '—'}
                                                </span>
                                                <span className="pb-1 text-sm text-muted-foreground capitalize">
                                                    /{selectedPrice?.billing_cycle_label.toLowerCase() ?? billingCycle}
                                                </span>
                                            </div>

                                            {plan.trial_days > 0 ? (
                                                <p className="text-sm font-medium text-[var(--success-foreground)] dark:text-[var(--success-dark-foreground)]">
                                                    {plan.trial_days}-day free trial included
                                                </p>
                                            ) : (
                                                <p className="text-sm text-muted-foreground">
                                                    Launches without a free-trial window.
                                                </p>
                                            )}
                                        </div>
                                    </CardHeader>

                                    <CardContent className="space-y-6 pt-6">
                                        <Button
                                            type="button"
                                            className="w-full"
                                            onClick={() =>
                                                selectedPrice
                                                    ? handleChoose(plan.id, selectedPrice.id)
                                                    : undefined
                                            }
                                            disabled={form.processing || !selectedPrice}
                                        >
                                            {plan.trial_days > 0
                                                ? 'Start Free Trial'
                                                : 'Choose Plan'}
                                        </Button>

                                        {plan.features.length > 0 ? (
                                            <div className="space-y-3">
                                                {plan.features.map((feature) => (
                                                    <div
                                                        key={feature.id}
                                                        className="flex gap-3 text-sm"
                                                    >
                                                        <CheckIcon className="mt-0.5 size-4 text-[var(--success-foreground)] dark:text-[var(--success-dark-foreground)]" />
                                                        <div className="space-y-1">
                                                            <p className="font-medium">
                                                                {feature.name}
                                                            </p>
                                                            <p className="leading-6 text-muted-foreground">
                                                                {feature.description
                                                                    ?? feature.formatted_value
                                                                    ?? ''}
                                                            </p>
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                        ) : (
                                            <p className="text-sm leading-6 text-muted-foreground">
                                                Managed setup, hosting, and provisioning are included
                                                with this launch package.
                                            </p>
                                        )}
                                    </CardContent>
                                </Card>
                            );
                        })}
                    </div>
                )}

                <div className="flex flex-wrap gap-3 text-sm text-muted-foreground">
                    <Badge variant="success" className="rounded-full px-3 py-1">
                        Free trial on every eligible plan
                    </Badge>
                    <Badge variant="outline" className="rounded-full px-3 py-1">
                        Upgrade or downgrade anytime
                    </Badge>
                    <Badge variant="outline" className="rounded-full px-3 py-1">
                        Managed provisioning included
                    </Badge>
                </div>
            </div>
        </AgencyOnboardingLayout>
    );
}
