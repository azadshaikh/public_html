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
import type { BillingOption, PaymentFormValues } from '../../types/billing';

type PaymentFormProps = {
    mode: 'create' | 'edit';
    payment?: { id: number; name: string };
    initialValues: PaymentFormValues;
    statusOptions: BillingOption[];
    methodOptions: BillingOption[];
    gatewayOptions: BillingOption[];
    currencyOptions: BillingOption[];
    customerOptions: BillingOption[];
    invoiceOptions: BillingOption[];
};

export default function PaymentForm({
    mode,
    payment,
    initialValues,
    statusOptions,
    methodOptions,
    gatewayOptions,
    currencyOptions,
    customerOptions,
    invoiceOptions,
}: PaymentFormProps) {
    const form = useAppForm<PaymentFormValues>({
        defaults: initialValues,
        rememberKey:
            mode === 'create'
                ? 'billing.payments.create'
                : `billing.payments.edit.${payment?.id}`,
        dirtyGuard: { enabled: true },
        rules: {
            amount: [formValidators.required('Amount')],
            currency: [formValidators.required('Currency')],
            payment_method: [formValidators.required('Payment Method')],
            payment_gateway: [formValidators.required('Payment Gateway')],
            status: [formValidators.required('Status')],
        },
    });

    const submitMethod = mode === 'create' ? 'post' : 'put';
    const submitUrl =
        mode === 'create'
            ? route('app.billing.payments.store')
            : route('app.billing.payments.update', payment!.id);
    const submitLabel = mode === 'create' ? 'Create Payment' : 'Update Payment';

    function onSubmit(e: FormEvent) {
        e.preventDefault();
        form.submit(submitMethod, submitUrl, {
            successToast:
                mode === 'create'
                    ? 'Payment recorded successfully.'
                    : 'Payment updated successfully.',
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
                            <CardTitle>Payment Details</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <FieldGroup cols={2}>
                                <Field>
                                    <FieldLabel>Customer</FieldLabel>
                                    <NativeSelect
                                        value={form.data.customer_id}
                                        onChange={(e) =>
                                            form.setData(
                                                'customer_id',
                                                e.target.value,
                                            )
                                        }
                                    >
                                        <NativeSelectOption value="">
                                            Select customer
                                        </NativeSelectOption>
                                        {customerOptions.map((opt) => (
                                            <NativeSelectOption
                                                key={opt.value}
                                                value={opt.value}
                                            >
                                                {opt.label}
                                            </NativeSelectOption>
                                        ))}
                                    </NativeSelect>
                                </Field>
                                <Field>
                                    <FieldLabel>Invoice</FieldLabel>
                                    <NativeSelect
                                        value={form.data.invoice_id}
                                        onChange={(e) =>
                                            form.setData(
                                                'invoice_id',
                                                e.target.value,
                                            )
                                        }
                                    >
                                        <NativeSelectOption value="">
                                            No invoice
                                        </NativeSelectOption>
                                        {invoiceOptions.map((opt) => (
                                            <NativeSelectOption
                                                key={opt.value}
                                                value={opt.value}
                                            >
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
                                        onChange={(e) =>
                                            form.setData(
                                                'amount',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <FieldError>
                                        {form.errors.amount}
                                    </FieldError>
                                </Field>
                                <Field>
                                    <FieldLabel>Currency *</FieldLabel>
                                    <NativeSelect
                                        value={form.data.currency}
                                        onChange={(e) =>
                                            form.setData(
                                                'currency',
                                                e.target.value,
                                            )
                                        }
                                    >
                                        {currencyOptions.map((opt) => (
                                            <NativeSelectOption
                                                key={opt.value}
                                                value={opt.value}
                                            >
                                                {opt.label}
                                            </NativeSelectOption>
                                        ))}
                                    </NativeSelect>
                                    <FieldError>
                                        {form.errors.currency}
                                    </FieldError>
                                </Field>
                                <Field>
                                    <FieldLabel>Exchange Rate</FieldLabel>
                                    <Input
                                        type="number"
                                        min="0"
                                        step="0.000001"
                                        value={form.data.exchange_rate}
                                        onChange={(e) =>
                                            form.setData(
                                                'exchange_rate',
                                                parseFloat(e.target.value) || 1,
                                            )
                                        }
                                    />
                                </Field>
                            </FieldGroup>
                            <FieldGroup cols={2}>
                                <Field>
                                    <FieldLabel>Payment Method *</FieldLabel>
                                    <NativeSelect
                                        value={form.data.payment_method}
                                        onChange={(e) =>
                                            form.setData(
                                                'payment_method',
                                                e.target.value,
                                            )
                                        }
                                    >
                                        <NativeSelectOption value="">
                                            Select method
                                        </NativeSelectOption>
                                        {methodOptions.map((opt) => (
                                            <NativeSelectOption
                                                key={opt.value}
                                                value={opt.value}
                                            >
                                                {opt.label}
                                            </NativeSelectOption>
                                        ))}
                                    </NativeSelect>
                                    <FieldError>
                                        {form.errors.payment_method}
                                    </FieldError>
                                </Field>
                                <Field>
                                    <FieldLabel>Payment Gateway *</FieldLabel>
                                    <NativeSelect
                                        value={form.data.payment_gateway}
                                        onChange={(e) =>
                                            form.setData(
                                                'payment_gateway',
                                                e.target.value,
                                            )
                                        }
                                    >
                                        <NativeSelectOption value="">
                                            Select gateway
                                        </NativeSelectOption>
                                        {gatewayOptions.map((opt) => (
                                            <NativeSelectOption
                                                key={opt.value}
                                                value={opt.value}
                                            >
                                                {opt.label}
                                            </NativeSelectOption>
                                        ))}
                                    </NativeSelect>
                                    <FieldError>
                                        {form.errors.payment_gateway}
                                    </FieldError>
                                </Field>
                            </FieldGroup>
                            <FieldGroup cols={2}>
                                <Field>
                                    <FieldLabel>Payment Number</FieldLabel>
                                    <Input
                                        value={form.data.payment_number}
                                        onChange={(e) =>
                                            form.setData(
                                                'payment_number',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Auto-generated if blank"
                                    />
                                </Field>
                                <Field>
                                    <FieldLabel>Reference</FieldLabel>
                                    <Input
                                        value={form.data.reference}
                                        onChange={(e) =>
                                            form.setData(
                                                'reference',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </Field>
                            </FieldGroup>
                            <FieldGroup cols={2}>
                                <Field>
                                    <FieldLabel>Gateway Transaction ID</FieldLabel>
                                    <Input
                                        value={form.data.gateway_transaction_id}
                                        onChange={(e) =>
                                            form.setData(
                                                'gateway_transaction_id',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </Field>
                            </FieldGroup>
                            <FieldGroup cols={2}>
                                <Field>
                                    <FieldLabel>Paid At</FieldLabel>
                                    <Input
                                        type="date"
                                        value={form.data.paid_at}
                                        onChange={(e) =>
                                            form.setData(
                                                'paid_at',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </Field>
                                <Field>
                                    <FieldLabel>Failed At</FieldLabel>
                                    <Input
                                        type="date"
                                        value={form.data.failed_at}
                                        onChange={(e) =>
                                            form.setData(
                                                'failed_at',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </Field>
                            </FieldGroup>
                            <Field>
                                <FieldLabel>Notes</FieldLabel>
                                <Textarea
                                    value={form.data.notes}
                                    onChange={(e) =>
                                        form.setData('notes', e.target.value)
                                    }
                                    rows={3}
                                />
                            </Field>
                        </CardContent>
                    </Card>
                </div>

                <div className="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Status</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <Field>
                                <FieldLabel>Payment Status *</FieldLabel>
                                <NativeSelect
                                    value={form.data.status}
                                    onChange={(e) =>
                                        form.setData('status', e.target.value)
                                    }
                                >
                                    {statusOptions.map((opt) => (
                                        <NativeSelectOption
                                            key={opt.value}
                                            value={opt.value}
                                        >
                                            {opt.label}
                                        </NativeSelectOption>
                                    ))}
                                </NativeSelect>
                                <FieldError>{form.errors.status}</FieldError>
                            </Field>
                        </CardContent>
                    </Card>

                    <Button
                        type="submit"
                        className="w-full"
                        disabled={form.processing}
                    >
                        {form.processing && <Spinner />}
                        {submitLabel}
                    </Button>
                </div>
            </div>
        </form>
    );
}
