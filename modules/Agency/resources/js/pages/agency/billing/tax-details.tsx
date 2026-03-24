import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { FormErrorSummary } from '@/components/forms/form-error-summary';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Field, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
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

export default function AgencyTaxDetails({
    customer,
    billingAddress,
}: AgencyTaxDetailsPageProps) {
    const form = useForm({
        country_code: billingAddress?.country_code ?? '',
        state: billingAddress?.state ?? '',
        state_code: billingAddress?.state_code ?? '',
        city: billingAddress?.city ?? '',
        vat_id: customer?.tax_id ?? '',
        company_name: customer?.company_name ?? '',
        address: billingAddress?.address1 ?? '',
    });

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(route('agency.billing.tax-details.update'));
    };

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Tax Details"
            description="Update the billing identity and tax information used on your invoices."
        >
            <Card className="mx-auto max-w-3xl">
                <CardHeader>
                    <CardTitle>Billing Identity</CardTitle>
                    <CardDescription>
                        Leave optional fields empty when they do not apply to
                        your business.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form className="space-y-6" onSubmit={handleSubmit}>
                        <FormErrorSummary errors={form.errors} />

                        <FieldGroup>
                            <div className="grid gap-4 md:grid-cols-2">
                                <Field data-invalid={form.errors.country_code || undefined}>
                                    <FieldLabel htmlFor="country_code">Country Code</FieldLabel>
                                    <Input
                                        id="country_code"
                                        value={form.data.country_code}
                                        onChange={(event) =>
                                            form.setData('country_code', event.target.value)
                                        }
                                        placeholder="IN"
                                    />
                                    <FieldError>{form.errors.country_code}</FieldError>
                                </Field>

                                <Field data-invalid={form.errors.state_code || undefined}>
                                    <FieldLabel htmlFor="state_code">State Code</FieldLabel>
                                    <Input
                                        id="state_code"
                                        value={form.data.state_code}
                                        onChange={(event) =>
                                            form.setData('state_code', event.target.value)
                                        }
                                        placeholder="KA"
                                    />
                                    <FieldError>{form.errors.state_code}</FieldError>
                                </Field>
                            </div>

                            <div className="grid gap-4 md:grid-cols-2">
                                <Field data-invalid={form.errors.state || undefined}>
                                    <FieldLabel htmlFor="state">State</FieldLabel>
                                    <Input
                                        id="state"
                                        value={form.data.state}
                                        onChange={(event) =>
                                            form.setData('state', event.target.value)
                                        }
                                        placeholder="Karnataka"
                                    />
                                    <FieldError>{form.errors.state}</FieldError>
                                </Field>

                                <Field data-invalid={form.errors.city || undefined}>
                                    <FieldLabel htmlFor="city">City</FieldLabel>
                                    <Input
                                        id="city"
                                        value={form.data.city}
                                        onChange={(event) =>
                                            form.setData('city', event.target.value)
                                        }
                                        placeholder="Bengaluru"
                                    />
                                    <FieldError>{form.errors.city}</FieldError>
                                </Field>
                            </div>

                            <Field data-invalid={form.errors.vat_id || undefined}>
                                <FieldLabel htmlFor="vat_id">VAT / GST / Sales Tax ID</FieldLabel>
                                <Input
                                    id="vat_id"
                                    value={form.data.vat_id}
                                    onChange={(event) =>
                                        form.setData('vat_id', event.target.value)
                                    }
                                    placeholder="GB123456789"
                                />
                                <FieldError>{form.errors.vat_id}</FieldError>
                            </Field>

                            <Field data-invalid={form.errors.company_name || undefined}>
                                <FieldLabel htmlFor="company_name">Company Name</FieldLabel>
                                <Input
                                    id="company_name"
                                    value={form.data.company_name}
                                    onChange={(event) =>
                                        form.setData('company_name', event.target.value)
                                    }
                                />
                                <FieldError>{form.errors.company_name}</FieldError>
                            </Field>

                            <Field data-invalid={form.errors.address || undefined}>
                                <FieldLabel htmlFor="address">Address</FieldLabel>
                                <Textarea
                                    id="address"
                                    rows={4}
                                    value={form.data.address}
                                    onChange={(event) =>
                                        form.setData('address', event.target.value)
                                    }
                                />
                                <FieldError>{form.errors.address}</FieldError>
                            </Field>
                        </FieldGroup>

                        <div className="flex justify-end">
                            <Button type="submit" disabled={form.processing}>
                                Save Tax Details
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </AppLayout>
    );
}
