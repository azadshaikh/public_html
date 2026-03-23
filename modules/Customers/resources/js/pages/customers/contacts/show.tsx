import { Link, router, usePage } from '@inertiajs/react';
import {
    ArrowLeftIcon,
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
import type { CustomerContactShowDetail } from '../../../types/customers';

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

type CustomerContactShowPageProps = {
    contact: CustomerContactShowDetail;
};

export default function CustomerContactsShow({
    contact,
}: CustomerContactShowPageProps) {
    const page = usePage<AuthenticatedSharedData>();
    const canEdit = page.props.auth.abilities.editCustomerContacts;
    const canDelete = page.props.auth.abilities.deleteCustomerContacts;
    const canRestore = page.props.auth.abilities.restoreCustomerContacts;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Customers', href: route('app.customers.index') },
        { title: 'Contacts', href: route('app.customers.contacts.index') },
        {
            title: contact.full_name,
            href: route('app.customers.contacts.show', contact.id),
        },
    ];

    const handleRestore = () => {
        if (!window.confirm(`Restore "${contact.full_name}"?`)) return;
        router.patch(
            route('app.customers.contacts.restore', contact.id),
            {},
            { preserveScroll: true },
        );
    };

    const handleDelete = () => {
        if (!window.confirm(`Move "${contact.full_name}" to trash?`)) return;
        router.delete(
            route('app.customers.contacts.destroy', contact.id),
            { preserveScroll: true },
        );
    };

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={contact.full_name}
            description="Contact details"
            headerActions={
                <div className="flex items-center gap-2">
                    <Button variant="outline" asChild>
                        <Link href={route('app.customers.contacts.index')}>
                            <ArrowLeftIcon data-icon="inline-start" />
                            Back
                        </Link>
                    </Button>

                    {contact.is_trashed && canRestore && (
                        <Button variant="outline" onClick={handleRestore}>
                            <RefreshCwIcon data-icon="inline-start" />
                            Restore
                        </Button>
                    )}

                    {!contact.is_trashed && canEdit && (
                        <Button asChild>
                            <Link
                                href={route(
                                    'app.customers.contacts.edit',
                                    contact.id,
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
                {contact.is_trashed && (
                    <div className="rounded-lg border border-destructive/50 bg-destructive/10 p-4 text-sm text-destructive">
                        This contact is in the trash.
                        {contact.deleted_at &&
                            ` Deleted on ${contact.deleted_at}.`}
                    </div>
                )}

                <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_300px]">
                    <Card>
                        <CardHeader>
                            <CardTitle>Contact Details</CardTitle>
                        </CardHeader>
                        <CardContent className="divide-y">
                            <DetailRow
                                label="Name"
                                value={contact.full_name}
                                icon={<UserIcon className="size-4" />}
                            />
                            <DetailRow
                                label="Email"
                                value={contact.email}
                                icon={<MailIcon className="size-4" />}
                            />
                            <DetailRow
                                label="Phone"
                                value={contact.phone}
                                icon={<PhoneIcon className="size-4" />}
                            />
                            <DetailRow
                                label="Position"
                                value={contact.position}
                            />
                            <DetailRow
                                label="Customer"
                                value={
                                    contact.customer_id ? (
                                        <Link
                                            href={route(
                                                'app.customers.show',
                                                contact.customer_id,
                                            )}
                                            className="text-primary hover:underline"
                                        >
                                            {contact.customer_name}
                                        </Link>
                                    ) : (
                                        contact.customer_name
                                    )
                                }
                            />
                            <DetailRow
                                label="Created"
                                value={contact.created_at}
                            />
                            <DetailRow
                                label="Last Updated"
                                value={contact.updated_at}
                            />
                        </CardContent>
                    </Card>

                    <div className="flex flex-col gap-4">
                        <Card>
                            <CardHeader>
                                <CardTitle>Status</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <div>
                                    <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                        Status
                                    </span>
                                    <div className="mt-1">
                                        <Badge
                                            variant={
                                                contact.is_trashed
                                                    ? 'destructive'
                                                    : contact.status ===
                                                        'active'
                                                      ? 'success'
                                                      : 'secondary'
                                            }
                                        >
                                            {contact.is_trashed
                                                ? 'Trashed'
                                                : contact.status_label}
                                        </Badge>
                                    </div>
                                </div>
                                <div>
                                    <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                        Primary Contact
                                    </span>
                                    <div className="mt-1">
                                        <Badge
                                            variant={
                                                contact.is_primary
                                                    ? 'info'
                                                    : 'secondary'
                                            }
                                        >
                                            {contact.is_primary
                                                ? 'Yes'
                                                : 'No'}
                                        </Badge>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {!contact.is_trashed && canDelete && (
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
