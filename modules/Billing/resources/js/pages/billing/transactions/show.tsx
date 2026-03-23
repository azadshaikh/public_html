import { Link } from '@inertiajs/react';
import { ArrowLeftIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { TransactionShowPageProps } from '../../../types/billing';

function DetailRow({ label, value }: { label: string; value: ReactNode }) {
    if (value === null || value === undefined || value === '') return null;
    return (
        <div className="flex items-start gap-3 py-2">
            <div className="flex min-w-0 flex-col gap-0.5">
                <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">{label}</span>
                <span className="text-sm text-foreground">{value}</span>
            </div>
        </div>
    );
}

export default function TransactionsShow({ transaction }: TransactionShowPageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Billing' },
        { title: 'Transactions', href: route('app.billing.transactions.index') },
        { title: transaction.transaction_id, href: route('app.billing.transactions.show', transaction.id) },
    ];

    const hasGateway = transaction.payment_method_label || transaction.payment_gateway || transaction.gateway_transaction_id;

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={transaction.transaction_id}
            description="Transaction details"
            headerActions={
                <Button variant="outline" asChild>
                    <Link href={route('app.billing.transactions.index')}>
                        <ArrowLeftIcon data-icon="inline-start" />
                        Back
                    </Link>
                </Button>
            }
        >
            <div className="flex flex-col gap-6">
                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="flex flex-col gap-6 lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>Transaction Details</CardTitle>
                            </CardHeader>
                            <CardContent className="divide-y">
                                <DetailRow label="Transaction ID" value={transaction.transaction_id} />
                                <DetailRow label="Reference" value={transaction.reference} />
                                <DetailRow label="Customer" value={transaction.customer_display} />
                                <DetailRow label="Source" value={transaction.source_display} />
                                <DetailRow label="Amount" value={transaction.formatted_amount} />
                                <DetailRow label="Currency" value={transaction.currency} />
                                <DetailRow
                                    label="Type"
                                    value={<Badge variant={transaction.type_badge as Parameters<typeof Badge>[0]['variant']}>{transaction.type_label}</Badge>}
                                />
                                <DetailRow
                                    label="Status"
                                    value={<Badge variant={transaction.status_badge as Parameters<typeof Badge>[0]['variant']}>{transaction.status_label}</Badge>}
                                />
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Balance</CardTitle>
                            </CardHeader>
                            <CardContent className="divide-y">
                                <DetailRow label="Balance Before" value={`${transaction.balance_before} ${transaction.currency}`} />
                                <DetailRow label="Balance After" value={`${transaction.balance_after} ${transaction.currency}`} />
                            </CardContent>
                        </Card>

                        {hasGateway && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Gateway Info</CardTitle>
                                </CardHeader>
                                <CardContent className="divide-y">
                                    <DetailRow
                                        label="Payment Method"
                                        value={transaction.payment_method_label && <Badge variant={transaction.payment_method_badge as Parameters<typeof Badge>[0]['variant']}>{transaction.payment_method_label}</Badge>}
                                    />
                                    <DetailRow label="Payment Gateway" value={transaction.payment_gateway} />
                                    <DetailRow label="Gateway Transaction ID" value={transaction.gateway_transaction_id} />
                                </CardContent>
                            </Card>
                        )}

                        {transaction.description && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Description</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-sm whitespace-pre-wrap">{transaction.description}</p>
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
                                    <div className="mt-1"><Badge variant={transaction.type_badge as Parameters<typeof Badge>[0]['variant']}>{transaction.type_label}</Badge></div>
                                </div>
                                <div>
                                    <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">Status</span>
                                    <div className="mt-1"><Badge variant={transaction.status_badge as Parameters<typeof Badge>[0]['variant']}>{transaction.status_label}</Badge></div>
                                </div>
                                <DetailRow label="Created" value={transaction.created_at} />
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
