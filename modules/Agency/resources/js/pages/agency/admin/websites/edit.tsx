import { Link } from '@inertiajs/react';
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

type Option = {
    value: string;
    label: string;
};

type AgencyWebsiteEditPageProps = {
    agencyWebsite: {
        id: number;
        name: string;
        domain: string | null;
        type: string | null;
        plan: string | null;
        expired_on: string | null;
        customer_ref: string | null;
        customer_data?: {
            name?: string | null;
            email?: string | null;
            company?: string | null;
            phone?: string | null;
        } | null;
        plan_ref: string | null;
        site_id: string | null;
        server_name: string | null;
        astero_version: string | null;
        status_label?: string | null;
        owner_name?: string | null;
        owner_email?: string | null;
    };
    typeOptions: Option[];
    statusOptions: Option[];
};

type AgencyWebsiteFormValues = {
    name: string;
    type: string;
    plan: string;
    expired_on: string;
    customer_ref: string;
    customer_name: string;
    customer_email: string;
    customer_company: string;
    customer_phone: string;
    plan_ref: string;
};

export default function AgencyAdminWebsitesEdit({
    agencyWebsite,
    typeOptions,
    statusOptions,
}: AgencyWebsiteEditPageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        {
            title: 'Agency',
            href: route('agency.admin.websites.index', { status: 'all' }),
        },
        {
            title: 'Websites',
            href: route('agency.admin.websites.index', { status: 'all' }),
        },
        {
            title: agencyWebsite.name,
            href: route('agency.admin.websites.show', agencyWebsite.id),
        },
        {
            title: 'Edit',
            href: route('agency.admin.websites.edit', agencyWebsite.id),
        },
    ];

    const form = useAppForm<AgencyWebsiteFormValues>({
        defaults: {
            name: agencyWebsite.name ?? '',
            type: agencyWebsite.type ?? typeOptions[0]?.value ?? 'paid',
            plan: agencyWebsite.plan ?? '',
            expired_on: agencyWebsite.expired_on
                ? agencyWebsite.expired_on.slice(0, 10)
                : '',
            customer_ref: agencyWebsite.customer_ref ?? '',
            customer_name: agencyWebsite.customer_data?.name ?? '',
            customer_email: agencyWebsite.customer_data?.email ?? '',
            customer_company: agencyWebsite.customer_data?.company ?? '',
            customer_phone: agencyWebsite.customer_data?.phone ?? '',
            plan_ref: agencyWebsite.plan_ref ?? '',
        },
        rememberKey: `agency.admin.websites.edit.${agencyWebsite.id}`,
        dirtyGuard: { enabled: true },
        rules: {
            name: [formValidators.required('Website name')],
            type: [formValidators.required('Website type')],
        },
    });

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit(
            'put',
            route('agency.admin.websites.update', agencyWebsite.id),
            {
                preserveScroll: true,
                setDefaultsOnSuccess: true,
                successToast: {
                    title: 'Website updated',
                    description:
                        'Agency website details were updated successfully.',
                },
            },
        );
    };

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={`Edit ${agencyWebsite.name}`}
            description="Update customer-facing website metadata without touching the underlying platform assignment."
        >
            <form className="flex flex-col gap-6" onSubmit={handleSubmit} noValidate>
                {form.dirtyGuardDialog}
                <FormErrorSummary errors={form.errors} minMessages={2} />

                <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
                    <div className="flex flex-col gap-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Website Details</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <FieldGroup>
                                    <Field data-invalid={form.invalid('name') || undefined}>
                                        <FieldLabel htmlFor="name">Website Name</FieldLabel>
                                        <Input
                                            id="name"
                                            value={form.data.name}
                                            onChange={(event) =>
                                                form.setField('name', event.target.value)
                                            }
                                            onBlur={() => form.touch('name')}
                                            placeholder="My Awesome Website"
                                        />
                                        <FieldError>{form.error('name')}</FieldError>
                                    </Field>

                                    <Field>
                                        <FieldLabel htmlFor="domain">Domain</FieldLabel>
                                        <Input
                                            id="domain"
                                            value={agencyWebsite.domain ?? ''}
                                            disabled
                                            readOnly
                                        />
                                        <FieldError>
                                            Domain changes are managed from the platform layer.
                                        </FieldError>
                                    </Field>

                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <Field data-invalid={form.invalid('type') || undefined}>
                                            <FieldLabel htmlFor="type">Website Type</FieldLabel>
                                            <NativeSelect
                                                id="type"
                                                value={form.data.type}
                                                onChange={(event) =>
                                                    form.setField('type', event.target.value)
                                                }
                                            >
                                                {typeOptions.map((option) => (
                                                    <NativeSelectOption
                                                        key={option.value}
                                                        value={option.value}
                                                    >
                                                        {option.label}
                                                    </NativeSelectOption>
                                                ))}
                                            </NativeSelect>
                                            <FieldError>{form.error('type')}</FieldError>
                                        </Field>

                                        <Field>
                                            <FieldLabel htmlFor="plan">Plan</FieldLabel>
                                            <Input
                                                id="plan"
                                                value={form.data.plan}
                                                onChange={(event) =>
                                                    form.setField('plan', event.target.value)
                                                }
                                                placeholder="starter, pro, enterprise"
                                            />
                                            <FieldError>{form.error('plan')}</FieldError>
                                        </Field>
                                    </div>

                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <Field>
                                            <FieldLabel htmlFor="expired_on">Expiry Date</FieldLabel>
                                            <Input
                                                id="expired_on"
                                                type="date"
                                                value={form.data.expired_on}
                                                onChange={(event) =>
                                                    form.setField('expired_on', event.target.value)
                                                }
                                            />
                                            <FieldError>{form.error('expired_on')}</FieldError>
                                        </Field>

                                        <Field>
                                            <FieldLabel htmlFor="plan_ref">Plan Reference</FieldLabel>
                                            <Input
                                                id="plan_ref"
                                                value={form.data.plan_ref}
                                                onChange={(event) =>
                                                    form.setField('plan_ref', event.target.value)
                                                }
                                                placeholder="External SKU or plan ID"
                                            />
                                            <FieldError>{form.error('plan_ref')}</FieldError>
                                        </Field>
                                    </div>
                                </FieldGroup>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Customer Information</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <FieldGroup>
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <Field>
                                            <FieldLabel htmlFor="customer_ref">Customer Reference</FieldLabel>
                                            <Input
                                                id="customer_ref"
                                                value={form.data.customer_ref}
                                                onChange={(event) =>
                                                    form.setField('customer_ref', event.target.value)
                                                }
                                                placeholder="External customer ID"
                                            />
                                            <FieldError>{form.error('customer_ref')}</FieldError>
                                        </Field>

                                        <Field>
                                            <FieldLabel htmlFor="customer_name">Customer Name</FieldLabel>
                                            <Input
                                                id="customer_name"
                                                value={form.data.customer_name}
                                                onChange={(event) =>
                                                    form.setField('customer_name', event.target.value)
                                                }
                                                placeholder="Full name"
                                            />
                                            <FieldError>{form.error('customer_name')}</FieldError>
                                        </Field>
                                    </div>

                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <Field>
                                            <FieldLabel htmlFor="customer_email">Customer Email</FieldLabel>
                                            <Input
                                                id="customer_email"
                                                type="email"
                                                value={form.data.customer_email}
                                                onChange={(event) =>
                                                    form.setField('customer_email', event.target.value)
                                                }
                                                placeholder="customer@example.com"
                                            />
                                            <FieldError>{form.error('customer_email')}</FieldError>
                                        </Field>

                                        <Field>
                                            <FieldLabel htmlFor="customer_phone">Customer Phone</FieldLabel>
                                            <Input
                                                id="customer_phone"
                                                value={form.data.customer_phone}
                                                onChange={(event) =>
                                                    form.setField('customer_phone', event.target.value)
                                                }
                                                placeholder="+1 555 123 4567"
                                            />
                                            <FieldError>{form.error('customer_phone')}</FieldError>
                                        </Field>
                                    </div>

                                    <Field>
                                        <FieldLabel htmlFor="customer_company">Company</FieldLabel>
                                        <Input
                                            id="customer_company"
                                            value={form.data.customer_company}
                                            onChange={(event) =>
                                                form.setField('customer_company', event.target.value)
                                            }
                                            placeholder="Company name"
                                        />
                                        <FieldError>{form.error('customer_company')}</FieldError>
                                    </Field>
                                </FieldGroup>
                            </CardContent>
                        </Card>
                    </div>

                    <div className="flex flex-col gap-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Infrastructure</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3 text-sm">
                                <div className="flex items-center justify-between gap-3">
                                    <span className="text-muted-foreground">Site ID</span>
                                    <span className="font-mono">{agencyWebsite.site_id ?? 'N/A'}</span>
                                </div>
                                <div className="flex items-center justify-between gap-3">
                                    <span className="text-muted-foreground">Server</span>
                                    <span>{agencyWebsite.server_name ?? 'N/A'}</span>
                                </div>
                                <div className="flex items-center justify-between gap-3">
                                    <span className="text-muted-foreground">Version</span>
                                    <span>{agencyWebsite.astero_version ?? 'N/A'}</span>
                                </div>
                                <div className="flex items-center justify-between gap-3">
                                    <span className="text-muted-foreground">Status</span>
                                    <span>{agencyWebsite.status_label ?? statusOptions[0]?.label ?? 'N/A'}</span>
                                </div>
                                <div className="flex items-center justify-between gap-3">
                                    <span className="text-muted-foreground">Owner</span>
                                    <span>
                                        {agencyWebsite.owner_name ??
                                            agencyWebsite.owner_email ??
                                            'N/A'}
                                    </span>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Actions</CardTitle>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-3">
                                <Button type="submit" disabled={form.processing}>
                                    {form.processing && <Spinner className="mr-2 size-4" />}
                                    Save Changes
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link href={route('agency.admin.websites.show', agencyWebsite.id)}>
                                        Cancel
                                    </Link>
                                </Button>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </form>
        </AppLayout>
    );
}
