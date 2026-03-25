import type { FormEvent } from 'react';
import { FormErrorSummary } from '@/components/forms/form-error-summary';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Field,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { useAppForm } from '@/hooks/use-app-form';
import { formValidators } from '@/lib/forms';
import type { BillingOption, CouponFormValues } from '../../types/billing';

type CouponFormProps = {
    mode: 'create' | 'edit';
    coupon?: { id: number; name: string };
    initialValues: CouponFormValues;
    typeOptions: BillingOption[];
    durationOptions: BillingOption[];
    planOptions: BillingOption[];
};

function numericInputValue(value: string): string | number {
    return value === '' ? '' : Number(value);
}

export default function CouponForm({
    mode,
    coupon,
    initialValues,
    typeOptions,
    durationOptions,
    planOptions,
}: CouponFormProps) {
    const form = useAppForm<CouponFormValues>({
        defaults: initialValues,
        rememberKey:
            mode === 'create'
                ? 'billing.coupons.create'
                : `billing.coupons.edit.${coupon?.id}`,
        dirtyGuard: { enabled: true },
        rules: {
            code: [formValidators.required('Code')],
            name: [formValidators.required('Name')],
            type: [formValidators.required('Type')],
            value: [formValidators.required('Value')],
            discount_duration: [formValidators.required('Duration')],
        },
    });

    const submitMethod = mode === 'create' ? 'post' : 'put';
    const submitUrl =
        mode === 'create'
            ? route('app.billing.coupons.store')
            : route('app.billing.coupons.update', coupon!.id);
    const submitLabel = mode === 'create' ? 'Create Coupon' : 'Update Coupon';

    function onSubmit(e: FormEvent) {
        e.preventDefault();
        form.submit(submitMethod, submitUrl, {
            successToast:
                mode === 'create'
                    ? 'Coupon created successfully.'
                    : 'Coupon updated successfully.',
            setDefaultsOnSuccess: true,
        });
    }

    return (
        <form onSubmit={onSubmit} className="space-y-6">
            <FormErrorSummary errors={form.errors} />

            <div className="grid gap-6 lg:grid-cols-3">
                <div className="space-y-6 lg:col-span-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Coupon Details</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <FieldGroup cols={2}>
                                <Field>
                                    <FieldLabel>Code *</FieldLabel>
                                    <Input
                                        value={form.data.code}
                                        onChange={(e) => form.setData('code', e.target.value)}
                                        placeholder="e.g. SAVE20"
                                    />
                                    <FieldError>{form.errors.code}</FieldError>
                                </Field>
                                <Field>
                                    <FieldLabel>Name *</FieldLabel>
                                    <Input
                                        value={form.data.name}
                                        onChange={(e) => form.setData('name', e.target.value)}
                                        placeholder="e.g. 20% Holiday Discount"
                                    />
                                    <FieldError>{form.errors.name}</FieldError>
                                </Field>
                            </FieldGroup>
                            <Field>
                                <FieldLabel>Description</FieldLabel>
                                <Textarea
                                    value={form.data.description}
                                    onChange={(e) => form.setData('description', e.target.value)}
                                    rows={2}
                                />
                            </Field>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Discount</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <FieldGroup cols={3}>
                                <Field>
                                    <FieldLabel>Type *</FieldLabel>
                                    <NativeSelect
                                        value={form.data.type}
                                        onChange={(e) => form.setData('type', e.target.value)}
                                    >
                                        <NativeSelectOption value="">Select type...</NativeSelectOption>
                                        {typeOptions.map((opt) => (
                                            <NativeSelectOption key={opt.value} value={opt.value}>
                                                {opt.label}
                                            </NativeSelectOption>
                                        ))}
                                    </NativeSelect>
                                    <FieldError>{form.errors.type}</FieldError>
                                </Field>
                                <Field>
                                    <FieldLabel>Value *</FieldLabel>
                                    <Input
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        value={form.data.value}
                                        onChange={(e) =>
                                            form.setData(
                                                'value',
                                                numericInputValue(
                                                    e.target.value,
                                                ),
                                            )
                                        }
                                        placeholder={form.data.type === 'percent' ? 'e.g. 20' : 'e.g. 10.00'}
                                    />
                                    <FieldError>{form.errors.value}</FieldError>
                                </Field>
                                <Field>
                                    <FieldLabel>Currency</FieldLabel>
                                    <NativeSelect
                                        value={form.data.currency}
                                        onChange={(e) => form.setData('currency', e.target.value)}
                                    >
                                        <NativeSelectOption value="USD">USD</NativeSelectOption>
                                        <NativeSelectOption value="EUR">EUR</NativeSelectOption>
                                        <NativeSelectOption value="GBP">GBP</NativeSelectOption>
                                        <NativeSelectOption value="INR">INR</NativeSelectOption>
                                    </NativeSelect>
                                </Field>
                            </FieldGroup>
                            <FieldGroup cols={2}>
                                <Field>
                                    <FieldLabel>Duration *</FieldLabel>
                                    <NativeSelect
                                        value={form.data.discount_duration}
                                        onChange={(e) => form.setData('discount_duration', e.target.value)}
                                    >
                                        <NativeSelectOption value="">Select duration...</NativeSelectOption>
                                        {durationOptions.map((opt) => (
                                            <NativeSelectOption key={opt.value} value={opt.value}>
                                                {opt.label}
                                            </NativeSelectOption>
                                        ))}
                                    </NativeSelect>
                                    <FieldError>{form.errors.discount_duration}</FieldError>
                                </Field>
                                {form.data.discount_duration === 'repeating' && (
                                    <Field>
                                        <FieldLabel>Duration (Months)</FieldLabel>
                                        <Input
                                            type="number"
                                            min="1"
                                            value={form.data.duration_in_months}
                                            onChange={(e) =>
                                                form.setData(
                                                    'duration_in_months',
                                                    numericInputValue(
                                                        e.target.value,
                                                    ),
                                                )
                                            }
                                        />
                                    </Field>
                                )}
                            </FieldGroup>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Usage Limits</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <FieldGroup cols={3}>
                                <Field>
                                    <FieldLabel>Max Uses</FieldLabel>
                                        <Input
                                            type="number"
                                            min="0"
                                            value={form.data.max_uses}
                                            onChange={(e) =>
                                                form.setData(
                                                    'max_uses',
                                                    numericInputValue(
                                                        e.target.value,
                                                    ),
                                                )
                                            }
                                            placeholder="Unlimited"
                                        />
                                    </Field>
                                <Field>
                                    <FieldLabel>Max Per Customer</FieldLabel>
                                        <Input
                                            type="number"
                                            min="0"
                                            value={form.data.max_uses_per_customer}
                                            onChange={(e) =>
                                                form.setData(
                                                    'max_uses_per_customer',
                                                    numericInputValue(
                                                        e.target.value,
                                                    ),
                                                )
                                            }
                                            placeholder="Unlimited"
                                        />
                                    </Field>
                                <Field>
                                    <FieldLabel>Min Order Amount</FieldLabel>
                                        <Input
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            value={form.data.min_order_amount}
                                            onChange={(e) =>
                                                form.setData(
                                                    'min_order_amount',
                                                    numericInputValue(
                                                        e.target.value,
                                                    ),
                                                )
                                            }
                                            placeholder="No minimum"
                                        />
                                    </Field>
                            </FieldGroup>
                        </CardContent>
                    </Card>

                    {planOptions.length > 0 && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Applicable Plans</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-2">
                                    {planOptions.map((plan) => (
                                        <label key={plan.value} className="flex items-center gap-2">
                                            <input
                                                type="checkbox"
                                                className="rounded border-gray-300"
                                                checked={(form.data.applicable_plan_ids ?? []).includes(Number(plan.value))}
                                                onChange={(e) => {
                                                    const current = form.data.applicable_plan_ids ?? [];
                                                    const planId = Number(plan.value);
                                                    form.setData(
                                                        'applicable_plan_ids',
                                                        e.target.checked
                                                            ? [...current, planId]
                                                            : current.filter((id) => id !== planId),
                                                    );
                                                }}
                                            />
                                            <span className="text-sm">{plan.label}</span>
                                        </label>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>
                    )}
                </div>

                <div className="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Status</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <Field className="flex items-center justify-between">
                                <FieldLabel>Active</FieldLabel>
                                <Switch
                                    checked={form.data.is_active}
                                    onCheckedChange={(checked) => form.setData('is_active', checked)}
                                />
                            </Field>
                            <Field>
                                <FieldLabel>Expires At</FieldLabel>
                                <Input
                                    type="date"
                                    value={form.data.expires_at}
                                    onChange={(e) => form.setData('expires_at', e.target.value)}
                                />
                            </Field>
                        </CardContent>
                    </Card>

                    <Button type="submit" className="w-full" disabled={form.processing}>
                        {form.processing && <Spinner />}
                        {submitLabel}
                    </Button>
                </div>
            </div>
        </form>
    );
}
