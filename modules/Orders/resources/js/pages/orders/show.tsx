import { Link, router, usePage } from '@inertiajs/react';
import { ArrowLeftIcon, RefreshCwIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { AuthenticatedSharedData, BreadcrumbItem } from '@/types';
import type { OrderShowPageProps } from '../../types/orders';

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

export default function OrdersShow({ order }: OrderShowPageProps) {
    const page = usePage<AuthenticatedSharedData>();
    const canDelete = page.props.auth.abilities.deleteOrders;
    const canRestore = page.props.auth.abilities.restoreOrders;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Orders', href: route('app.orders.index') },
        { title: order.order_number, href: route('app.orders.show', order.id) },
    ];

    const handleRestore = () => {
        if (!window.confirm(`Restore order "${order.order_number}"?`)) return;
        router.patch(route('app.orders.restore', order.id), {}, { preserveScroll: true });
    };

    const handleDelete = () => {
        if (!window.confirm(`Move order "${order.order_number}" to trash?`)) return;
        router.delete(route('app.orders.destroy', order.id), { preserveScroll: true });
    };

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={order.order_number}
            description="Order details"
            headerActions={
                <div className="flex items-center gap-2">
                    <Button variant="outline" asChild>
                        <Link href={route('app.orders.index')}>
                            <ArrowLeftIcon data-icon="inline-start" />
                            Back
                        </Link>
                    </Button>
                    {order.is_trashed && canRestore && (
                        <Button variant="outline" onClick={handleRestore}>
                            <RefreshCwIcon data-icon="inline-start" />
                            Restore
                        </Button>
                    )}
                    {!order.is_trashed && canDelete && (
                        <Button variant="destructive" onClick={handleDelete}>
                            Move to Trash
                        </Button>
                    )}
                </div>
            }
        >
            <div className="flex flex-col gap-6">
                {order.is_trashed && (
                    <div className="rounded-lg border border-destructive/50 bg-destructive/10 p-4 text-sm text-destructive">
                        This order is in the trash.
                    </div>
                )}

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="flex flex-col gap-6 lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>Order Details</CardTitle>
                            </CardHeader>
                            <CardContent className="divide-y">
                                <DetailRow label="Order Number" value={order.order_number} />
                                <DetailRow label="Customer" value={order.customer_display} />
                                <DetailRow
                                    label="Type"
                                    value={
                                        <Badge variant={order.type_badge as Parameters<typeof Badge>[0]['variant']}>
                                            {order.type_label}
                                        </Badge>
                                    }
                                />
                                <DetailRow
                                    label="Status"
                                    value={
                                        <Badge variant={order.status_badge as Parameters<typeof Badge>[0]['variant']}>
                                            {order.status_label}
                                        </Badge>
                                    }
                                />
                                {order.coupon_code && <DetailRow label="Coupon Code" value={order.coupon_code} />}
                                <DetailRow label="Paid At" value={order.paid_at_formatted} />
                                <DetailRow label="Created" value={order.created_at_formatted} />
                            </CardContent>
                        </Card>

                        {order.items && order.items.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Order Items</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="overflow-x-auto">
                                        <table className="w-full text-sm">
                                            <thead>
                                                <tr className="border-b text-left text-muted-foreground">
                                                    <th className="pb-2 font-medium">Item</th>
                                                    <th className="pb-2 font-medium text-right">Qty</th>
                                                    <th className="pb-2 font-medium text-right">Unit Price</th>
                                                    <th className="pb-2 font-medium text-right">Total</th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y">
                                                {order.items.map((item) => (
                                                    <tr key={item.id}>
                                                        <td className="py-2">
                                                            <div className="font-medium">{item.name}</div>
                                                            {item.description && (
                                                                <div className="text-xs text-muted-foreground">{item.description}</div>
                                                            )}
                                                        </td>
                                                        <td className="py-2 text-right">{item.quantity}</td>
                                                        <td className="py-2 text-right">{Number(item.unit_price).toFixed(2)}</td>
                                                        <td className="py-2 text-right">{Number(item.total).toFixed(2)}</td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        {order.notes && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Notes</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-sm whitespace-pre-wrap">{order.notes}</p>
                                </CardContent>
                            </Card>
                        )}
                    </div>

                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Financial Summary</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-2 text-sm">
                                    <div className="flex justify-between">
                                        <span className="text-muted-foreground">Subtotal</span>
                                        <span>{order.subtotal_display}</span>
                                    </div>
                                    {Number(order.discount_amount) > 0 && (
                                        <div className="flex justify-between">
                                            <span className="text-muted-foreground">Discount</span>
                                            <span>-{order.discount_display}</span>
                                        </div>
                                    )}
                                    {Number(order.tax_amount) > 0 && (
                                        <div className="flex justify-between">
                                            <span className="text-muted-foreground">Tax</span>
                                            <span>{order.tax_display}</span>
                                        </div>
                                    )}
                                    <div className="flex justify-between border-t pt-2 font-semibold">
                                        <span>Total</span>
                                        <span>{order.total_display}</span>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Details</CardTitle>
                            </CardHeader>
                            <CardContent className="text-sm space-y-2">
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">Currency</span>
                                    <span className="uppercase">{order.currency}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">Order ID</span>
                                    <span>#{order.id}</span>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
