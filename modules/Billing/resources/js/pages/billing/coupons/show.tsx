import { Link, router, usePage } from '@inertiajs/react';
import { ArrowLeftIcon, PencilIcon, RefreshCwIcon, Trash2Icon } from 'lucide-react';
import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { AuthenticatedSharedData, BreadcrumbItem } from '@/types';
import type { CouponShowPageProps } from '../../../types/billing';

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

export default function CouponsShow({ coupon }: CouponShowPageProps) {
    const page = usePage<AuthenticatedSharedData>();
    const canEdit = page.props.auth.abilities.editCoupons;
    const canDelete = page.props.auth.abilities.deleteCoupons;
    const canRestore = page.props.auth.abilities.restoreCoupons;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Billing', href: route('app.billing.coupons.index') },
        { title: 'Coupons', href: route('app.billing.coupons.index') },
        { title: coupon.name, href: route('app.billing.coupons.show', coupon.id) },
    ];

    const handleRestore = () => {
        if (!window.confirm(`Restore coupon "${coupon.code}"?`)) return;
        router.patch(route('app.billing.coupons.restore', coupon.id), {}, { preserveScroll: true });
    };

    const handleDelete = () => {
        if (!window.confirm(`Move coupon "${coupon.code}" to trash?`)) return;
        router.delete(route('app.billing.coupons.destroy', coupon.id), { preserveScroll: true });
    };

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={coupon.name}
            description="Coupon details"
            headerActions={
                <div className="flex items-center gap-2">
                    <Button variant="outline" asChild>
                        <Link href={route('app.billing.coupons.index')}>
                            <ArrowLeftIcon data-icon="inline-start" />
                            Back
                        </Link>
                    </Button>
                    {coupon.is_trashed && canRestore && (
                        <Button variant="outline" onClick={handleRestore}>
                            <RefreshCwIcon data-icon="inline-start" />
                            Restore
                        </Button>
                    )}
                    {!coupon.is_trashed && canEdit && (
                        <Button asChild>
                            <Link href={route('app.billing.coupons.edit', coupon.id)}>
                                <PencilIcon data-icon="inline-start" />
                                Edit
                            </Link>
                        </Button>
                    )}
                    {!coupon.is_trashed && canDelete && (
                        <Button variant="destructive" onClick={handleDelete}>
                            <Trash2Icon data-icon="inline-start" />
                            Move to Trash
                        </Button>
                    )}
                </div>
            }
        >
            <div className="flex flex-col gap-6">
                {coupon.is_trashed && (
                    <div className="rounded-lg border border-destructive/50 bg-destructive/10 p-4 text-sm text-destructive">
                        This coupon is in the trash.
                    </div>
                )}

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="flex flex-col gap-6 lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>Coupon Details</CardTitle>
                            </CardHeader>
                            <CardContent className="divide-y">
                                <DetailRow label="Code" value={coupon.code} />
                                <DetailRow label="Name" value={coupon.name} />
                                <DetailRow label="Description" value={coupon.description} />
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Discount</CardTitle>
                            </CardHeader>
                            <CardContent className="divide-y">
                                <DetailRow
                                    label="Type"
                                    value={
                                        <Badge variant={coupon.type_badge as Parameters<typeof Badge>[0]['variant']}>{coupon.type_label}</Badge>
                                    }
                                />
                                <DetailRow label="Value" value={coupon.value_display} />
                                {coupon.currency && <DetailRow label="Currency" value={coupon.currency} />}
                                <DetailRow
                                    label="Duration"
                                    value={
                                        <Badge variant={coupon.discount_duration_badge as Parameters<typeof Badge>[0]['variant']}>{coupon.discount_duration_label}</Badge>
                                    }
                                />
                                {coupon.duration_in_months != null && coupon.duration_in_months > 0 && (
                                    <DetailRow label="Duration (Months)" value={coupon.duration_in_months} />
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Usage Limits</CardTitle>
                            </CardHeader>
                            <CardContent className="divide-y">
                                <DetailRow label="Max Uses" value={coupon.max_uses ?? 'Unlimited'} />
                                <DetailRow label="Current Uses" value={coupon.uses_count} />
                                <DetailRow label="Max Uses Per Customer" value={coupon.max_uses_per_customer} />
                                {coupon.min_order_amount != null && coupon.min_order_amount > 0 && (
                                    <DetailRow label="Min Order Amount" value={coupon.min_order_amount} />
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Status</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div>
                                    <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">Active</span>
                                    <div className="mt-1"><Badge variant={coupon.is_active_badge as Parameters<typeof Badge>[0]['variant']}>{coupon.is_active_label}</Badge></div>
                                </div>
                                <DetailRow label="Expires" value={coupon.expires_at_display} />
                                <DetailRow label="Created" value={coupon.created_at_formatted} />
                                <DetailRow label="Updated" value={coupon.updated_at_formatted} />
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
