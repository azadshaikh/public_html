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
import { Textarea } from '@/components/ui/textarea';
import { useAppForm } from '@/hooks/use-app-form';
import { formValidators } from '@/lib/forms';
import type { BillingOption, CreditFormValues } from '../../types/billing';

type CreditFormProps = {
    mode: 'create' | 'edit';
    credit?: { id: number; name: string };
    initialValues: CreditFormValues;
    statusOptions: BillingOption[];
    typeOptions: BillingOption[];
    currencyOptions: BillingOption[];
    customerOptions: BillingOption[];
    invoiceOptions: BillingOption[];
};

export default function CreditForm({
    mode,
    credit,
    initialValues,
    statusOptions,
    typeOptions,
    currencyOptions,
    customerOptions,
    invoiceOptions,
}: CreditFormProps) {
    const form = useAppForm<CreditFormValues>({
        defaults: initialValues,
        rememberKey:
            mode === 'create'
                ? 'billing.credits.create'
                : `billing.credits.edit.${credit?.id}`,
        dirtyGuard: { enabled: true },
        rules: {
            customer_id: [formValidators.required('Customer')],
            amount: [formValidators.required('Amount')],
            currency: [formValidators.required('Currency')],
            type: [formValidators.required('Type')],
            status: [formValidators.required('Status')],
        },
    });

    const submitMethod = mode === 'create' ? 'post' : 'put';
    const submitUrl =
        mode === 'create'
            ? route('app.billing.credits.store')
            : route('app.billing.credits.update', credit!.id);
    const submitLabel = mode === 'create' ? 'Create Credit' : 'Update Credit';

    function onSubmit(e: FormEvent) {
        e.preventDefault();
        form.submit(submitMethod, submitUrl, {
            successToast:
                mode === 'create'
                    ? 'Credit created successfully.'
                    : 'Credit updated successfully.',
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
                            <CardTitle>Credit Details</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <FieldGroup cols={2}>
                                <Field>
                                    <FieldLabel>Customer *</FieldLabel>
                                    <NativeSelect
                                        value={form.data.customer_id}
                                        onChange={(e) => form.setData('customer_id', e.target.value)}
                                    >
                                        <NativeSelectOption value="">Select customer</NativeSelectOption>
                                        {customerOptions.map((opt) => (
                                            <NativeSelectOption key={opt.value} value={opt.value}>
                                                {opt.label}
                                            </NativeSelectOption>
                                        ))}
                                    </NativeSelect>
                                    <FieldError>{form.errors.customer_id}</FieldError>
                                </Field>
                                <Field>
                                    <FieldLabel>Invoice</FieldLabel>
                                    <NativeSelect
                                        value={form.data.invoice_id}
                                        onChange={(e) => form.setData('invoice_id', e.target.value)}
                                    >
                                        <NativeSelectOption value="">No invoice</NativeSelectOption>
                                        {invoiceOptions.map((opt) => (
                                            <NativeSelectOption key={opt.value} value={opt.value}>
                                                {opt.label}
                                            </NativeSelectOption>
                                        ))}
                                    </NativeSelect>
                                </Field>
                            </FieldGroup>
                            <FieldGroup cols={3}>
                                <Field>
                                    <FieldLabel>Amount *</FieldLabel>
                                    <Input
                                        type="number"
                                        min="0.01"
                                        step="0.01"
                                        value={form.data.amount}
                                        onChange={(e) => form.setData('amount', e.target.value)}
                                    />
                                    <FieldError>{form.errors.amount}</FieldError>
                                </Field>
                                <Field>
                                    <FieldLabel>Currency *</FieldLabel>
                                    <NativeSelect
                                        value={form.data.currency}
                                        onChange={(e) => form.setData('currency', e.target.value)}
                                    >
                                        {currencyOptions.map((opt) => (
                                            <NativeSelectOption key={opt.value} value={opt.value}>
                                                {opt.label}
                                            </NativeSelectOption>
                                        ))}
                                    </NativeSelect>
                                    <FieldError>{form.errors.currency}</FieldError>
                                </Field>
                                <Field>
                                    <FieldLabel>Expires At</FieldLabel>
                                    <Input
                                        type="date"
                                        value={form.data.expires_at}
                                        onChange={(e) => form.setData('expires_at', e.target.value)}
                                    />
                                </Field>
                            </FieldGroup>
                            <FieldGroup cols={2}>
                                <Field>
                                    <FieldLabel>Credit Number</FieldLabel>
                                    <Input
                                        value={form.data.credit_number}
                                        onChange={(e) => form.setData('credit_number', e.target.value)}
                                        placeholder="Auto-generated if blank"
                                    />
                                </Field>
                                <Field>
                                    <FieldLabel>Reference</FieldLabel>
                                    <Input
                                        value={form.data.reference}
                                        onChange={(e) => form.setData('reference', e.target.value)}
                                    />
                                </Field>
                            </FieldGroup>
                            <Field>
                                <FieldLabel>Reason</FieldLabel>
                                <Textarea
                                    value={form.data.reason}
                                    onChange={(e) => form.setData('reason', e.target.value)}
                                    rows={2}
                                />
                            </Field>
                            <Field>
                                <FieldLabel>Notes</FieldLabel>
                                <Textarea
                                    value={form.data.notes}
                                    onChange={(e) => form.setData('notes', e.target.value)}
                                    rows={3}
                                />
                            </Field>
                        </CardContent>
                    </Card>
                </div>

                <div className="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Classification</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <Field>
                                <FieldLabel>Type *</FieldLabel>
                                <NativeSelect
                                    value={form.data.type}
                                    onChange={(e) => form.setData('type', e.target.value)}
                                >
                                    <NativeSelectOption value="">Select type</NativeSelectOption>
                                    {typeOptions.map((opt) => (
                                        <NativeSelectOption key={opt.value} value={opt.value}>
                                            {opt.label}
                                        </NativeSelectOption>
                                    ))}
                                </NativeSelect>
                                <FieldError>{form.errors.type}</FieldError>
                            </Field>
                            <Field>
                                <FieldLabel>Status *</FieldLabel>
                                <NativeSelect
                                    value={form.data.status}
                                    onChange={(e) => form.setData('status', e.target.value)}
                                >
                                    {statusOptions.map((opt) => (
                                        <NativeSelectOption key={opt.value} value={opt.value}>
                                            {opt.label}
                                        </NativeSelectOption>
                                    ))}
                                </NativeSelect>
                                <FieldError>{form.errors.status}</FieldError>
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
