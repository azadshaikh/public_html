import { Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type WebsiteDetail = {
    id: number;
    name: string;
    domain: string;
    status: string;
    status_label: string;
    status_badge: string;
    type_label: string;
    plan: string | null;
    site_id: string | null;
    server_name: string | null;
    astero_version: string | null;
    expired_on: string | null;
    created_at: string | null;
    deleted_at: string | null;
};

type CustomerData = {
    name: string | null;
    email: string | null;
    company_name: string | null;
} | null;

type SubscriptionData = {
    plan_name: string;
    formatted_price: string | null;
    billing_cycle: string;
    status_label: string;
    status_class: string;
} | null;

type InvoiceData = {
    id: number;
    invoice_number: string;
    status: string;
    total: number;
    issue_date: string | null;
};

type PaymentData = {
    id: number;
    amount: number;
    status: string;
    created_at: string | null;
};

type AgencyAdminWebsiteShowPageProps = {
    website: WebsiteDetail;
    customer: CustomerData;
    subscription: SubscriptionData;
    invoices: InvoiceData[];
    payments: PaymentData[];
};

function formatDate(value: string | null): string {
    if (!value) {
        return 'N/A';
    }

    return new Intl.DateTimeFormat('en', {
        month: 'short',
        day: '2-digit',
        year: 'numeric',
    }).format(new Date(value));
}

function formatMoney(value: number): string {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
    }).format(value);
}

export default function AgencyAdminWebsiteShow({
    website,
    customer,
    subscription,
    invoices,
    payments,
}: AgencyAdminWebsiteShowPageProps) {
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
            title: website.name,
            href: route('agency.admin.websites.show', website.id),
        },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={website.name}
            description="Review ownership, lifecycle, and linked commercial records for this agency website."
            headerActions={
                <div className="flex flex-wrap gap-2">
                    <Button asChild variant="outline">
                        <Link href={route('agency.admin.websites.index', { status: 'all' })}>
                            Back
                        </Link>
                    </Button>
                    <Button asChild>
                        <Link href={route('agency.admin.websites.edit', website.id)}>
                            Edit
                        </Link>
                    </Button>
                </div>
            }
        >
            <div className="space-y-6">
                <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                    <Card>
                        <CardHeader>
                            <div className="flex items-start justify-between gap-4">
                                <div>
                                    <CardTitle>{website.domain}</CardTitle>
                                    <CardDescription>
                                        {website.type_label}
                                        {website.plan ? ` • ${website.plan}` : ''}
                                    </CardDescription>
                                </div>
                                <Badge variant="secondary">{website.status_label}</Badge>
                            </div>
                        </CardHeader>
                        <CardContent className="grid gap-4 md:grid-cols-2">
                            <div>
                                <p className="text-sm text-muted-foreground">Site ID</p>
                                <p className="font-medium">{website.site_id ?? 'N/A'}</p>
                            </div>
                            <div>
                                <p className="text-sm text-muted-foreground">Server</p>
                                <p className="font-medium">{website.server_name ?? 'N/A'}</p>
                            </div>
                            <div>
                                <p className="text-sm text-muted-foreground">Version</p>
                                <p className="font-medium">
                                    {website.astero_version ?? 'N/A'}
                                </p>
                            </div>
                            <div>
                                <p className="text-sm text-muted-foreground">Expires</p>
                                <p className="font-medium">{formatDate(website.expired_on)}</p>
                            </div>
                            <div>
                                <p className="text-sm text-muted-foreground">Created</p>
                                <p className="font-medium">{formatDate(website.created_at)}</p>
                            </div>
                            <div>
                                <p className="text-sm text-muted-foreground">Deleted</p>
                                <p className="font-medium">{formatDate(website.deleted_at)}</p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Owner</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <div>
                                <p className="text-sm text-muted-foreground">Name</p>
                                <p className="font-medium">{customer?.name ?? 'N/A'}</p>
                            </div>
                            <div>
                                <p className="text-sm text-muted-foreground">Email</p>
                                <p className="font-medium">{customer?.email ?? 'N/A'}</p>
                            </div>
                            <div>
                                <p className="text-sm text-muted-foreground">Company</p>
                                <p className="font-medium">
                                    {customer?.company_name ?? 'N/A'}
                                </p>
                            </div>
                            {subscription ? (
                                <div className="rounded-xl border p-4">
                                    <p className="text-sm text-muted-foreground">Subscription</p>
                                    <p className="font-medium">{subscription.plan_name}</p>
                                    <p className="text-sm text-muted-foreground">
                                        {subscription.formatted_price ?? 'N/A'} /{' '}
                                        {subscription.billing_cycle}
                                    </p>
                                    <Badge variant="outline" className="mt-2">
                                        {subscription.status_label}
                                    </Badge>
                                </div>
                            ) : null}
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-6 xl:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Invoices</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {invoices.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No invoices are linked to this site.
                                </p>
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Invoice</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead>Total</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {invoices.map((invoice) => (
                                            <TableRow key={invoice.id}>
                                                <TableCell>{invoice.invoice_number}</TableCell>
                                                <TableCell>{invoice.status}</TableCell>
                                                <TableCell>{formatMoney(invoice.total)}</TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Payments</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {payments.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No payments are linked to this site.
                                </p>
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Amount</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead>Date</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {payments.map((payment) => (
                                            <TableRow key={payment.id}>
                                                <TableCell>{formatMoney(payment.amount)}</TableCell>
                                                <TableCell>{payment.status}</TableCell>
                                                <TableCell>
                                                    {formatDate(payment.created_at)}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
