import { Link, router, usePage } from '@inertiajs/react';
import { ArrowLeftIcon, PencilIcon, RefreshCwIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { AuthenticatedSharedData, BreadcrumbItem } from '@/types';
import type { PlanShowPageProps } from '../../../types/subscriptions';

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

export default function PlansShow({ plan }: PlanShowPageProps) {
    const page = usePage<AuthenticatedSharedData>();
    const canEdit = page.props.auth.abilities.editPlans;
    const canDelete = page.props.auth.abilities.deletePlans;
    const canRestore = page.props.auth.abilities.restorePlans;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Subscriptions', href: route('subscriptions.plans.index') },
        { title: 'Plans', href: route('subscriptions.plans.index') },
        { title: plan.name, href: route('subscriptions.plans.show', plan.id) },
    ];

    const handleRestore = () => {
        if (!window.confirm(`Restore plan "${plan.name}"?`)) return;
        router.patch(route('subscriptions.plans.restore', plan.id), {}, { preserveScroll: true });
    };

    const handleDelete = () => {
        if (!window.confirm(`Move plan "${plan.name}" to trash?`)) return;
        router.delete(route('subscriptions.plans.destroy', plan.id), { preserveScroll: true });
    };

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={plan.name}
            description="Plan details"
            headerActions={
                <div className="flex items-center gap-2">
                    <Button variant="outline" asChild>
                        <Link href={route('subscriptions.plans.index')}>
                            <ArrowLeftIcon data-icon="inline-start" />
                            Back
                        </Link>
                    </Button>
                    {plan.is_trashed && canRestore && (
                        <Button variant="outline" onClick={handleRestore}>
                            <RefreshCwIcon data-icon="inline-start" />
                            Restore
                        </Button>
                    )}
                    {!plan.is_trashed && canEdit && (
                        <Button asChild>
                            <Link href={route('subscriptions.plans.edit', plan.id)}>
                                <PencilIcon data-icon="inline-start" />
                                Edit
                            </Link>
                        </Button>
                    )}
                    {!plan.is_trashed && canDelete && (
                        <Button variant="destructive" onClick={handleDelete}>
                            Move to Trash
                        </Button>
                    )}
                </div>
            }
        >
            <div className="flex flex-col gap-6">
                {plan.is_trashed && (
                    <div className="rounded-lg border border-destructive/50 bg-destructive/10 p-4 text-sm text-destructive">
                        This plan is in the trash.
                    </div>
                )}

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="flex flex-col gap-6 lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>Plan Details</CardTitle>
                            </CardHeader>
                            <CardContent className="divide-y">
                                <DetailRow label="Code" value={plan.code} />
                                <DetailRow label="Name" value={plan.name} />
                                <DetailRow label="Description" value={plan.description} />
                            </CardContent>
                        </Card>

                        {/* Pricing Tiers */}
                        {plan.prices && plan.prices.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Pricing</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="overflow-x-auto">
                                        <table className="w-full text-sm">
                                            <thead>
                                                <tr className="border-b text-left text-muted-foreground">
                                                    <th className="pb-2 font-medium">Billing Cycle</th>
                                                    <th className="pb-2 font-medium">Price</th>
                                                    <th className="pb-2 font-medium">Currency</th>
                                                    <th className="pb-2 font-medium">Status</th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y">
                                                {plan.prices.map((price) => (
                                                    <tr key={price.id}>
                                                        <td className="py-2">{price.billing_cycle_label}</td>
                                                        <td className="py-2">{price.formatted_price}</td>
                                                        <td className="py-2">{price.currency}</td>
                                                        <td className="py-2">
                                                            <Badge variant={price.is_active ? 'success' : 'warning'}>
                                                                {price.is_active ? 'Active' : 'Inactive'}
                                                            </Badge>
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        {/* Features */}
                        {plan.features && plan.features.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Features</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="overflow-x-auto">
                                        <table className="w-full text-sm">
                                            <thead>
                                                <tr className="border-b text-left text-muted-foreground">
                                                    <th className="pb-2 font-medium">Feature</th>
                                                    <th className="pb-2 font-medium">Code</th>
                                                    <th className="pb-2 font-medium">Type</th>
                                                    <th className="pb-2 font-medium">Value</th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y">
                                                {plan.features.map((feature) => (
                                                    <tr key={feature.id}>
                                                        <td className="py-2 font-medium">{feature.name}</td>
                                                        <td className="py-2">
                                                            <code className="rounded bg-muted px-1.5 py-0.5 text-xs">{feature.code}</code>
                                                        </td>
                                                        <td className="py-2 capitalize">{feature.type}</td>
                                                        <td className="py-2">
                                                            {feature.type === 'boolean' ? (feature.value === '1' || feature.value === 'true' ? '✓' : '✗')
                                                                : feature.type === 'unlimited' ? '∞'
                                                                : feature.value ?? '—'}
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                </CardContent>
                            </Card>
                        )}
                    </div>

                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Status & Settings</CardTitle>
                            </CardHeader>
                            <CardContent className="divide-y">
                                <DetailRow
                                    label="Status"
                                    value={
                                        <Badge variant={plan.status_badge as Parameters<typeof Badge>[0]['variant']}>
                                            {plan.is_active ? 'Active' : 'Inactive'}
                                        </Badge>
                                    }
                                />
                                <DetailRow label="Trial Days" value={plan.trial_days} />
                                <DetailRow label="Grace Days" value={plan.grace_days} />
                                <DetailRow label="Sort Order" value={plan.sort_order} />
                                <DetailRow
                                    label="Popular"
                                    value={
                                        plan.is_popular ? (
                                            <Badge variant="info">Yes</Badge>
                                        ) : (
                                            'No'
                                        )
                                    }
                                />
                                <DetailRow label="Subscribers" value={plan.subscriptions_count} />
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
