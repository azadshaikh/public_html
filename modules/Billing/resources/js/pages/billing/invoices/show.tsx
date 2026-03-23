import { Link, router, usePage } from '@inertiajs/react';
import { ArrowLeftIcon, PencilIcon, RefreshCwIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { AuthenticatedSharedData, BreadcrumbItem } from '@/types';
import type { InvoiceShowPageProps } from '../../../types/billing';

function DetailRow({ label, value, icon }: { label: string; value: ReactNode; icon?: ReactNode }) {
    if (value === null || value === undefined || value === '') return null;
    return (
        <div className="flex items-start gap-3 py-2">
            {icon && <span className="mt-0.5 text-muted-foreground">{icon}</span>}
            <div className="flex min-w-0 flex-col gap-0.5">
                <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">{label}</span>
                <span className="text-sm text-foreground">{value}</span>
            </div>
        </div>
    );
}

export default function InvoicesShow({ invoice }: InvoiceShowPageProps) {
    const page = usePage<AuthenticatedSharedData>();
    const canEdit = page.props.auth.abilities.editInvoices;
    const canDelete = page.props.auth.abilities.deleteInvoices;
    const canRestore = page.props.auth.abilities.restoreInvoices;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Billing' },
        { title: 'Invoices', href: route('app.billing.invoices.index') },
        { title: invoice.invoice_number, href: route('app.billing.invoices.show', invoice.id) },
    ];

    const handleRestore = () => {
        if (!window.confirm(`Restore invoice "${invoice.invoice_number}"?`)) return;
        router.patch(route('app.billing.invoices.restore', invoice.id), {}, { preserveScroll: true });
    };

    const handleDelete = () => {
        if (!window.confirm(`Move invoice "${invoice.invoice_number}" to trash?`)) return;
        router.delete(route('app.billing.invoices.destroy', invoice.id), { preserveScroll: true });
    };

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={invoice.invoice_number}
            description="Invoice details"
            headerActions={
                <div className="flex items-center gap-2">
                    <Button variant="outline" asChild>
                        <Link href={route('app.billing.invoices.index')}>
                            <ArrowLeftIcon data-icon="inline-start" />
                            Back
                        </Link>
                    </Button>
                    {invoice.is_trashed && canRestore && (
                        <Button variant="outline" onClick={handleRestore}>
                            <RefreshCwIcon data-icon="inline-start" />
                            Restore
                        </Button>
                    )}
                    {!invoice.is_trashed && canEdit && (
                        <Button asChild>
                            <Link href={route('app.billing.invoices.edit', invoice.id)}>
                                <PencilIcon data-icon="inline-start" />
                                Edit
                            </Link>
                        </Button>
                    )}
                </div>
            }
        >
            <div className="flex flex-col gap-6">
                {invoice.is_trashed && (
                    <div className="rounded-lg border border-destructive/50 bg-destructive/10 p-4 text-sm text-destructive">
                        This invoice is in the trash.
                    </div>
                )}

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="flex flex-col gap-6 lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>Invoice Details</CardTitle>
                            </CardHeader>
                            <CardContent className="divide-y">
                                <DetailRow label="Invoice Number" value={invoice.invoice_number} />
                                <DetailRow label="Reference" value={invoice.reference} />
                                <DetailRow label="Customer" value={invoice.customer_display} />
                                <DetailRow label="Billing Name" value={invoice.billing_name} />
                                <DetailRow label="Billing Email" value={invoice.billing_email} />
                                <DetailRow label="Billing Phone" value={invoice.billing_phone} />
                                <DetailRow label="Billing Address" value={invoice.billing_address} />
                                <DetailRow label="Issue Date" value={invoice.issue_date} />
                                <DetailRow label="Due Date" value={invoice.due_date} />
                                <DetailRow label="Paid At" value={invoice.paid_at} />
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Financial Summary</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-2 text-sm">
                                    <div className="flex justify-between"><span className="text-muted-foreground">Subtotal</span><span>{invoice.currency} {invoice.subtotal.toFixed(2)}</span></div>
                                    <div className="flex justify-between"><span className="text-muted-foreground">Tax</span><span>{invoice.currency} {invoice.tax_amount.toFixed(2)}</span></div>
                                    <div className="flex justify-between"><span className="text-muted-foreground">Discount</span><span>-{invoice.currency} {invoice.discount_amount.toFixed(2)}</span></div>
                                    <div className="flex justify-between border-t pt-2 font-semibold"><span>Total</span><span>{invoice.formatted_total}</span></div>
                                    <div className="flex justify-between"><span className="text-muted-foreground">Amount Paid</span><span>{invoice.currency} {invoice.amount_paid.toFixed(2)}</span></div>
                                    <div className="flex justify-between font-medium"><span>Amount Due</span><span>{invoice.formatted_amount_due}</span></div>
                                </div>
                            </CardContent>
                        </Card>

                        {invoice.items && invoice.items.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Line Items</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="overflow-x-auto">
                                        <table className="w-full text-sm">
                                            <thead>
                                                <tr className="border-b text-left text-muted-foreground">
                                                    <th className="pb-2 font-medium">Item</th>
                                                    <th className="pb-2 font-medium text-right">Qty</th>
                                                    <th className="pb-2 font-medium text-right">Unit Price</th>
                                                    <th className="pb-2 font-medium text-right">Tax %</th>
                                                    <th className="pb-2 font-medium text-right">Discount %</th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y">
                                                {invoice.items.map((item, idx) => (
                                                    <tr key={item.id ?? idx}>
                                                        <td className="py-2">
                                                            <div className="font-medium">{item.name}</div>
                                                            {item.description && <div className="text-xs text-muted-foreground">{item.description}</div>}
                                                        </td>
                                                        <td className="py-2 text-right">{item.quantity}</td>
                                                        <td className="py-2 text-right">{Number(item.unit_price).toFixed(2)}</td>
                                                        <td className="py-2 text-right">{Number(item.tax_rate).toFixed(2)}%</td>
                                                        <td className="py-2 text-right">{Number(item.discount_rate).toFixed(2)}%</td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        {(invoice.notes || invoice.terms) && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Notes & Terms</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    {invoice.notes && (
                                        <div>
                                            <h4 className="text-xs font-medium tracking-wide text-muted-foreground uppercase mb-1">Notes</h4>
                                            <p className="text-sm whitespace-pre-wrap">{invoice.notes}</p>
                                        </div>
                                    )}
                                    {invoice.terms && (
                                        <div>
                                            <h4 className="text-xs font-medium tracking-wide text-muted-foreground uppercase mb-1">Terms</h4>
                                            <p className="text-sm whitespace-pre-wrap">{invoice.terms}</p>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        )}
                    </div>

                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Status</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div>
                                    <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">Invoice Status</span>
                                    <div className="mt-1"><Badge variant={invoice.status_badge as Parameters<typeof Badge>[0]['variant']}>{invoice.status_label}</Badge></div>
                                </div>
                                <div>
                                    <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">Payment Status</span>
                                    <div className="mt-1"><Badge variant={invoice.payment_status_badge as Parameters<typeof Badge>[0]['variant']}>{invoice.payment_status_label}</Badge></div>
                                </div>
                                <DetailRow label="Currency" value={invoice.currency} />
                                <DetailRow label="Exchange Rate" value={invoice.exchange_rate !== 1 ? String(invoice.exchange_rate) : null} />
                                <DetailRow label="Created" value={invoice.created_at_formatted} />
                                <DetailRow label="Updated" value={invoice.updated_at_formatted} />
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
