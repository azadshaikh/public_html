import type { FormEvent } from 'react';
import { Link } from '@inertiajs/react';
import { CountrySelect } from '@/components/geo/country-select';
import { StateSelect } from '@/components/geo/state-select';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useAppForm } from '@/hooks/use-app-form';
import AppLayout from '@/layouts/app-layout';
import { formValidators } from '@/lib/forms';
import type { BreadcrumbItem } from '@/types';

type BillingParty = {
    tax_id?: string | null;
    company_name?: string | null;
};

type BillingAddress = {
    country_code?: string | null;
    state?: string | null;
    state_code?: string | null;
    city?: string | null;
    address1?: string | null;
};

type AgencyTaxDetailsPageProps = {
    customer: BillingParty | null;
    billingAddress: BillingAddress | null;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Billing', href: route('agency.billing.index') },
    { title: 'Tax Details', href: route('agency.billing.tax-details') },
];

const stackedLabelClassName =
    'text-sm font-semibold tracking-[0.01em] uppercase text-foreground';

export default function AgencyTaxDetails({
    customer,
    billingAddress,
}: AgencyTaxDetailsPageProps) {
    const form = useAppForm({
        defaults: {
            country_code: billingAddress?.country_code ?? '',
            state: billingAddress?.state ?? '',
            state_code: billingAddress?.state_code ?? '',
            city: billingAddress?.city ?? '',
            vat_id: customer?.tax_id ?? '',
            company_name: customer?.company_name ?? '',
            address: billingAddress?.address1 ?? '',
        },
        rememberKey: 'agency.billing.tax-details',
        dirtyGuard: { enabled: true },
        rules: {
            country_code: [formValidators.required('Country')],
            company_name: [formValidators.required('Company Name')],
            address: [formValidators.required('Address')],
        },
    });

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.submit('post', route('agency.billing.tax-details.update'), {
            preserveScroll: true,
            setDefaultsOnSuccess: true,
            successToast: {
                title: 'Tax details updated',
                description: 'Your billing tax information has been saved successfully.',
            },
        });
    };

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Tax Details"
            description="Update the billing identity and tax information used on your invoices."
            headerActions={
                <Button variant="outline" asChild>
                    <Link href={route('agency.billing.index')}>
                        Back to Billing
                    </Link>
                </Button>
            }
        >
            <div className="mx-auto w-full max-w-2xl">
                <form className="flex flex-col gap-6" onSubmit={handleSubmit} noValidate>
                    {form.dirtyGuardDialog}

                    <Card className="border-border/60 shadow-xs">
                        <CardHeader>
                            <CardTitle>Tax information</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <FieldGroup>
                                <Field data-invalid={form.invalid('country_code') || undefined}>
                                    <FieldLabel className="text-base font-medium text-foreground normal-case">
                                        Country <span className="text-destructive">*</span>
                                    </FieldLabel>
                                    <CountrySelect
                                        value={form.data.country_code}
                                        onChange={(code) => {
                                            form.setField('country_code', code);
                                            form.setField('state_code', '');
                                            form.setField('state', '');
                                        }}
                                        placeholder="-- Select Country --"
                                        className="h-11"
                                        aria-invalid={form.invalid('country_code') || undefined}
                                    />
                                    <FieldError>{form.error('country_code')}</FieldError>
                                </Field>

                                <Field data-invalid={form.invalid('state_code') || undefined}>
                                    <FieldLabel className="text-base font-medium text-foreground normal-case">
                                        State
                                    </FieldLabel>
                                    <StateSelect
                                        countryCode={form.data.country_code}
                                        value={form.data.state_code}
                                        onChange={(code, name) => {
                                            form.setField('state_code', code);
                                            form.setField('state', name);
                                        }}
                                        placeholder="-- Select State --"
                                        className="h-11"
                                        aria-invalid={form.invalid('state_code') || undefined}
                                    />
                                    <FieldError>{form.error('state_code')}</FieldError>
                                </Field>

                                <Field data-invalid={form.invalid('city') || undefined}>
                                    <FieldLabel htmlFor="city" className={stackedLabelClassName}>
                                        City
                                    </FieldLabel>
                                    <Input
                                        id="city"
                                        value={form.data.city}
                                        onChange={(event) =>
                                            form.setField('city', event.target.value)
                                        }
                                        onBlur={() => form.touch('city')}
                                        size="xl"
                                        aria-invalid={form.invalid('city') || undefined}
                                    />
                                    <FieldError>{form.error('city')}</FieldError>
                                </Field>

                                <Field data-invalid={form.invalid('vat_id') || undefined}>
                                    <FieldLabel htmlFor="vat_id" className={stackedLabelClassName}>
                                        VAT / GST / Sales Tax ID
                                    </FieldLabel>
                                    <Input
                                        id="vat_id"
                                        value={form.data.vat_id}
                                        onChange={(event) =>
                                            form.setField('vat_id', event.target.value)
                                        }
                                        onBlur={() => form.touch('vat_id')}
                                        placeholder="e.g. GB123456789"
                                        size="xl"
                                        aria-invalid={form.invalid('vat_id') || undefined}
                                    />
                                    <FieldDescription>
                                        Enter your VAT, GST, CT, or Sales Tax ID if applicable.
                                    </FieldDescription>
                                    <FieldError>{form.error('vat_id')}</FieldError>
                                </Field>

                                <Field data-invalid={form.invalid('company_name') || undefined}>
                                    <FieldLabel htmlFor="company_name" className={stackedLabelClassName}>
                                        Company Name
                                    </FieldLabel>
                                    <Input
                                        id="company_name"
                                        value={form.data.company_name}
                                        onChange={(event) =>
                                            form.setField('company_name', event.target.value)
                                        }
                                        onBlur={() => form.touch('company_name')}
                                        size="xl"
                                        aria-invalid={form.invalid('company_name') || undefined}
                                    />
                                    <FieldError>{form.error('company_name')}</FieldError>
                                </Field>

                                <Field data-invalid={form.invalid('address') || undefined}>
                                    <FieldLabel htmlFor="address" className={stackedLabelClassName}>
                                        Address
                                    </FieldLabel>
                                    <Textarea
                                        id="address"
                                        rows={4}
                                        value={form.data.address}
                                        onChange={(event) =>
                                            form.setField('address', event.target.value)
                                        }
                                        onBlur={() => form.touch('address')}
                                        className="min-h-28"
                                        aria-invalid={form.invalid('address') || undefined}
                                    />
                                    <FieldError>{form.error('address')}</FieldError>
                                </Field>
                            </FieldGroup>
                        </CardContent>
                    </Card>

                    <Button type="submit" size="xl" className="w-full" disabled={form.processing}>
                        Save
                    </Button>
                </form>
            </div>
        </AppLayout>
    );
}
