import { useState } from 'react';
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
import type {
    BillingOption,
    InvoiceFormValues,
    InvoiceLineItem,
} from '../../types/billing';

type InvoiceFormProps = {
    mode: 'create' | 'edit';
    invoice?: { id: number; name: string };
    initialValues: InvoiceFormValues;
    statusOptions: BillingOption[];
    paymentStatusOptions: BillingOption[];
    currencyOptions: BillingOption[];
    customerOptions: BillingOption[];
};

const emptyItem: InvoiceLineItem = {
    id: null,
    name: '',
    description: '',
    quantity: 1,
    unit_price: 0,
    tax_rate: 0,
    discount_rate: 0,
    sort_order: 0,
};

export default function InvoiceForm({
    mode,
    invoice,
    initialValues,
    statusOptions,
    paymentStatusOptions,
    currencyOptions,
    customerOptions,
}: InvoiceFormProps) {
    const form = useAppForm<InvoiceFormValues>({
        defaults: initialValues,
        rememberKey:
            mode === 'create'
                ? 'billing.invoices.create'
                : `billing.invoices.edit.${invoice?.id}`,
        dirtyGuard: { enabled: true },
        rules: {
            customer_id: [formValidators.required('Customer')],
            currency: [formValidators.required('Currency')],
            issue_date: [formValidators.required('Issue Date')],
            due_date: [formValidators.required('Due Date')],
            status: [formValidators.required('Status')],
            payment_status: [formValidators.required('Payment Status')],
        },
    });

    const [items, setItems] = useState<InvoiceLineItem[]>(initialValues.items);

    const addItem = () => {
        const next = [...items, { ...emptyItem, sort_order: items.length }];
        setItems(next);
        form.setData('items', next);
    };

    const removeItem = (index: number) => {
        const next = items.filter((_, i) => i !== index);
        setItems(next);
        form.setData('items', next);
    };

    const updateItem = (
        index: number,
        field: keyof InvoiceLineItem,
        value: string | number,
    ) => {
        const next = items.map((item, i) =>
            i === index ? { ...item, [field]: value } : item,
        );
        setItems(next);
        form.setData('items', next);
    };

    const calculateLineTotal = (item: InvoiceLineItem): number => {
        const subtotal = item.quantity * item.unit_price;
        const discount = subtotal * (item.discount_rate / 100);
        const afterDiscount = subtotal - discount;
        const tax = afterDiscount * (item.tax_rate / 100);
        return afterDiscount + tax;
    };

    const grandTotal = items.reduce((sum, item) => sum + calculateLineTotal(item), 0);

    const submitMethod = mode === 'create' ? 'post' : 'put';
    const submitUrl =
        mode === 'create'
            ? route('app.billing.invoices.store')
            : route('app.billing.invoices.update', invoice!.id);
    const submitLabel = mode === 'create' ? 'Create Invoice' : 'Update Invoice';

    function onSubmit(e: FormEvent) {
        e.preventDefault();
        form.submit(submitMethod, submitUrl, {
            successToast:
                mode === 'create'
                    ? 'Invoice created successfully.'
                    : 'Invoice updated successfully.',
            setDefaultsOnSuccess: true,
        });
    }

    return (
        <form onSubmit={onSubmit} className="space-y-6">
            <FormErrorSummary errors={form.errors} />

            <div className="grid gap-6 lg:grid-cols-3">
                <div className="space-y-6 lg:col-span-2">
                    {/* Basic Details */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Invoice Details</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <FieldGroup cols={2}>
                                <Field>
                                    <FieldLabel>Customer *</FieldLabel>
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
                                    <FieldError>
                                        {form.errors.customer_id}
                                    </FieldError>
                                </Field>
                                <Field>
                                    <FieldLabel>Invoice Number</FieldLabel>
                                    <Input
                                        value={form.data.invoice_number}
                                        onChange={(e) =>
                                            form.setData(
                                                'invoice_number',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Auto-generated if blank"
                                    />
                                    <FieldError>
                                        {form.errors.invoice_number}
                                    </FieldError>
                                </Field>
                            </FieldGroup>
                            <FieldGroup cols={2}>
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
                                        placeholder="PO or reference number"
                                    />
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
                            </FieldGroup>
                            <FieldGroup cols={2}>
                                <Field>
                                    <FieldLabel>Issue Date *</FieldLabel>
                                    <Input
                                        type="date"
                                        value={form.data.issue_date}
                                        onChange={(e) =>
                                            form.setData(
                                                'issue_date',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <FieldError>
                                        {form.errors.issue_date}
                                    </FieldError>
                                </Field>
                                <Field>
                                    <FieldLabel>Due Date *</FieldLabel>
                                    <Input
                                        type="date"
                                        value={form.data.due_date}
                                        onChange={(e) =>
                                            form.setData(
                                                'due_date',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <FieldError>
                                        {form.errors.due_date}
                                    </FieldError>
                                </Field>
                            </FieldGroup>
                        </CardContent>
                    </Card>

                    {/* Billing Address */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Billing Information</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <FieldGroup cols={2}>
                                <Field>
                                    <FieldLabel>Billing Name</FieldLabel>
                                    <Input
                                        value={form.data.billing_name}
                                        onChange={(e) =>
                                            form.setData(
                                                'billing_name',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </Field>
                                <Field>
                                    <FieldLabel>Billing Email</FieldLabel>
                                    <Input
                                        type="email"
                                        value={form.data.billing_email}
                                        onChange={(e) =>
                                            form.setData(
                                                'billing_email',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </Field>
                            </FieldGroup>
                            <FieldGroup cols={2}>
                                <Field>
                                    <FieldLabel>Billing Phone</FieldLabel>
                                    <Input
                                        value={form.data.billing_phone}
                                        onChange={(e) =>
                                            form.setData(
                                                'billing_phone',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </Field>
                            </FieldGroup>
                            <Field>
                                <FieldLabel>Billing Address</FieldLabel>
                                <Textarea
                                    value={form.data.billing_address}
                                    onChange={(e) =>
                                        form.setData(
                                            'billing_address',
                                            e.target.value,
                                        )
                                    }
                                    rows={3}
                                />
                            </Field>
                        </CardContent>
                    </Card>

                    {/* Line Items */}
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between">
                            <CardTitle>Invoice Items</CardTitle>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={addItem}
                            >
                                Add Item
                            </Button>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-4">
                                {items.map((item, index) => (
                                    <div
                                        key={index}
                                        className="rounded-lg border p-4"
                                    >
                                        <div className="mb-3 flex items-center justify-between">
                                            <span className="text-sm font-medium">
                                                Item {index + 1}
                                            </span>
                                            {items.length > 1 && (
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() =>
                                                        removeItem(index)
                                                    }
                                                    className="text-destructive"
                                                >
                                                    Remove
                                                </Button>
                                            )}
                                        </div>
                                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                            <Field className="sm:col-span-2">
                                                <FieldLabel>Name *</FieldLabel>
                                                <Input
                                                    value={item.name}
                                                    onChange={(e) =>
                                                        updateItem(
                                                            index,
                                                            'name',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                            </Field>
                                            <Field>
                                                <FieldLabel>Qty *</FieldLabel>
                                                <Input
                                                    type="number"
                                                    min="0.01"
                                                    step="0.01"
                                                    value={item.quantity}
                                                    onChange={(e) =>
                                                        updateItem(
                                                            index,
                                                            'quantity',
                                                            parseFloat(
                                                                e.target.value,
                                                            ) || 0,
                                                        )
                                                    }
                                                />
                                            </Field>
                                            <Field>
                                                <FieldLabel>
                                                    Unit Price *
                                                </FieldLabel>
                                                <Input
                                                    type="number"
                                                    min="0"
                                                    step="0.01"
                                                    value={item.unit_price}
                                                    onChange={(e) =>
                                                        updateItem(
                                                            index,
                                                            'unit_price',
                                                            parseFloat(
                                                                e.target.value,
                                                            ) || 0,
                                                        )
                                                    }
                                                />
                                            </Field>
                                            <Field>
                                                <FieldLabel>Tax %</FieldLabel>
                                                <Input
                                                    type="number"
                                                    min="0"
                                                    max="100"
                                                    step="0.01"
                                                    value={item.tax_rate}
                                                    onChange={(e) =>
                                                        updateItem(
                                                            index,
                                                            'tax_rate',
                                                            parseFloat(
                                                                e.target.value,
                                                            ) || 0,
                                                        )
                                                    }
                                                />
                                            </Field>
                                            <Field>
                                                <FieldLabel>
                                                    Discount %
                                                </FieldLabel>
                                                <Input
                                                    type="number"
                                                    min="0"
                                                    max="100"
                                                    step="0.01"
                                                    value={item.discount_rate}
                                                    onChange={(e) =>
                                                        updateItem(
                                                            index,
                                                            'discount_rate',
                                                            parseFloat(
                                                                e.target.value,
                                                            ) || 0,
                                                        )
                                                    }
                                                />
                                            </Field>
                                            <Field>
                                                <FieldLabel>
                                                    Line Total
                                                </FieldLabel>
                                                <Input
                                                    value={calculateLineTotal(
                                                        item,
                                                    ).toFixed(2)}
                                                    readOnly
                                                    className="bg-muted"
                                                />
                                            </Field>
                                            <Field className="sm:col-span-2 lg:col-span-4">
                                                <FieldLabel>
                                                    Description
                                                </FieldLabel>
                                                <Input
                                                    value={item.description}
                                                    onChange={(e) =>
                                                        updateItem(
                                                            index,
                                                            'description',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                            </Field>
                                        </div>
                                    </div>
                                ))}
                                <div className="flex justify-end border-t pt-4">
                                    <div className="text-right">
                                        <span className="text-sm text-muted-foreground">
                                            Grand Total:{' '}
                                        </span>
                                        <span className="text-lg font-bold">
                                            {grandTotal.toFixed(2)}{' '}
                                            {form.data.currency}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Notes & Terms */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Notes & Terms</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <Field>
                                <FieldLabel>Notes</FieldLabel>
                                <Textarea
                                    value={form.data.notes}
                                    onChange={(e) =>
                                        form.setData('notes', e.target.value)
                                    }
                                    rows={3}
                                    placeholder="Internal notes"
                                />
                            </Field>
                            <Field>
                                <FieldLabel>Terms</FieldLabel>
                                <Textarea
                                    value={form.data.terms}
                                    onChange={(e) =>
                                        form.setData('terms', e.target.value)
                                    }
                                    rows={3}
                                    placeholder="Payment terms and conditions"
                                />
                            </Field>
                        </CardContent>
                    </Card>
                </div>

                {/* Sidebar */}
                <div className="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Status</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <Field>
                                <FieldLabel>Invoice Status *</FieldLabel>
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
                            <Field>
                                <FieldLabel>Payment Status *</FieldLabel>
                                <NativeSelect
                                    value={form.data.payment_status}
                                    onChange={(e) =>
                                        form.setData(
                                            'payment_status',
                                            e.target.value,
                                        )
                                    }
                                >
                                    {paymentStatusOptions.map((opt) => (
                                        <NativeSelectOption
                                            key={opt.value}
                                            value={opt.value}
                                        >
                                            {opt.label}
                                        </NativeSelectOption>
                                    ))}
                                </NativeSelect>
                                <FieldError>
                                    {form.errors.payment_status}
                                </FieldError>
                            </Field>
                            <Field>
                                <FieldLabel>Paid At</FieldLabel>
                                <Input
                                    type="date"
                                    value={form.data.paid_at}
                                    onChange={(e) =>
                                        form.setData('paid_at', e.target.value)
                                    }
                                />
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
