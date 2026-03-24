import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Field, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import AgencyOnboardingLayout from '../../../components/agency-onboarding-layout';

type SelectedPlan = {
    id: number;
    name: string;
    trial_days: number;
} | null;

type SelectedPrice = {
    id: number;
    billing_cycle_label: string;
    formatted_price: string;
    currency: string;
} | null;

type AgencyOnboardingCheckoutPageProps = {
    selectedPlan: SelectedPlan;
    selectedPrice: SelectedPrice;
    websiteDetails: {
        name?: string;
        domain?: string;
    };
    stripeConfigured: boolean;
    termsUrl: string;
    privacyUrl: string;
};

export default function AgencyOnboardingCheckout({
    selectedPlan,
    selectedPrice,
    websiteDetails,
    stripeConfigured,
    termsUrl,
    privacyUrl,
}: AgencyOnboardingCheckoutPageProps) {
    const form = useForm({
        coupon_code: '',
        agree_terms: false,
    });

    const isTrial = (selectedPlan?.trial_days ?? 0) > 0;

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!form.data.agree_terms) {
            form.setError('agree_terms', 'You must agree to the terms first.');

            return;
        }

        form.post(route('agency.onboarding.checkout.process'));
    };

    return (
        <AgencyOnboardingLayout
            title="Checkout"
            description="Review your order before provisioning starts."
            currentStep="checkout"
            backHref={route('agency.onboarding.plans')}
        >
            <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
                <Card className="w-full rounded-[2rem] border-black/6 bg-white/92 shadow-[0_20px_80px_rgba(33,30,22,0.08)] dark:border-white/10 dark:bg-white/5 dark:shadow-none">
                    <CardHeader className="space-y-3 border-b border-black/6 pb-6 dark:border-white/10">
                        <Badge variant="info" className="w-fit rounded-full px-3 py-1">
                            Final Review
                        </Badge>
                        <div className="space-y-2">
                            <CardTitle className="text-2xl tracking-[-0.03em]">
                                You&apos;re almost there
                            </CardTitle>
                            <CardDescription className="text-sm leading-6">
                                Confirm the selected plan, review the domain, and agree to the
                                terms before provisioning begins.
                            </CardDescription>
                        </div>
                    </CardHeader>

                    <CardContent className="pt-6">
                        <form className="space-y-6" onSubmit={handleSubmit}>
                            <FieldGroup>
                                <Field data-invalid={form.errors.coupon_code || undefined}>
                                    <FieldLabel htmlFor="coupon_code">Coupon Code</FieldLabel>
                                    <Input
                                        id="coupon_code"
                                        value={form.data.coupon_code}
                                        onChange={(event) =>
                                            form.setData('coupon_code', event.target.value)
                                        }
                                        placeholder="Optional"
                                    />
                                    <FieldError>{form.errors.coupon_code}</FieldError>
                                </Field>

                                <Field data-invalid={form.errors.agree_terms || undefined}>
                                    <label className="flex items-start gap-4 rounded-[1.5rem] border p-5">
                                        <Checkbox
                                            checked={form.data.agree_terms}
                                            onCheckedChange={(checked) =>
                                                form.setData('agree_terms', Boolean(checked))
                                            }
                                        />
                                        <span className="text-sm leading-6 text-muted-foreground">
                                            I agree to the
                                            {' '}
                                            <a
                                                href={termsUrl}
                                                className="font-medium text-primary"
                                                target="_blank"
                                                rel="noreferrer"
                                            >
                                                Terms of Service
                                            </a>
                                            {' '}
                                            and
                                            {' '}
                                            <a
                                                href={privacyUrl}
                                                className="font-medium text-primary"
                                                target="_blank"
                                                rel="noreferrer"
                                            >
                                                Privacy Policy
                                            </a>
                                            .
                                        </span>
                                    </label>
                                    <FieldError>{form.errors.agree_terms}</FieldError>
                                </Field>
                            </FieldGroup>

                            {isTrial ? (
                                <div className="rounded-[1.5rem] border border-[var(--success-border)] bg-[var(--success-bg)] p-4 text-sm leading-6 text-[var(--success-foreground)] dark:border-[var(--success-dark-border)] dark:bg-[var(--success-dark-bg)] dark:text-[var(--success-dark-foreground)]">
                                    No credit card is required for this free-trial checkout. We
                                    will provision the website immediately after you continue.
                                </div>
                            ) : stripeConfigured ? (
                                <div className="rounded-[1.5rem] border border-black/6 bg-background p-4 text-sm leading-6 text-muted-foreground dark:border-white/10">
                                    Payment is handled securely through Stripe. Card details stay on
                                    Stripe’s checkout page and never pass through this app.
                                </div>
                            ) : (
                                <div className="rounded-[1.5rem] border border-amber-200 bg-amber-100 p-4 text-sm leading-6 text-amber-700 dark:border-amber-500/20 dark:bg-amber-500/15 dark:text-amber-300">
                                    Stripe is not configured for paid plans yet. Choose a trial
                                    plan or contact support before continuing.
                                </div>
                            )}

                            <div className="flex flex-col gap-3 border-t border-black/6 pt-5 sm:flex-row sm:items-center sm:justify-between dark:border-white/10">
                                <p className="text-sm leading-6 text-muted-foreground">
                                    Provisioning starts right after this step, and you can track the
                                    live status on the next screen.
                                </p>

                                <Button
                                    type="submit"
                                    disabled={form.processing || (!isTrial && !stripeConfigured)}
                                >
                                    {isTrial ? 'Start Free Trial' : 'Continue to Payment'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <div className="space-y-4">
                    <Card className="rounded-[2rem] border-black/6 bg-white/88 dark:border-white/10 dark:bg-white/5">
                        <CardHeader className="space-y-2">
                            <div className="flex items-center justify-between gap-3">
                                <CardTitle className="text-lg">Order Summary</CardTitle>
                                {isTrial ? (
                                    <Badge variant="success">Free Trial</Badge>
                                ) : (
                                    <Badge variant="outline">
                                        {selectedPrice?.billing_cycle_label ?? 'Billing'}
                                    </Badge>
                                )}
                            </div>
                            <CardDescription>
                                The domain is included in the selected launch package.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="rounded-[1.5rem] border p-4">
                                <p className="text-xs font-semibold tracking-[0.22em] text-muted-foreground uppercase">
                                    Plan
                                </p>
                                <p className="mt-2 text-base font-semibold">
                                    {selectedPlan?.name ?? 'Selected Plan'}
                                </p>
                                <p className="mt-1 text-sm leading-6 text-muted-foreground">
                                    {selectedPrice?.billing_cycle_label ?? 'Billing cycle'} plan
                                </p>
                            </div>

                            <div className="rounded-[1.5rem] border p-4">
                                <p className="text-xs font-semibold tracking-[0.22em] text-muted-foreground uppercase">
                                    Domain
                                </p>
                                <p className="mt-2 break-all text-base font-semibold">
                                    {websiteDetails.domain ?? 'No domain selected'}
                                </p>
                                <p className="mt-1 text-sm leading-6 text-muted-foreground">
                                    Website name:
                                    {' '}
                                    {websiteDetails.name ?? websiteDetails.domain ?? 'Pending'}
                                </p>
                            </div>

                            <div className="rounded-[1.5rem] border border-dashed border-primary/30 bg-primary/5 p-4">
                                <p className="text-xs font-semibold tracking-[0.22em] text-muted-foreground uppercase">
                                    Total due today
                                </p>
                                <p className="mt-2 text-3xl font-semibold tracking-[-0.03em]">
                                    {isTrial ? '$0.00' : selectedPrice?.formatted_price ?? '$0.00'}
                                </p>
                                <p className="mt-1 text-sm leading-6 text-muted-foreground">
                                    {isTrial
                                        ? `${selectedPlan?.trial_days ?? 0}-day free trial before billing starts.`
                                        : selectedPrice?.billing_cycle_label ?? 'Billed immediately.'}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="rounded-[2rem] border-black/6 bg-white/88 dark:border-white/10 dark:bg-white/5">
                        <CardHeader className="space-y-2">
                            <CardTitle className="text-lg">What happens next</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3 text-sm leading-6 text-muted-foreground">
                            <p>The platform starts provisioning hosting, DNS, and application setup.</p>
                            <p>
                                You will land on a live progress screen where you can track every
                                provisioning stage.
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AgencyOnboardingLayout>
    );
}
