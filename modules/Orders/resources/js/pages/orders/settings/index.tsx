import type { FormEvent } from 'react';
import { FormErrorSummary } from '@/components/forms/form-error-summary';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Field, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { NativeSelect, NativeSelectOption } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import { useAppForm } from '@/hooks/use-app-form';
import { formValidators } from '@/lib/forms';
import type { BreadcrumbItem } from '@/types';
import type { OrderSettingsPageProps, OrderSettingsValues } from '../../../types/orders';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Orders', href: route('app.orders.index') },
    { title: 'Settings', href: route('app.orders.settings.index') },
];

export default function OrderSettings({ initialValues, digitLengthOptions, formatOptions }: OrderSettingsPageProps) {
    const form = useAppForm<OrderSettingsValues>({
        rememberKey: 'orders.settings.order-number',
        defaults: initialValues,
        dirtyGuard: { enabled: true },
        rules: {
            order_prefix: [formValidators.required('Order Prefix')],
            order_serial_number: [formValidators.required('Serial Number')],
        },
    });

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.submit('post', route('app.orders.settings.update-order-number'), {
            preserveScroll: true,
            setDefaultsOnSuccess: true,
            successToast: {
                title: 'Order settings updated',
                description: 'Order numbering settings have been saved successfully.',
            },
        });
    };

    const previewNumber = (() => {
        const prefix = form.data.order_prefix || 'ORD';
        const serial = String(form.data.order_serial_number || '1').padStart(Number(form.data.order_digit_length) || 4, '0');
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');

        switch (form.data.order_format) {
            case 'date_sequence':
                return `${prefix}-${year}${month}${day}-${serial}`;
            case 'year_sequence':
                return `${prefix}-${year}-${serial}`;
            case 'year_month_sequence':
                return `${prefix}-${year}${month}-${serial}`;
            case 'sequence_only':
            default:
                return `${prefix}-${serial}`;
        }
    })();

    return (
        <AppLayout breadcrumbs={breadcrumbs} title="Order Settings" description="Configure order numbering">
            <div className="mx-auto max-w-2xl">
                <form className="space-y-6" onSubmit={handleSubmit} noValidate>
                    {form.dirtyGuardDialog}
                    <FormErrorSummary errors={form.errors} minMessages={2} />

                    <Card>
                        <CardHeader>
                            <CardTitle>Order Numbering</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <FieldGroup>
                                <Field data-invalid={form.invalid('order_prefix') || undefined}>
                                    <FieldLabel htmlFor="order_prefix">
                                        Order Prefix <span className="text-destructive">*</span>
                                    </FieldLabel>
                                    <Input
                                        id="order_prefix"
                                        value={form.data.order_prefix}
                                        onChange={(e) => form.setField('order_prefix', e.target.value)}
                                        onBlur={() => form.touch('order_prefix')}
                                        aria-invalid={form.invalid('order_prefix') || undefined}
                                        placeholder="e.g. ORD"
                                    />
                                    <FieldError>{form.error('order_prefix')}</FieldError>
                                </Field>

                                <Field data-invalid={form.invalid('order_serial_number') || undefined}>
                                    <FieldLabel htmlFor="order_serial_number">
                                        Next Serial Number <span className="text-destructive">*</span>
                                    </FieldLabel>
                                    <Input
                                        id="order_serial_number"
                                        type="number"
                                        min="1"
                                        value={form.data.order_serial_number}
                                        onChange={(e) => form.setField('order_serial_number', Number(e.target.value))}
                                        onBlur={() => form.touch('order_serial_number')}
                                        aria-invalid={form.invalid('order_serial_number') || undefined}
                                    />
                                    <FieldError>{form.error('order_serial_number')}</FieldError>
                                </Field>

                                <Field data-invalid={form.invalid('order_digit_length') || undefined}>
                                    <FieldLabel htmlFor="order_digit_length">Digit Length</FieldLabel>
                                    <NativeSelect
                                        id="order_digit_length"
                                        value={form.data.order_digit_length}
                                        onChange={(e) => form.setField('order_digit_length', Number(e.target.value))}
                                        aria-invalid={form.invalid('order_digit_length') || undefined}
                                    >
                                        {digitLengthOptions.map((option) => (
                                            <NativeSelectOption key={option.value} value={option.value}>
                                                {option.label}
                                            </NativeSelectOption>
                                        ))}
                                    </NativeSelect>
                                    <FieldError>{form.error('order_digit_length')}</FieldError>
                                </Field>

                                <Field data-invalid={form.invalid('order_format') || undefined}>
                                    <FieldLabel htmlFor="order_format">Order Format</FieldLabel>
                                    <NativeSelect
                                        id="order_format"
                                        value={form.data.order_format}
                                        onChange={(e) => form.setField('order_format', e.target.value)}
                                        aria-invalid={form.invalid('order_format') || undefined}
                                    >
                                        {formatOptions.map((option) => (
                                            <NativeSelectOption key={option.value} value={option.value}>
                                                {option.label}{option.example ? ` (${option.example})` : ''}
                                            </NativeSelectOption>
                                        ))}
                                    </NativeSelect>
                                    <FieldError>{form.error('order_format')}</FieldError>
                                </Field>

                                <div className="rounded-lg border bg-muted/30 p-4">
                                    <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">Preview</span>
                                    <div className="mt-1 font-mono text-lg font-bold text-foreground">{previewNumber}</div>
                                </div>
                            </FieldGroup>
                        </CardContent>
                    </Card>

                    <Button type="submit" className="w-full" disabled={form.processing}>
                        {form.processing ? <Spinner className="mr-2" /> : null}
                        Save Order Settings
                    </Button>
                </form>
            </div>
        </AppLayout>
    );
}
