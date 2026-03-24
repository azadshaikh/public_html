import { router } from '@inertiajs/react';
import { CheckIcon, GiftIcon, Layers3Icon } from 'lucide-react';
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
import { Switch } from '@/components/ui/switch';
import { cn } from '@/lib/utils';
import AgencyOnboardingMinimalLayout from '../../../components/agency-onboarding-minimal-layout';

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

const cycleOrder = ['monthly', 'quarterly', 'yearly', 'lifetime'] as const;

function parseCurrencyAmount(value: string): number | null {
    const normalized = value.replace(/[^0-9.]/g, '');
    const amount = Number.parseFloat(normalized);

    return Number.isFinite(amount) ? amount : null;
}

export default function AgencyOnboardingPlans({
    plans,
    selectedPlanId,
    selectedPlanPriceId,
}: AgencyOnboardingPlansPageProps) {
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
    const [isSubmitting, setIsSubmitting] = useState(false);
    const hasMonthlyAndYearly = availableCycles.includes('monthly') && availableCycles.includes('yearly');
    const shouldUseBinaryToggle = hasMonthlyAndYearly && availableCycles.length === 2;
    const yearlySavingsLabel = (() => {
        if (!hasMonthlyAndYearly) {
            return null;
        }

        const discounts = plans
            .map((plan) => {
                const monthlyPrice = plan.prices.find((price) => price.billing_cycle === 'monthly');
                const yearlyPrice = plan.prices.find((price) => price.billing_cycle === 'yearly');

                if (!monthlyPrice || !yearlyPrice) {
                    return null;
                }

                const monthlyAmount = parseCurrencyAmount(monthlyPrice.formatted_price);
                const yearlyAmount = parseCurrencyAmount(yearlyPrice.formatted_price);

                if (monthlyAmount === null || yearlyAmount === null) {
                    return null;
                }

                const yearlyEquivalent = monthlyAmount * 12;

                if (yearlyEquivalent <= 0 || yearlyAmount >= yearlyEquivalent) {
                    return null;
                }

                return Math.round(((yearlyEquivalent - yearlyAmount) / yearlyEquivalent) * 100);
            })
            .filter((discount): discount is number => discount !== null);

        if (discounts.length === 0) {
            return null;
        }

        return `Save up to ${Math.max(...discounts)}%`;
    })();

    const handleChoose = (planId: number, priceId: number) => {
        setIsSubmitting(true);

        router.post(
            route('agency.onboarding.plans.store'),
            {
                plan_id: planId,
                plan_price_id: priceId,
            },
            {
                preserveScroll: true,
                onFinish: () => setIsSubmitting(false),
            },
        );
    };

    return (
        <AgencyOnboardingMinimalLayout
            title="Pick the perfect plan"
            description="Every plan includes a free trial. Upgrade, downgrade, or cancel anytime — no questions asked."
            backHref={route('agency.onboarding.domain')}
            contentWidthClassName="max-w-6xl"
        >
            <div className="space-y-8">
                {availableCycles.length > 1 ? (
                    <div className="flex justify-center">
                        {shouldUseBinaryToggle ? (
                            <div className="flex items-center gap-3 text-sm font-medium text-foreground">
                                <button
                                    type="button"
                                    onClick={() => setBillingCycle('monthly')}
                                    className={cn(
                                        'transition-colors',
                                        billingCycle === 'monthly'
                                            ? 'text-foreground'
                                            : 'text-muted-foreground',
                                    )}
                                >
                                    Monthly
                                </button>

                                <Switch
                                    checked={billingCycle === 'yearly'}
                                    onCheckedChange={(checked) =>
                                        setBillingCycle(checked ? 'yearly' : 'monthly')
                                    }
                                />

                                <button
                                    type="button"
                                    onClick={() => setBillingCycle('yearly')}
                                    className={cn(
                                        'transition-colors',
                                        billingCycle === 'yearly'
                                            ? 'text-foreground'
                                            : 'text-muted-foreground',
                                    )}
                                >
                                    Yearly
                                </button>

                                {yearlySavingsLabel ? (
                                    <span className="rounded-full bg-[var(--success-bg)] px-2.5 py-1 text-xs font-medium text-[var(--success-foreground)] dark:bg-[var(--success-dark-bg)] dark:text-[var(--success-dark-foreground)]">
                                        {yearlySavingsLabel}
                                    </span>
                                ) : null}
                            </div>
                        ) : (
                            <div className="inline-flex flex-wrap gap-2 rounded-full border border-border bg-muted/40 p-1">
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
                        )}
                    </div>
                ) : null}

                {plans.length === 0 ? (
                    <Card className="rounded-[2rem] border-border bg-card text-center shadow-none">
                        <CardHeader>
                            <CardTitle className="text-xl font-medium">No plans available</CardTitle>
                            <CardDescription className="text-sm leading-6">
                                There are no active launch plans right now. Contact support and we
                                will help you finish provisioning manually.
                            </CardDescription>
                        </CardHeader>
                    </Card>
                ) : (
                    <div className="grid gap-5 lg:grid-cols-3">
                        {plans.map((plan) => {
                            const selectedPrice =
                                plan.prices.find(
                                    (price) => price.billing_cycle === billingCycle,
                                ) ?? plan.prices[0];
                            const isSelected =
                                plan.id === selectedPlanId
                                || selectedPrice?.id === selectedPlanPriceId;
                            const featurePreview = plan.features.slice(0, 6);

                            return (
                                <Card
                                    key={plan.id}
                                    className={cn(
                                        'rounded-[1.75rem] border-border bg-background py-0 shadow-none',
                                        plan.is_popular && 'border-primary/30',
                                        isSelected && 'border-primary/40 shadow-[0_16px_40px_rgba(15,23,42,0.08)]',
                                    )}
                                >
                                    <div className="m-2 rounded-[1.25rem] border border-border/70 bg-muted/30 p-4">
                                        <CardHeader className="space-y-4 px-0 pb-0">
                                            <div className="flex flex-wrap items-start justify-between gap-3">
                                                <div className="flex size-10 items-center justify-center rounded-xl bg-background shadow-sm ring-1 ring-black/6 dark:ring-white/10">
                                                    <Layers3Icon className="size-4" />
                                                </div>

                                                <div className="flex flex-wrap items-center gap-2">
                                                    {plan.is_popular ? (
                                                        <Badge className="rounded-full bg-red-500 px-2.5 py-1 text-[11px] font-medium text-white hover:bg-red-500">
                                                            Most Popular
                                                        </Badge>
                                                    ) : null}
                                                    {isSelected ? (
                                                        <Badge variant="outline" className="rounded-full px-2.5 py-1 text-[11px]">
                                                            Selected
                                                        </Badge>
                                                    ) : null}
                                                </div>
                                            </div>

                                            <div className="space-y-1.5">
                                                <CardTitle className="text-2xl font-medium tracking-[-0.025em]">
                                                {plan.name}
                                                </CardTitle>
                                                <CardDescription className="text-sm leading-6">
                                                    {plan.description ?? 'Managed website hosting plan'}
                                                </CardDescription>
                                            </div>

                                            {plan.trial_days > 0 ? (
                                                <div className="flex items-center gap-2 text-sm font-medium text-[var(--success-foreground)] dark:text-[var(--success-dark-foreground)]">
                                                    <GiftIcon className="size-4" />
                                                    <span>{plan.trial_days}-day free trial</span>
                                                </div>
                                            ) : null}

                                            <div className="flex items-end gap-2 pt-1">
                                                <span className="text-5xl font-semibold tracking-[-0.05em] text-foreground">
                                                    {selectedPrice?.formatted_price ?? '—'}
                                                </span>
                                                <span className="pb-2 text-sm text-muted-foreground">
                                                    /{selectedPrice?.billing_cycle_label.toLowerCase() ?? billingCycle}
                                                </span>
                                            </div>
                                        </CardHeader>

                                        <CardContent className="px-0 pt-4">
                                            <Button
                                                type="button"
                                                variant={plan.is_popular || isSelected ? 'default' : 'outline'}
                                                size="xl"
                                                className="w-full rounded-lg"
                                                onClick={() =>
                                                    selectedPrice
                                                        ? handleChoose(plan.id, selectedPrice.id)
                                                        : undefined
                                                }
                                                disabled={isSubmitting || !selectedPrice}
                                            >
                                                {plan.trial_days > 0
                                                    ? 'Start Free Trial'
                                                    : 'Get Started'}
                                            </Button>
                                        </CardContent>
                                    </div>

                                    <CardContent className="space-y-3 px-4 pb-5 pt-1">
                                        {featurePreview.length > 0 ? (
                                            <div className="space-y-3">
                                                {featurePreview.map((feature) => (
                                                    <div
                                                        key={feature.id}
                                                        className="flex items-start gap-3 text-sm"
                                                    >
                                                        <CheckIcon className="mt-0.5 size-4 text-[var(--success-foreground)] dark:text-[var(--success-dark-foreground)]" />
                                                        <p className="leading-6 text-foreground/90">
                                                            {feature.formatted_value
                                                                ? `${feature.formatted_value} ${feature.name}`
                                                                : feature.description ?? feature.name}
                                                        </p>
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

                <div className="flex flex-wrap items-center justify-center gap-x-5 gap-y-2 text-sm text-muted-foreground">
                    <div className="flex items-center gap-1.5">
                        <GiftIcon className="size-3.5 text-[var(--success-foreground)] dark:text-[var(--success-dark-foreground)]" />
                        <span>Free trial on every eligible plan</span>
                    </div>
                    <div className="flex items-center gap-1.5">
                        <CheckIcon className="size-3.5 text-[var(--success-foreground)] dark:text-[var(--success-dark-foreground)]" />
                        <span>No credit card required</span>
                    </div>
                    <div className="flex items-center gap-1.5">
                        <CheckIcon className="size-3.5 text-[var(--success-foreground)] dark:text-[var(--success-dark-foreground)]" />
                        <span>Upgrade or downgrade anytime</span>
                    </div>
                </div>
            </div>
        </AgencyOnboardingMinimalLayout>
    );
}
