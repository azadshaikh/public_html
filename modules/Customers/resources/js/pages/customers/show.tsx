import { Link, router, usePage } from '@inertiajs/react';
import {
    ArrowLeftIcon,
    BuildingIcon,
    GlobeIcon,
    MailIcon,
    PencilIcon,
    PhoneIcon,
    RefreshCwIcon,
    UserIcon,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { AuthenticatedSharedData, BreadcrumbItem } from '@/types';
import type { CustomerShowPageProps } from '../../types/customers';

function DetailRow({
    label,
    value,
    icon,
}: {
    label: string;
    value: ReactNode;
    icon?: ReactNode;
}) {
    if (value === null || value === undefined || value === '') return null;

    return (
        <div className="flex items-start gap-3 py-2">
            {icon && (
                <span className="mt-0.5 text-muted-foreground">{icon}</span>
            )}
            <div className="flex min-w-0 flex-col gap-0.5">
                <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    {label}
                </span>
                <span className="text-sm text-foreground">{value}</span>
            </div>
        </div>
    );
}

export default function CustomersShow({
    customer,
    activities,
}: CustomerShowPageProps) {
    const page = usePage<AuthenticatedSharedData>();
    const canEdit = page.props.auth.abilities.editCustomers;
    const canDelete = page.props.auth.abilities.deleteCustomers;
    const canRestore = page.props.auth.abilities.restoreCustomers;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Customers', href: route('app.customers.index') },
        {
            title: customer.company_name_display,
            href: route('app.customers.show', customer.id),
        },
    ];

    const handleRestore = () => {
        if (
            !window.confirm(
                `Restore "${customer.company_name_display}"?`,
            )
        )
            return;
        router.patch(
            route('app.customers.restore', customer.id),
            {},
            { preserveScroll: true },
        );
    };

    const handleDelete = () => {
        if (
            !window.confirm(
                `Move "${customer.company_name_display}" to trash?`,
            )
        )
            return;
        router.delete(route('app.customers.destroy', customer.id), {
            preserveScroll: true,
        });
    };

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={customer.company_name_display}
            description="Customer details"
            headerActions={
                <div className="flex items-center gap-2">
                    <Button variant="outline" asChild>
                        <Link href={route('app.customers.index')}>
                            <ArrowLeftIcon data-icon="inline-start" />
                            Back
                        </Link>
                    </Button>

                    {customer.is_trashed && canRestore && (
                        <Button variant="outline" onClick={handleRestore}>
                            <RefreshCwIcon data-icon="inline-start" />
                            Restore
                        </Button>
                    )}

                    {!customer.is_trashed && canEdit && (
                        <Button asChild>
                            <Link
                                href={route(
                                    'app.customers.edit',
                                    customer.id,
                                )}
                            >
                                <PencilIcon data-icon="inline-start" />
                                Edit
                            </Link>
                        </Button>
                    )}
                </div>
            }
        >
            <div className="flex flex-col gap-6">
                {customer.is_trashed && (
                    <div className="rounded-lg border border-destructive/50 bg-destructive/10 p-4 text-sm text-destructive">
                        This customer is in the trash.
                        {customer.deleted_at &&
                            ` Deleted on ${customer.deleted_at}.`}
                    </div>
                )}

                <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_300px]">
                    {/* Main content */}
                    <div className="flex flex-col gap-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Customer Details</CardTitle>
                            </CardHeader>
                            <CardContent className="divide-y">
                                <DetailRow
                                    label="Name"
                                    value={customer.company_name_display}
                                    icon={
                                        <BuildingIcon className="size-4" />
                                    }
                                />
                                <DetailRow
                                    label="Email"
                                    value={customer.email}
                                    icon={<MailIcon className="size-4" />}
                                />
                                <DetailRow
                                    label="Phone"
                                    value={customer.phone}
                                    icon={
                                        <PhoneIcon className="size-4" />
                                    }
                                />
                                <DetailRow
                                    label="Website"
                                    value={
                                        customer.website ? (
                                            <a
                                                href={customer.website}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="text-primary hover:underline"
                                            >
                                                {customer.website}
                                            </a>
                                        ) : null
                                    }
                                    icon={
                                        <GlobeIcon className="size-4" />
                                    }
                                />
                                <DetailRow
                                    label="Description"
                                    value={customer.description || '—'}
                                />
                                <DetailRow
                                    label="Tax ID"
                                    value={customer.tax_id}
                                />
                                <DetailRow
                                    label="Industry"
                                    value={customer.industry_name}
                                />
                                <DetailRow
                                    label="Organization Size"
                                    value={customer.org_size_label}
                                />
                                <DetailRow
                                    label="Annual Revenue"
                                    value={customer.revenue_label}
                                />
                                <DetailRow
                                    label="Account Manager"
                                    value={customer.account_manager_name}
                                />
                            </CardContent>
                        </Card>

                        {/* Contacts */}
                        {customer.contacts.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Contacts</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="divide-y">
                                        {customer.contacts.map((contact) => (
                                            <div
                                                key={contact.id}
                                                className="flex items-center gap-3 py-3 first:pt-0 last:pb-0"
                                            >
                                                <div className="flex size-8 items-center justify-center rounded-full bg-muted text-muted-foreground">
                                                    <UserIcon className="size-4" />
                                                </div>
                                                <div className="min-w-0 flex-1">
                                                    <div className="flex items-center gap-2">
                                                        <span className="font-medium text-sm">
                                                            {contact.full_name}
                                                        </span>
                                                        {contact.is_primary && (
                                                            <Badge variant="info">
                                                                Primary
                                                            </Badge>
                                                        )}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        {contact.email}
                                                        {contact.position &&
                                                            ` · ${contact.position}`}
                                                    </div>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        {/* Addresses */}
                        {customer.addresses.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Addresses</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        {customer.addresses.map((addr) => (
                                            <div
                                                key={addr.id}
                                                className="rounded-lg border p-3"
                                            >
                                                <div className="mb-1 flex items-center gap-2">
                                                    <span className="text-xs font-medium uppercase text-muted-foreground">
                                                        {addr.type}
                                                    </span>
                                                    {addr.is_primary && (
                                                        <Badge variant="info">
                                                            Primary
                                                        </Badge>
                                                    )}
                                                </div>
                                                <div className="text-sm">
                                                    {[
                                                        addr.address1,
                                                        addr.address2,
                                                        [
                                                            addr.city,
                                                            addr.state,
                                                            addr.zip,
                                                        ]
                                                            .filter(
                                                                Boolean,
                                                            )
                                                            .join(', '),
                                                        addr.country,
                                                    ]
                                                        .filter(Boolean)
                                                        .map((line, i) => (
                                                            <div key={i}>
                                                                {line}
                                                            </div>
                                                        ))}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        {/* Activity Log */}
                        {activities && activities.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Activity</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="divide-y">
                                        {activities.map((activity) => (
                                            <div
                                                key={activity.id}
                                                className="flex items-start gap-3 py-3 first:pt-0 last:pb-0"
                                            >
                                                <div className="min-w-0 flex-1">
                                                    <p className="text-sm">
                                                        {activity.description}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {activity.causer_name}
                                                        {activity.created_at &&
                                                            ` · ${activity.created_at}`}
                                                    </p>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>
                        )}
                    </div>

                    {/* Sidebar */}
                    <div className="flex flex-col gap-4">
                        <Card>
                            <CardHeader>
                                <CardTitle>Classification</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <div>
                                    <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                        Type
                                    </span>
                                    <div className="mt-1">
                                        <Badge variant="outline">
                                            {customer.type}
                                        </Badge>
                                    </div>
                                </div>
                                <div>
                                    <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                        Status
                                    </span>
                                    <div className="mt-1">
                                        <Badge
                                            variant={
                                                customer.is_trashed
                                                    ? 'destructive'
                                                    : customer.status ===
                                                        'active'
                                                      ? 'success'
                                                      : 'secondary'
                                            }
                                        >
                                            {customer.is_trashed
                                                ? 'Trashed'
                                                : customer.status_label}
                                        </Badge>
                                    </div>
                                </div>
                                {customer.tier_label && (
                                    <div>
                                        <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                            Tier
                                        </span>
                                        <div className="mt-1 text-sm">
                                            {customer.tier_label}
                                        </div>
                                    </div>
                                )}
                                {customer.source_label && (
                                    <div>
                                        <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                            Source
                                        </span>
                                        <div className="mt-1 text-sm">
                                            {customer.source_label}
                                        </div>
                                    </div>
                                )}
                                {customer.customer_group_label && (
                                    <div>
                                        <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                            Group
                                        </span>
                                        <div className="mt-1 text-sm">
                                            {customer.customer_group_label}
                                        </div>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Preferences</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-2 text-sm">
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">
                                        Marketing opt-in
                                    </span>
                                    <span>
                                        {customer.opt_in_marketing
                                            ? 'Yes'
                                            : 'No'}
                                    </span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">
                                        Do not call
                                    </span>
                                    <span>
                                        {customer.do_not_call ? 'Yes' : 'No'}
                                    </span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">
                                        Do not email
                                    </span>
                                    <span>
                                        {customer.do_not_email ? 'Yes' : 'No'}
                                    </span>
                                </div>
                            </CardContent>
                        </Card>

                        {customer.user && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Linked Account</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-1 text-sm">
                                    <div className="font-medium">
                                        {customer.user.name}
                                    </div>
                                    <div className="text-muted-foreground">
                                        {customer.user.email}
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        <Card>
                            <CardHeader>
                                <CardTitle>Dates</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-2 text-sm">
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">
                                        Created
                                    </span>
                                    <span>
                                        {customer.created_at || '—'}
                                    </span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">
                                        Updated
                                    </span>
                                    <span>
                                        {customer.updated_at || '—'}
                                    </span>
                                </div>
                                {customer.last_contacted_at && (
                                    <div className="flex justify-between">
                                        <span className="text-muted-foreground">
                                            Last contacted
                                        </span>
                                        <span>
                                            {customer.last_contacted_at}
                                        </span>
                                    </div>
                                )}
                                {customer.next_action_date && (
                                    <div className="flex justify-between">
                                        <span className="text-muted-foreground">
                                            Next action
                                        </span>
                                        <span>
                                            {customer.next_action_date}
                                        </span>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {!customer.is_trashed && canDelete && (
                            <Card>
                                <CardContent className="pt-6">
                                    <Button
                                        variant="destructive"
                                        className="w-full"
                                        onClick={handleDelete}
                                    >
                                        Move to Trash
                                    </Button>
                                </CardContent>
                            </Card>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
