import { Link, router, usePage } from '@inertiajs/react';
import { ArrowLeftIcon, PencilIcon, RefreshCwIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { AuthenticatedSharedData, BreadcrumbItem } from '@/types';
import type { PaymentShowPageProps } from '../../../types/billing';

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

export default function PaymentsShow({ payment }: PaymentShowPageProps) {
    const page = usePage<AuthenticatedSharedData>();
    const canEdit = page.props.auth.abilities.editPayments;
    const canDelete = page.props.auth.abilities.deletePayments;
    const canRestore = page.props.auth.abilities.restorePayments;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Billing' },
        { title: 'Payments', href: route('app.billing.payments.index') },
        { title: payment.payment_number, href: route('app.billing.payments.show', payment.id) },
    ];

    const handleRestore = () => {
        if (!window.confirm(`Restore payment "${payment.payment_number}"?`)) return;
        router.patch(route('app.billing.payments.restore', payment.id), {}, { preserveScroll: true });
    };

    const handleDelete = () => {
        if (!window.confirm(`Move payment "${payment.payment_number}" to trash?`)) return;
        router.delete(route('app.billing.payments.destroy', payment.id), { preserveScroll: true });
    };

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={payment.payment_number}
            description="Payment details"
            headerActions={
                <div className="flex items-center gap-2">
                    <Button variant="outline" asChild>
                        <Link href={route('app.billing.payments.index')}>
                            <ArrowLeftIcon data-icon="inline-start" />
                            Back
                        </Link>
                    </Button>
                    {payment.is_trashed && canRestore && (
                        <Button variant="outline" onClick={handleRestore}>
                            <RefreshCwIcon data-icon="inline-start" />
                            Restore
                        </Button>
                    )}
                    {!payment.is_trashed && canEdit && (
                        <Button asChild>
                            <Link href={route('app.billing.payments.edit', payment.id)}>
                                <PencilIcon data-icon="inline-start" />
                                Edit
                            </Link>
                        </Button>
                    )}
                </div>
            }
        >
            <div className="flex flex-col gap-6">
                {payment.is_trashed && (
                    <div className="rounded-lg border border-destructive/50 bg-destructive/10 p-4 text-sm text-destructive">
                        This payment is in the trash.
                    </div>
                )}

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="flex flex-col gap-6 lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>Payment Details</CardTitle>
                            </CardHeader>
                            <CardContent className="divide-y">
                                <DetailRow label="Payment Number" value={payment.payment_number} />
                                <DetailRow label="Reference" value={payment.reference} />
                                <DetailRow
                                    label="Invoice"
                                    value={
                                        payment.invoice_id ? (
                                            <Link href={route('app.billing.invoices.show', payment.invoice_id)} className="text-sm text-foreground hover:underline">
                                                {payment.invoice_number}
                                            </Link>
                                        ) : (
                                            payment.invoice_number
                                        )
                                    }
                                />
                                <DetailRow label="Customer" value={payment.customer_display} />
                                <DetailRow label="Amount" value={payment.formatted_amount} />
                                <DetailRow label="Currency" value={payment.currency} />
                                <DetailRow label="Exchange Rate" value={payment.exchange_rate !== 1 ? String(payment.exchange_rate) : null} />
                                <DetailRow label="Paid At" value={payment.paid_at} />
                                <DetailRow label="Failed At" value={payment.failed_at} />
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Gateway Info</CardTitle>
                            </CardHeader>
                            <CardContent className="divide-y">
                                <DetailRow
                                    label="Payment Method"
                                    value={payment.payment_method_label && <Badge variant={payment.payment_method_badge as Parameters<typeof Badge>[0]['variant']}>{payment.payment_method_label}</Badge>}
                                />
                                <DetailRow label="Payment Gateway" value={payment.payment_gateway} />
                                <DetailRow label="Gateway Transaction ID" value={payment.gateway_transaction_id} />
                                <DetailRow label="Idempotency Key" value={payment.idempotency_key} />
                            </CardContent>
                        </Card>

                        {payment.notes && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Notes</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-sm whitespace-pre-wrap">{payment.notes}</p>
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
                                    <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">Payment Status</span>
                                    <div className="mt-1"><Badge variant={payment.status_badge as Parameters<typeof Badge>[0]['variant']}>{payment.status_label}</Badge></div>
                                </div>
                                <DetailRow label="Created" value={payment.created_at_formatted} />
                                <DetailRow label="Updated" value={payment.updated_at_formatted} />
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
