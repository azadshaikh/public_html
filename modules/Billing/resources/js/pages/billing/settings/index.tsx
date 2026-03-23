import { Link } from '@inertiajs/react';
import { ArrowLeftIcon, SettingsIcon } from 'lucide-react';
import type { FormEvent } from 'react';
import { FormErrorSummary } from '@/components/forms/form-error-summary';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Field, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { NativeSelect, NativeSelectOption } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { useAppForm } from '@/hooks/use-app-form';
import AppLayout from '@/layouts/app-layout';
import { formValidators } from '@/lib/forms';
import type { BreadcrumbItem } from '@/types';
import type { InvoiceSettings, SettingsPageProps, StripeSettings } from '../../../types/billing';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Billing', href: route('app.billing.invoices.index') },
    { title: 'Settings', href: route('app.billing.settings.index') },
];

function InvoiceSettingsForm({
    initialValues,
    invoiceDigitLengthOptions,
    invoiceFormatOptions,
}: {
    initialValues: InvoiceSettings;
    invoiceDigitLengthOptions: SettingsPageProps['invoiceDigitLengthOptions'];
    invoiceFormatOptions: SettingsPageProps['invoiceFormatOptions'];
}) {
    const form = useAppForm<InvoiceSettings>({
        rememberKey: 'billing.settings.invoice',
        defaults: initialValues,
        dirtyGuard: { enabled: true },
        rules: {
            invoice_prefix: [formValidators.required('Invoice Prefix')],
            invoice_serial_number: [formValidators.required('Serial Number')],
        },
    });

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.submit('post', route('app.billing.settings.update-invoice-prefix'), {
            preserveScroll: true,
            setDefaultsOnSuccess: true,
            successToast: {
                title: 'Invoice settings updated',
                description: 'Invoice numbering settings have been saved successfully.',
            },
        });
    };

    const previewNumber = `${form.data.invoice_prefix}${String(form.data.invoice_serial_number || '1').padStart(Number(form.data.invoice_digit_length) || 4, '0')}`;

    return (
        <form className="space-y-6" onSubmit={handleSubmit} noValidate>
            {form.dirtyGuardDialog}
            <FormErrorSummary errors={form.errors} minMessages={2} />

            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <SettingsIcon data-icon="inline-start" />
                        Invoice Numbering
                    </CardTitle>
                </CardHeader>
                <CardContent className="flex flex-col gap-6">
                    <FieldGroup className="md:grid-cols-2">
                        <Field data-invalid={form.invalid('invoice_prefix') || undefined}>
                            <FieldLabel htmlFor="invoice_prefix">
                                Invoice Prefix <span className="text-destructive">*</span>
                            </FieldLabel>
                            <Input
                                id="invoice_prefix"
                                value={form.data.invoice_prefix}
                                onChange={(e) => form.setField('invoice_prefix', e.target.value)}
                                onBlur={() => form.touch('invoice_prefix')}
                                aria-invalid={form.invalid('invoice_prefix') || undefined}
                                placeholder="e.g. INV-"
                            />
                            <FieldError>{form.error('invoice_prefix')}</FieldError>
                        </Field>

                        <Field data-invalid={form.invalid('invoice_serial_number') || undefined}>
                            <FieldLabel htmlFor="invoice_serial_number">
                                Next Serial Number <span className="text-destructive">*</span>
                            </FieldLabel>
                            <Input
                                id="invoice_serial_number"
                                type="number"
                                min="1"
                                value={form.data.invoice_serial_number}
                                onChange={(e) => form.setField('invoice_serial_number', Number(e.target.value))}
                                onBlur={() => form.touch('invoice_serial_number')}
                                aria-invalid={form.invalid('invoice_serial_number') || undefined}
                            />
                            <FieldError>{form.error('invoice_serial_number')}</FieldError>
                        </Field>

                        <Field data-invalid={form.invalid('invoice_digit_length') || undefined}>
                            <FieldLabel htmlFor="invoice_digit_length">Digit Length</FieldLabel>
                            <NativeSelect
                                id="invoice_digit_length"
                                value={form.data.invoice_digit_length}
                                onChange={(e) => form.setField('invoice_digit_length', Number(e.target.value))}
                                aria-invalid={form.invalid('invoice_digit_length') || undefined}
                            >
                                {invoiceDigitLengthOptions.map((option) => (
                                    <NativeSelectOption key={option.value} value={option.value}>
                                        {option.label}
                                    </NativeSelectOption>
                                ))}
                            </NativeSelect>
                            <FieldError>{form.error('invoice_digit_length')}</FieldError>
                        </Field>

                        <Field data-invalid={form.invalid('invoice_format') || undefined}>
                            <FieldLabel htmlFor="invoice_format">Invoice Format</FieldLabel>
                            <NativeSelect
                                id="invoice_format"
                                value={form.data.invoice_format}
                                onChange={(e) => form.setField('invoice_format', e.target.value)}
                                aria-invalid={form.invalid('invoice_format') || undefined}
                            >
                                {invoiceFormatOptions.map((option) => (
                                    <NativeSelectOption key={option.value} value={option.value}>
                                        {option.label}
                                    </NativeSelectOption>
                                ))}
                            </NativeSelect>
                            <FieldError>{form.error('invoice_format')}</FieldError>
                        </Field>
                    </FieldGroup>

                    <div className="rounded-lg border bg-muted/30 p-4 sm:p-5">
                        <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">Preview</span>
                        <div className="mt-2 font-mono text-lg font-bold text-foreground sm:text-2xl">{previewNumber}</div>
                    </div>
                </CardContent>
            </Card>

            <Button type="submit" className="w-full" disabled={form.processing}>
                {form.processing ? <Spinner className="mr-2" /> : null}
                Save Invoice Settings
            </Button>
        </form>
    );
}

function StripeSettingsForm({ initialValues }: { initialValues: StripeSettings }) {
    const form = useAppForm<StripeSettings>({
        rememberKey: 'billing.settings.stripe',
        defaults: initialValues,
        dirtyGuard: { enabled: true },
        rules: {
            stripe_key: [formValidators.required('Stripe Key')],
            stripe_secret: [formValidators.required('Stripe Secret')],
            stripe_webhook_secret: [formValidators.required('Webhook Secret')],
        },
    });

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.submit('post', route('app.billing.settings.update-stripe'), {
            preserveScroll: true,
            setDefaultsOnSuccess: true,
            successToast: {
                title: 'Stripe settings updated',
                description: 'Stripe integration settings have been saved successfully.',
            },
        });
    };

    return (
        <form className="space-y-6" onSubmit={handleSubmit} noValidate>
            {form.dirtyGuardDialog}
            <FormErrorSummary errors={form.errors} minMessages={2} />

            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <SettingsIcon data-icon="inline-start" />
                        Stripe Integration
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <FieldGroup className="md:grid-cols-2">
                        <Field data-invalid={form.invalid('stripe_key') || undefined}>
                            <FieldLabel htmlFor="stripe_key">
                                Stripe Publishable Key <span className="text-destructive">*</span>
                            </FieldLabel>
                            <Input
                                id="stripe_key"
                                value={form.data.stripe_key}
                                onChange={(e) => form.setField('stripe_key', e.target.value)}
                                onBlur={() => form.touch('stripe_key')}
                                aria-invalid={form.invalid('stripe_key') || undefined}
                                placeholder="pk_..."
                            />
                            <FieldError>{form.error('stripe_key')}</FieldError>
                        </Field>

                        <Field data-invalid={form.invalid('stripe_secret') || undefined}>
                            <FieldLabel htmlFor="stripe_secret">
                                Stripe Secret Key <span className="text-destructive">*</span>
                            </FieldLabel>
                            <Input
                                id="stripe_secret"
                                type="password"
                                value={form.data.stripe_secret}
                                onChange={(e) => form.setField('stripe_secret', e.target.value)}
                                onBlur={() => form.touch('stripe_secret')}
                                aria-invalid={form.invalid('stripe_secret') || undefined}
                                placeholder="sk_..."
                            />
                            <FieldError>{form.error('stripe_secret')}</FieldError>
                        </Field>

                        <Field data-invalid={form.invalid('stripe_webhook_secret') || undefined}>
                            <FieldLabel htmlFor="stripe_webhook_secret">
                                Webhook Secret <span className="text-destructive">*</span>
                            </FieldLabel>
                            <Input
                                id="stripe_webhook_secret"
                                type="password"
                                value={form.data.stripe_webhook_secret}
                                onChange={(e) => form.setField('stripe_webhook_secret', e.target.value)}
                                onBlur={() => form.touch('stripe_webhook_secret')}
                                aria-invalid={form.invalid('stripe_webhook_secret') || undefined}
                                placeholder="whsec_..."
                            />
                            <FieldError>{form.error('stripe_webhook_secret')}</FieldError>
                        </Field>
                    </FieldGroup>
                </CardContent>
            </Card>

            <Button type="submit" className="w-full" disabled={form.processing}>
                {form.processing ? <Spinner className="mr-2" /> : null}
                Save Stripe Settings
            </Button>
        </form>
    );
}

export default function BillingSettings({
    invoiceSettings,
    stripeSettings,
    invoiceDigitLengthOptions,
    invoiceFormatOptions,
}: SettingsPageProps) {
    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Billing Settings"
            description="Configure invoice numbering and Stripe integration"
            headerActions={
                <Button variant="outline" asChild>
                    <Link href={route('app.billing.invoices.index')}>
                        <ArrowLeftIcon data-icon="inline-start" />
                        Back to Billing
                    </Link>
                </Button>
            }
        >
            <div className="mx-auto w-full max-w-3xl space-y-10">
                <InvoiceSettingsForm
                    initialValues={invoiceSettings}
                    invoiceDigitLengthOptions={invoiceDigitLengthOptions}
                    invoiceFormatOptions={invoiceFormatOptions}
                />
                <StripeSettingsForm initialValues={stripeSettings} />
            </div>
        </AppLayout>
    );
}
