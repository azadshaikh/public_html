import type { FormEvent } from 'react';
import { useMemo } from 'react';
import { FormErrorSummary } from '@/components/forms/form-error-summary';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { NativeSelect, NativeSelectOption } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { useAppForm } from '@/hooks/use-app-form';
import { formValidators } from '@/lib/forms';
import type { SubscriptionFormValues, SubscriptionOption } from '../../types/subscriptions';

type SubscriptionFormProps = {
    mode: 'create' | 'edit';
    subscription?: { id: number; name: string };
    initialValues: SubscriptionFormValues;
    planOptions: SubscriptionOption[];
    planPriceOptionsByPlan: Record<string, SubscriptionOption[]>;
    statusOptions: SubscriptionOption[];
    customerOptions: SubscriptionOption[];
};

export default function SubscriptionForm({
    mode,
    subscription,
    initialValues,
    planOptions,
    planPriceOptionsByPlan,
    statusOptions,
    customerOptions,
}: SubscriptionFormProps) {
    const form = useAppForm<SubscriptionFormValues>({
        defaults: initialValues,
        rememberKey: mode === 'create' ? 'subscriptions.subscriptions.create' : `subscriptions.subscriptions.edit.${subscription?.id}`,
        dirtyGuard: { enabled: true },
        rules: {
            plan_id: [formValidators.required('Plan')],
            ...(mode === 'create' ? { plan_price_id: [formValidators.required('Billing Option')], customer_id: [formValidators.required('Customer')] } : {}),
        },
    });

    const submitMethod = mode === 'create' ? 'post' : 'put';
    const submitUrl =
        mode === 'create' ? route('subscriptions.subscriptions.store') : route('subscriptions.subscriptions.update', subscription!.id);
    const submitLabel = mode === 'create' ? 'Create Subscription' : 'Update Subscription';

    const priceOptions = useMemo(() => {
        const planId = String(form.data.plan_id);
        return planPriceOptionsByPlan[planId] || [];
    }, [form.data.plan_id, planPriceOptionsByPlan]);

    function onPlanChange(planId: string) {
        form.setData('plan_id', planId);
        form.setData('plan_price_id', '');
    }

    function onSubmit(e: FormEvent) {
        e.preventDefault();
        form.submit(submitMethod, submitUrl, {
            successToast: mode === 'create' ? 'Subscription created successfully.' : 'Subscription updated successfully.',
            setDefaultsOnSuccess: true,
        });
    }

    return (
        <form onSubmit={onSubmit} className="space-y-6">
            {form.dirtyGuardDialog}
            <FormErrorSummary errors={form.errors} />

            <div className="grid gap-6 lg:grid-cols-3">
                <div className="space-y-6 lg:col-span-2">
                    {mode === 'create' && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Customer</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <Field>
                                    <FieldLabel>Customer *</FieldLabel>
                                    <NativeSelect value={form.data.customer_id} onChange={(e) => form.setData('customer_id', e.target.value)}>
                                        <NativeSelectOption value="">Select customer...</NativeSelectOption>
                                        {customerOptions.map((opt) => (
                                            <NativeSelectOption key={opt.value} value={String(opt.value)}>
                                                {opt.label}
                                            </NativeSelectOption>
                                        ))}
                                    </NativeSelect>
                                    <FieldError>{form.errors.customer_id}</FieldError>
                                </Field>
                            </CardContent>
                        </Card>
                    )}

                    <Card>
                        <CardHeader>
                            <CardTitle>Plan & Billing</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <Field>
                                <FieldLabel>Plan *</FieldLabel>
                                <NativeSelect value={form.data.plan_id} onChange={(e) => onPlanChange(e.target.value)}>
                                    <NativeSelectOption value="">Select plan...</NativeSelectOption>
                                    {planOptions.map((opt) => (
                                        <NativeSelectOption key={opt.value} value={String(opt.value)}>
                                            {opt.label}
                                        </NativeSelectOption>
                                    ))}
                                </NativeSelect>
                                <FieldError>{form.errors.plan_id}</FieldError>
                            </Field>

                            {priceOptions.length > 0 && (
                                <Field>
                                    <FieldLabel>Billing Option {mode === 'create' ? '*' : ''}</FieldLabel>
                                    <NativeSelect
                                        value={form.data.plan_price_id}
                                        onChange={(e) => form.setData('plan_price_id', e.target.value)}
                                    >
                                        <NativeSelectOption value="">Select billing option...</NativeSelectOption>
                                        {priceOptions.map((opt) => (
                                            <NativeSelectOption key={opt.value} value={String(opt.value)}>
                                                {opt.label}
                                            </NativeSelectOption>
                                        ))}
                                    </NativeSelect>
                                    <FieldError>{form.errors.plan_price_id}</FieldError>
                                </Field>
                            )}

                            <div className="grid grid-cols-2 gap-4">
                                <Field>
                                    <FieldLabel>Custom Price</FieldLabel>
                                    <Input
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        value={form.data.price}
                                        onChange={(e) => form.setData('price', e.target.value)}
                                        placeholder="Leave blank to use plan price"
                                    />
                                    <FieldError>{form.errors.price}</FieldError>
                                </Field>
                                <Field>
                                    <FieldLabel>Currency</FieldLabel>
                                    <Input value={form.data.currency} onChange={(e) => form.setData('currency', e.target.value)} placeholder="USD" />
                                </Field>
                            </div>
                        </CardContent>
                    </Card>

                    {mode === 'edit' && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Status</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <Field>
                                    <FieldLabel>Status</FieldLabel>
                                    <NativeSelect value={form.data.status} onChange={(e) => form.setData('status', e.target.value)}>
                                        <NativeSelectOption value="">Keep current status</NativeSelectOption>
                                        {statusOptions.map((opt) => (
                                            <NativeSelectOption key={opt.value} value={String(opt.value)}>
                                                {opt.label}
                                            </NativeSelectOption>
                                        ))}
                                    </NativeSelect>
                                    <FieldError>{form.errors.status}</FieldError>
                                </Field>
                            </CardContent>
                        </Card>
                    )}
                </div>

                <div className="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Trial</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <Field>
                                <FieldLabel>Trial Days</FieldLabel>
                                <Input
                                    type="number"
                                    min="0"
                                    max="365"
                                    value={form.data.trial_days}
                                    onChange={(e) => form.setData('trial_days', Number(e.target.value))}
                                />
                            </Field>
                        </CardContent>
                    </Card>

                    <Button type="submit" className="w-full" disabled={form.processing}>
                        {form.processing && <Spinner data-icon="inline-start" />}
                        {submitLabel}
                    </Button>
                </div>
            </div>
        </form>
    );
}
