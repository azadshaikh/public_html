import { Link, router, usePage } from '@inertiajs/react';
import { ArrowLeftIcon, PencilIcon, RefreshCwIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { AuthenticatedSharedData, BreadcrumbItem } from '@/types';
import type { RefundShowPageProps } from '../../../types/billing';

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

export default function RefundsShow({ refund }: RefundShowPageProps) {
    const page = usePage<AuthenticatedSharedData>();
    const canEdit = page.props.auth.abilities.editRefunds;
    const canDelete = page.props.auth.abilities.deleteRefunds;
    const canRestore = page.props.auth.abilities.restoreRefunds;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Billing' },
        { title: 'Refunds', href: route('app.billing.refunds.index') },
        { title: refund.refund_number, href: route('app.billing.refunds.show', refund.id) },
    ];

    const handleRestore = () => {
        if (!window.confirm(`Restore refund "${refund.refund_number}"?`)) return;
        router.patch(route('app.billing.refunds.restore', refund.id), {}, { preserveScroll: true });
    };

    const handleDelete = () => {
        if (!window.confirm(`Move refund "${refund.refund_number}" to trash?`)) return;
        router.delete(route('app.billing.refunds.destroy', refund.id), { preserveScroll: true });
    };

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={refund.refund_number}
            description="Refund details"
            headerActions={
                <div className="flex items-center gap-2">
                    <Button variant="outline" asChild>
                        <Link href={route('app.billing.refunds.index')}>
                            <ArrowLeftIcon data-icon="inline-start" />
                            Back
                        </Link>
                    </Button>
                    {refund.is_trashed && canRestore && (
                        <Button variant="outline" onClick={handleRestore}>
                            <RefreshCwIcon data-icon="inline-start" />
                            Restore
                        </Button>
                    )}
                    {!refund.is_trashed && canEdit && (
                        <Button asChild>
                            <Link href={route('app.billing.refunds.edit', refund.id)}>
                                <PencilIcon data-icon="inline-start" />
                                Edit
                            </Link>
                        </Button>
                    )}
                </div>
            }
        >
            <div className="flex flex-col gap-6">
                {refund.is_trashed && (
                    <div className="rounded-lg border border-destructive/50 bg-destructive/10 p-4 text-sm text-destructive">
                        This refund is in the trash.
                    </div>
                )}

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="flex flex-col gap-6 lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>Refund Details</CardTitle>
                            </CardHeader>
                            <CardContent className="divide-y">
                                <DetailRow label="Refund Number" value={refund.refund_number} />
                                <DetailRow label="Reference" value={refund.reference} />
                                <DetailRow label="Customer" value={refund.customer_display} />
                                <DetailRow label="Payment" value={refund.payment_number} />
                                {refund.invoice_number && (
                                    <DetailRow
                                        label="Invoice"
                                        value={
                                            refund.invoice_id ? (
                                                <Link href={route('app.billing.invoices.show', refund.invoice_id)} className="text-sm text-foreground hover:underline">
                                                    {refund.invoice_number}
                                                </Link>
                                            ) : (
                                                refund.invoice_number
                                            )
                                        }
                                    />
                                )}
                                <DetailRow label="Amount" value={refund.formatted_amount} />
                                <DetailRow label="Currency" value={refund.currency} />
                                <DetailRow label="Refunded At" value={refund.refunded_at} />
                                <DetailRow label="Failed At" value={refund.failed_at} />
                            </CardContent>
                        </Card>

                        {refund.gateway_refund_id && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Gateway Info</CardTitle>
                                </CardHeader>
                                <CardContent className="divide-y">
                                    <DetailRow label="Gateway Refund ID" value={refund.gateway_refund_id} />
                                    <DetailRow label="Idempotency Key" value={refund.idempotency_key} />
                                </CardContent>
                            </Card>
                        )}

                        {(refund.reason || refund.notes) && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Notes</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    {refund.reason && <DetailRow label="Reason" value={refund.reason} />}
                                    {refund.notes && (
                                        <div>
                                            <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">Notes</span>
                                            <p className="mt-1 text-sm whitespace-pre-wrap">{refund.notes}</p>
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
                                    <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">Type</span>
                                    <div className="mt-1"><Badge variant={refund.type_badge as Parameters<typeof Badge>[0]['variant']}>{refund.type_label}</Badge></div>
                                </div>
                                <div>
                                    <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">Refund Status</span>
                                    <div className="mt-1"><Badge variant={refund.status_badge as Parameters<typeof Badge>[0]['variant']}>{refund.status_label}</Badge></div>
                                </div>
                                <DetailRow label="Created" value={refund.created_at_formatted} />
                                <DetailRow label="Updated" value={refund.updated_at_formatted} />
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
