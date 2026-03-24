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
            <div className="mx-auto grid w-full max-w-5xl gap-6 lg:grid-cols-[minmax(0,1fr)_280px] lg:items-start">
                <Card className="w-full border-border/60 shadow-xs">
                    <CardHeader className="gap-2">
                        <CardTitle>Billing Identity</CardTitle>
                        <CardDescription>
                            Update the tax and billing details that appear on your invoices and receipts.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form className="space-y-6" onSubmit={handleSubmit}>
                            <FormErrorSummary errors={form.errors} minMessages={2} />

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
                                            size="xl"
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
                                            size="xl"
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
                                            size="xl"
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
                                            size="xl"
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
                                        size="xl"
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
                                        placeholder="Astero Digital"
                                        size="xl"
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
                                        className="min-h-28"
                                    />
                                    <FieldError>{form.errors.address}</FieldError>
                                </Field>
                            </FieldGroup>

                            <div className="flex justify-end">
                                <Button type="submit" size="xl" disabled={form.processing}>
                                    Save Tax Details
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card size="sm" className="border-border/60 bg-muted/20 shadow-xs">
                    <CardHeader className="gap-2">
                        <CardTitle>How It Is Used</CardTitle>
                        <CardDescription>
                            These details are used on invoices, receipts, and tax-facing billing records.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3 text-sm text-muted-foreground">
                        <p>Leave optional fields empty when they do not apply to your business.</p>
                        <p>Use standard country and state codes when available for cleaner invoice formatting.</p>
                        <p>Your tax ID and company name should match the details you use for billing compliance.</p>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
