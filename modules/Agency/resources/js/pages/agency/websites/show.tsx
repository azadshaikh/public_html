import { Link } from '@inertiajs/react';
import { ExternalLinkIcon, GlobeIcon } from 'lucide-react';
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

type WebsiteData = {
    id: number;
    name: string;
    domain: string;
    site_url: string;
    admin_url: string | null;
    status: string;
    status_label: string;
    status_badge: string;
    plan: string | null;
    type: string;
    type_label: string;
    astero_version: string | null;
    created_at: string | null;
    provisioned_at: string | null;
};

type SubscriptionData = {
    plan_name: string;
    formatted_price: string | null;
    billing_cycle: string;
    status_label: string;
    status_class: string;
    trial_ends_at: string | null;
    current_period_start: string | null;
    current_period_end: string | null;
    created_at: string | null;
    canceled_at: string | null;
    cancels_at: string | null;
    on_grace_period: boolean;
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

type AgencyWebsiteShowPageProps = {
    website: WebsiteData;
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

export default function AgencyWebsiteShow({
    website,
    subscription,
    invoices,
    payments,
}: AgencyWebsiteShowPageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Websites', href: route('agency.websites.index') },
        { title: website.name, href: route('agency.websites.show', website.id) },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={website.name}
            description="Review website status, billing context, and quick access links."
            headerActions={
                <div className="flex flex-wrap gap-2">
                    <Button asChild variant="outline">
                        <Link href={route('agency.websites.index')}>Back</Link>
                    </Button>
                    <Button asChild>
                        <a href={website.site_url} target="_blank" rel="noreferrer">
                            <ExternalLinkIcon data-icon="inline-start" />
                            Visit Website
                        </a>
                    </Button>
                </div>
            }
        >
            <div className="space-y-6">
                <Card>
                    <CardHeader>
                        <div className="flex items-start justify-between gap-4">
                            <div className="flex items-center gap-4">
                                <div className="flex size-14 items-center justify-center rounded-2xl bg-muted">
                                    <GlobeIcon className="size-6" />
                                </div>
                                <div>
                                    <CardTitle>{website.domain}</CardTitle>
                                    <CardDescription>
                                        {website.type_label}
                                        {website.plan ? ` • ${website.plan}` : ''}
                                    </CardDescription>
                                </div>
                            </div>
                            <Badge variant="secondary">{website.status_label}</Badge>
                        </div>
                    </CardHeader>
                    <CardContent className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <div>
                            <p className="text-sm text-muted-foreground">Created</p>
                            <p className="font-medium">{formatDate(website.created_at)}</p>
                        </div>
                        <div>
                            <p className="text-sm text-muted-foreground">Provisioned</p>
                            <p className="font-medium">
                                {formatDate(website.provisioned_at)}
                            </p>
                        </div>
                        <div>
                            <p className="text-sm text-muted-foreground">Version</p>
                            <p className="font-medium">
                                {website.astero_version ?? 'N/A'}
                            </p>
                        </div>
                        <div>
                            <p className="text-sm text-muted-foreground">Admin Panel</p>
                            {website.admin_url ? (
                                <a
                                    href={website.admin_url}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="font-medium text-primary"
                                >
                                    Open Admin
                                </a>
                            ) : (
                                <p className="font-medium">Unavailable</p>
                            )}
                        </div>
                    </CardContent>
                </Card>

                <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                    <Card>
                        <CardHeader>
                            <CardTitle>Subscription</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {subscription ? (
                                <div className="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <p className="text-sm text-muted-foreground">Plan</p>
                                        <p className="font-medium">{subscription.plan_name}</p>
                                    </div>
                                    <div>
                                        <p className="text-sm text-muted-foreground">Price</p>
                                        <p className="font-medium">
                                            {subscription.formatted_price ?? 'N/A'} /{' '}
                                            {subscription.billing_cycle}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-sm text-muted-foreground">Status</p>
                                        <p className="font-medium">
                                            {subscription.status_label}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-sm text-muted-foreground">
                                            Current Period
                                        </p>
                                        <p className="font-medium">
                                            {formatDate(subscription.current_period_start)} -{' '}
                                            {formatDate(subscription.current_period_end)}
                                        </p>
                                    </div>
                                </div>
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    No subscription metadata is linked to this website yet.
                                </p>
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
                                    No payments have been recorded for this site.
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

                <Card>
                    <CardHeader>
                        <CardTitle>Invoices</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {invoices.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No invoices are linked to this website.
                            </p>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Invoice</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Total</TableHead>
                                        <TableHead>Date</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {invoices.map((invoice) => (
                                        <TableRow key={invoice.id}>
                                            <TableCell>{invoice.invoice_number}</TableCell>
                                            <TableCell>{invoice.status}</TableCell>
                                            <TableCell>{formatMoney(invoice.total)}</TableCell>
                                            <TableCell>{formatDate(invoice.issue_date)}</TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
