import { useForm } from '@inertiajs/react';
import { GiftIcon, LoaderCircleIcon, LockIcon, TicketIcon } from 'lucide-react';
import { useMemo, useState } from 'react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { FieldError } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import AgencyOnboardingMinimalLayout from '../../../components/agency-onboarding-minimal-layout';

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

type AppliedCoupon = {
    code: string;
    discountFormatted: string;
    newTotalFormatted: string;
    message: string;
};

function parseCurrencyAmount(value: string | null | undefined): number {
    const normalized = (value ?? '').replace(/[^0-9.]/g, '');
    const amount = Number.parseFloat(normalized);

    return Number.isFinite(amount) ? amount : 0;
}

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
    const [showCouponField, setShowCouponField] = useState(false);
    const [appliedCoupon, setAppliedCoupon] = useState<AppliedCoupon | null>(null);
    const [couponMessage, setCouponMessage] = useState<string | null>(null);
    const [isApplyingCoupon, setIsApplyingCoupon] = useState(false);
    const paymentError = (form.errors as Record<string, string | undefined>)
        .payment;

    const isTrial = (selectedPlan?.trial_days ?? 0) > 0;
    const subtotalAmount = useMemo(() => parseCurrencyAmount(selectedPrice?.formatted_price), [selectedPrice?.formatted_price]);
    const subtotalFormatted = selectedPrice?.formatted_price ?? '$0.00';
    const totalDueTodayFormatted = isTrial
        ? appliedCoupon?.newTotalFormatted ?? '$0.00'
        : appliedCoupon?.newTotalFormatted ?? subtotalFormatted;
    const recurringNotice = isTrial
        ? `${selectedPrice?.formatted_price ?? '$0.00'}/${selectedPrice?.billing_cycle_label ?? 'month'} after ${selectedPlan?.trial_days ?? 0}-day trial`
        : null;

    const handleValidateCoupon = async () => {
        const couponCode = form.data.coupon_code.trim();

        if (couponCode === '') {
            form.setError('coupon_code', 'Enter a coupon code first.');

            return;
        }

        setIsApplyingCoupon(true);
        setCouponMessage(null);
        form.clearErrors('coupon_code');

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
            const response = await fetch(route('agency.onboarding.validate-coupon'), {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    coupon_code: couponCode,
                }),
            });

            const payload = (await response.json()) as {
                valid?: boolean;
                message?: string;
                discount_formatted?: string;
                new_total_formatted?: string;
            };

            if (!response.ok || !payload.valid) {
                form.setError('coupon_code', payload.message ?? 'Unable to apply coupon.');
                setAppliedCoupon(null);

                return;
            }

            setAppliedCoupon({
                code: couponCode,
                discountFormatted: payload.discount_formatted ?? '0.00',
                newTotalFormatted: payload.new_total_formatted ?? '0.00',
                message: payload.message ?? 'Coupon applied!',
            });
            setCouponMessage(payload.message ?? 'Coupon applied!');
        } catch {
            form.setError('coupon_code', 'Unable to apply coupon right now.');
            setAppliedCoupon(null);
        } finally {
            setIsApplyingCoupon(false);
        }
    };

    const handleRemoveCoupon = () => {
        setAppliedCoupon(null);
        setCouponMessage(null);
        form.setData('coupon_code', '');
        form.clearErrors('coupon_code');
    };

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!form.data.agree_terms) {
            form.setError('agree_terms', 'You must agree to the terms first.');

            return;
        }

        form.post(route('agency.onboarding.checkout.process'));
    };

    return (
        <AgencyOnboardingMinimalLayout
            title="You're almost there!"
            description="Review your order and you'll be up and running in no time."
            backHref={route('agency.onboarding.plans')}
            contentWidthClassName="max-w-2xl"
        >
            <div className="space-y-6">
                <form className="space-y-5" noValidate onSubmit={handleSubmit}>
                    <Card className="w-full rounded-[1.75rem] border-border bg-card py-0 shadow-none">
                        <CardHeader className="space-y-4 border-b border-border px-4 py-4">
                            <CardTitle className="text-sm font-semibold">Your Order</CardTitle>

                            <div className="space-y-3 text-sm">
                                <div className="flex items-start justify-between gap-4">
                                    <div className="space-y-1">
                                        <p className="font-semibold text-foreground">
                                            {selectedPlan?.name ?? 'Selected Plan'}
                                        </p>
                                        <p className="text-muted-foreground">
                                            {selectedPrice?.billing_cycle_label ?? 'Billing cycle'}
                                        </p>
                                    </div>
                                    <p className="font-semibold text-[var(--success-foreground)] dark:text-[var(--success-dark-foreground)]">
                                        {isTrial ? '$0.00' : subtotalFormatted}
                                    </p>
                                </div>

                                {isTrial ? (
                                    <div className="flex items-center gap-2 text-[13px] font-medium text-[var(--success-foreground)] dark:text-[var(--success-dark-foreground)]">
                                        <GiftIcon className="size-3.5" />
                                        <span>{selectedPlan?.trial_days ?? 0}-day free trial</span>
                                    </div>
                                ) : null}

                                <div className="flex items-start justify-between gap-4 pt-1">
                                    <div className="space-y-1">
                                        <p className="break-all font-semibold text-foreground">
                                            {websiteDetails.domain ?? 'No domain selected'}
                                        </p>
                                        <p className="text-muted-foreground">Domain</p>
                                    </div>
                                    <p className="text-[13px] font-medium text-[var(--success-foreground)] dark:text-[var(--success-dark-foreground)]">
                                        Included
                                    </p>
                                </div>
                            </div>
                        </CardHeader>

                        <CardContent className="space-y-3 px-4 py-4 text-sm">
                            <div className="flex items-center justify-between gap-4 border-t border-border pt-3 font-semibold text-foreground">
                                <span>Total Due Today</span>
                                <span className="text-xl text-[var(--success-foreground)] dark:text-[var(--success-dark-foreground)]">
                                    {totalDueTodayFormatted}
                                </span>
                            </div>

                            {recurringNotice ? (
                                <p className="text-right text-[13px] text-muted-foreground">
                                    {recurringNotice}
                                </p>
                            ) : null}
                        </CardContent>
                    </Card>

                    <div className="space-y-3">
                        <button
                            type="button"
                            onClick={() => setShowCouponField((current) => !current)}
                            className="inline-flex items-center gap-1.5 rounded-lg border border-border px-2.5 py-1 text-xs font-medium text-foreground hover:bg-muted"
                        >
                            <TicketIcon className="size-3.5" />
                            {showCouponField ? 'Hide coupon field' : 'Have a coupon code?'}
                        </button>

                        {showCouponField ? (
                            <div className="space-y-3">
                                <div className="flex gap-2">
                                    <Input
                                        id="coupon_code"
                                        value={form.data.coupon_code}
                                        onChange={(event) => form.setData('coupon_code', event.target.value)}
                                        placeholder="Enter coupon code"
                                        className="h-11"
                                    />
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="xl"
                                        className="min-w-24 rounded-lg"
                                        disabled={isApplyingCoupon || form.data.coupon_code.trim() === ''}
                                        onClick={() => {
                                            void handleValidateCoupon();
                                        }}
                                    >
                                        {isApplyingCoupon ? <LoaderCircleIcon className="size-4 animate-spin" /> : 'Apply'}
                                    </Button>
                                </div>

                                <FieldError>{form.errors.coupon_code}</FieldError>

                                {appliedCoupon ? (
                                    <div className="space-y-2 text-sm">
                                        <div className="flex items-center justify-between gap-4 text-[var(--success-foreground)] dark:text-[var(--success-dark-foreground)]">
                                            <div className="flex items-center gap-1.5">
                                                <GiftIcon className="size-3.5" />
                                                <span>Discount ({appliedCoupon.code})</span>
                                            </div>
                                            <button
                                                type="button"
                                                onClick={handleRemoveCoupon}
                                                className="text-xs font-medium text-destructive underline-offset-4 hover:underline"
                                            >
                                                Remove
                                            </button>
                                        </div>

                                        <div className="flex items-center justify-between gap-4 text-[13px] text-muted-foreground">
                                            <span>{couponMessage ?? appliedCoupon.message}</span>
                                            <span>-{appliedCoupon.discountFormatted}</span>
                                        </div>

                                        <div className="flex items-center justify-between gap-4 border-t border-border pt-2 font-semibold text-foreground">
                                            <span>New Total</span>
                                            <span>{appliedCoupon.newTotalFormatted}</span>
                                        </div>
                                    </div>
                                ) : null}
                            </div>
                        ) : null}
                    </div>

                    {isTrial ? (
                        <div className="rounded-[1.25rem] border border-[var(--success-border)] bg-[var(--success-bg)] px-4 py-3 text-sm leading-7 text-[var(--success-foreground)] dark:border-[var(--success-dark-border)] dark:bg-[var(--success-dark-bg)] dark:text-[var(--success-dark-foreground)]">
                            <div className="flex items-start gap-2">
                                <GiftIcon className="mt-1 size-4 shrink-0" />
                                <p>
                                    No credit card required. Your {selectedPlan?.trial_days ?? 0}-day free trial starts immediately. When you're ready, payments are handled securely through Stripe — we never store your card details.
                                </p>
                            </div>
                        </div>
                    ) : stripeConfigured ? (
                        <div className="rounded-[1.25rem] border border-border bg-muted/30 px-4 py-3 text-sm leading-7 text-muted-foreground">
                            <div className="flex items-start gap-2">
                                <LockIcon className="mt-1 size-4 shrink-0 text-foreground" />
                                <p>
                                    Payments are handled securely through Stripe. Card details stay on Stripe's checkout page and never pass through this app.
                                </p>
                            </div>
                        </div>
                    ) : (
                        <div className="rounded-[1.25rem] border border-amber-200 bg-amber-100 px-4 py-3 text-sm leading-7 text-amber-700 dark:border-amber-500/20 dark:bg-amber-500/15 dark:text-amber-300">
                            Stripe is not configured for paid plans yet. Choose a trial plan or contact support before continuing.
                        </div>
                    )}

                    {paymentError ? (
                        <div className="rounded-[1.25rem] border border-destructive/20 bg-destructive/5 px-4 py-3 text-sm text-destructive">
                            {paymentError}
                        </div>
                    ) : null}

                    <div className="space-y-2">
                        <label className="flex items-start gap-3 text-sm text-muted-foreground">
                            <Checkbox
                                checked={form.data.agree_terms}
                                onCheckedChange={(checked) => form.setData('agree_terms', Boolean(checked))}
                            />
                            <span>
                                I agree to the{' '}
                                <a
                                    href={termsUrl}
                                    className="text-foreground underline underline-offset-4"
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    Terms of Service
                                </a>{' '}
                                and{' '}
                                <a
                                    href={privacyUrl}
                                    className="text-foreground underline underline-offset-4"
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    Privacy Policy
                                </a>.
                            </span>
                        </label>
                        <FieldError>{form.errors.agree_terms}</FieldError>
                    </div>

                    <Button
                        type="submit"
                        size="xl"
                        className="w-full rounded-lg bg-[var(--success-foreground)] text-white hover:bg-[color-mix(in_oklab,var(--success-foreground)_90%,black)] dark:bg-[var(--success-dark-foreground)] dark:text-black dark:hover:bg-[color-mix(in_oklab,var(--success-dark-foreground)_88%,white)]"
                        disabled={form.processing || (!isTrial && !stripeConfigured)}
                    >
                        {form.processing ? (
                            <LoaderCircleIcon className="size-4 animate-spin" />
                        ) : (
                            <LockIcon className="size-4" />
                        )}
                        {isTrial ? 'Start Free Trial' : 'Continue to Payment'}
                    </Button>
                </form>
            </div>
        </AgencyOnboardingMinimalLayout>
    );
}
