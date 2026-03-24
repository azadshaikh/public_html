import { Link } from '@inertiajs/react';
import { FileTextIcon } from 'lucide-react';
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

type InvoiceRow = {
    id: number;
    invoice_number: string;
    issue_date: string | null;
    due_date: string | null;
    status: string;
    total: number;
    amount_due: number;
};

type PaginationData = {
    current_page: number;
    last_page: number;
};

type AgencyInvoicesIndexPageProps = {
    invoices: InvoiceRow[];
    pagination: PaginationData;
    statusCounts: {
        total: number;
        paid: number;
        pending: number;
        overdue: number;
    };
    balanceDue: number;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Billing', href: route('agency.billing.index') },
    { title: 'Billing History', href: route('agency.billing.invoices.index') },
];

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

function statusVariant(status: string): 'success' | 'warning' | 'danger' | 'secondary' {
    switch (status) {
        case 'paid':
            return 'success';
        case 'overdue':
            return 'danger';
        case 'pending':
        case 'sent':
            return 'warning';
        default:
            return 'secondary';
    }
}

export default function AgencyInvoicesIndex({
    invoices,
    pagination,
    balanceDue,
}: AgencyInvoicesIndexPageProps) {
    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Billing History"
            description="Review invoice history and track any unpaid balances."
            headerActions={
                <Button variant="outline" asChild>
                    <Link href={route('agency.billing.index')}>
                        Back to Billing
                    </Link>
                </Button>
            }
        >
            <div className="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Invoices</CardTitle>
                        <CardDescription>
                            Current balance due: {formatMoney(balanceDue)}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {invoices.length === 0 ? (
                            <div className="flex flex-col items-center justify-center rounded-xl border border-dashed py-12 text-center">
                                <FileTextIcon className="mb-3 size-10 text-muted-foreground" />
                                <p className="font-medium">No invoices yet</p>
                                <p className="text-sm text-muted-foreground">
                                    Generated invoices will show up here after checkout.
                                </p>
                            </div>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Invoice</TableHead>
                                        <TableHead>Issue Date</TableHead>
                                        <TableHead>Due Date</TableHead>
                                        <TableHead>Total</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="text-right">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {invoices.map((invoice) => (
                                        <TableRow key={invoice.id}>
                                            <TableCell className="font-medium">
                                                {invoice.invoice_number}
                                            </TableCell>
                                            <TableCell>{formatDate(invoice.issue_date)}</TableCell>
                                            <TableCell>{formatDate(invoice.due_date)}</TableCell>
                                            <TableCell>
                                                <div>{formatMoney(invoice.total)}</div>
                                                {invoice.amount_due > 0 ? (
                                                    <div className="text-xs text-muted-foreground">
                                                        Due: {formatMoney(invoice.amount_due)}
                                                    </div>
                                                ) : null}
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant={statusVariant(invoice.status)}>
                                                    {invoice.status}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <Button asChild size="sm" variant="outline">
                                                    <Link
                                                        href={route(
                                                            'agency.billing.invoices.show',
                                                            invoice.id,
                                                        )}
                                                    >
                                                        View
                                                    </Link>
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}

                        <div className="mt-4 flex items-center justify-between text-sm text-muted-foreground">
                            <span>
                                Page {pagination.current_page} of {pagination.last_page}
                            </span>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
