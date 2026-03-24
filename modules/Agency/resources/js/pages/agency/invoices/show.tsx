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

type InvoiceItem = {
    id: number;
    description: string;
    quantity: number;
    unit_price: number;
    total: number;
};

type InvoiceDetail = {
    id: number;
    invoice_number: string;
    issue_date: string | null;
    due_date: string | null;
    status: string;
    subtotal: number;
    tax: number;
    discount: number;
    total: number;
    amount_due: number;
    items: InvoiceItem[];
};

type AgencyInvoiceShowPageProps = {
    invoice: InvoiceDetail;
};

function formatDate(value: string | null): string {
    if (!value) {
        return 'N/A';
    }

    return new Intl.DateTimeFormat('en', {
        month: 'long',
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

export default function AgencyInvoiceShow({
    invoice,
}: AgencyInvoiceShowPageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Billing', href: route('agency.billing.index') },
        { title: 'Billing History', href: route('agency.billing.invoices.index') },
        {
            title: invoice.invoice_number,
            href: route('agency.billing.invoices.show', invoice.id),
        },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={`Invoice #${invoice.invoice_number}`}
            description="Review issued charges, taxes, and outstanding balance."
            headerActions={
                <Button asChild variant="outline">
                    <Link href={route('agency.billing.invoices.index')}>
                        Back to Billing History
                    </Link>
                </Button>
            }
        >
            <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
                <div className="space-y-6">
                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between gap-3">
                                <div>
                                    <CardTitle>Invoice Details</CardTitle>
                                    <CardDescription>
                                        Issued {formatDate(invoice.issue_date)}
                                    </CardDescription>
                                </div>
                                <Badge variant="secondary">{invoice.status}</Badge>
                            </div>
                        </CardHeader>
                        <CardContent className="grid gap-4 md:grid-cols-2">
                            <div>
                                <p className="text-sm text-muted-foreground">Due Date</p>
                                <p className="font-medium">{formatDate(invoice.due_date)}</p>
                            </div>
                            <div>
                                <p className="text-sm text-muted-foreground">Subtotal</p>
                                <p className="font-medium">{formatMoney(invoice.subtotal)}</p>
                            </div>
                            <div>
                                <p className="text-sm text-muted-foreground">Tax</p>
                                <p className="font-medium">{formatMoney(invoice.tax)}</p>
                            </div>
                            <div>
                                <p className="text-sm text-muted-foreground">Discount</p>
                                <p className="font-medium">{formatMoney(invoice.discount)}</p>
                            </div>
                            <div>
                                <p className="text-sm text-muted-foreground">Total</p>
                                <p className="font-medium">{formatMoney(invoice.total)}</p>
                            </div>
                            <div>
                                <p className="text-sm text-muted-foreground">Amount Due</p>
                                <p className="font-medium">{formatMoney(invoice.amount_due)}</p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Line Items</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Description</TableHead>
                                        <TableHead>Qty</TableHead>
                                        <TableHead>Unit Price</TableHead>
                                        <TableHead className="text-right">Amount</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {invoice.items.map((item) => (
                                        <TableRow key={item.id}>
                                            <TableCell>{item.description}</TableCell>
                                            <TableCell>{item.quantity}</TableCell>
                                            <TableCell>{formatMoney(item.unit_price)}</TableCell>
                                            <TableCell className="text-right">
                                                {formatMoney(item.total)}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Actions</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <Button className="w-full" disabled={invoice.amount_due <= 0}>
                            Pay Now
                        </Button>
                        <Button asChild variant="outline" className="w-full">
                            <Link href={route('agency.tickets.create')}>
                                Get Support
                            </Link>
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
