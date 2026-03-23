import { Link, router, usePage } from '@inertiajs/react';
import { ArrowLeftIcon, BanIcon, PauseIcon, PencilIcon, PlayIcon, RefreshCwIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { AuthenticatedSharedData, BreadcrumbItem } from '@/types';
import type { SubscriptionShowPageProps } from '../../../types/subscriptions';

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

export default function SubscriptionsShow({ subscription }: SubscriptionShowPageProps) {
    const page = usePage<AuthenticatedSharedData>();
    const canEdit = page.props.auth.abilities.editSubscriptions;
    const canRestore = page.props.auth.abilities.restoreSubscriptions;
    const canCancel = page.props.auth.abilities.cancelSubscriptions;
    const canResume = page.props.auth.abilities.resumeSubscriptions;
    const canPause = page.props.auth.abilities.pauseSubscriptions;

    const sub = subscription;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Subscriptions', href: route('subscriptions.plans.index') },
        { title: 'Subscriptions', href: route('subscriptions.subscriptions.index') },
        { title: sub.unique_id, href: route('subscriptions.subscriptions.show', sub.id) },
    ];

    const handleCancel = (immediately: boolean) => {
        const msg = immediately
            ? `Cancel subscription "${sub.unique_id}" immediately? This cannot be undone.`
            : `Cancel subscription "${sub.unique_id}" at the end of the billing period?`;
        if (!window.confirm(msg)) return;
        router.post(route('subscriptions.subscriptions.cancel', sub.id), { immediately }, { preserveScroll: true });
    };

    const handleResume = () => {
        if (!window.confirm(`Resume subscription "${sub.unique_id}"?`)) return;
        router.post(route('subscriptions.subscriptions.resume', sub.id), {}, { preserveScroll: true });
    };

    const handlePause = () => {
        if (!window.confirm(`Pause subscription "${sub.unique_id}"?`)) return;
        router.post(route('subscriptions.subscriptions.pause', sub.id), {}, { preserveScroll: true });
    };

    const handleRestore = () => {
        if (!window.confirm(`Restore subscription "${sub.unique_id}"?`)) return;
        router.patch(route('subscriptions.subscriptions.restore', sub.id), {}, { preserveScroll: true });
    };

    const isActive = sub.status === 'active' || sub.status === 'trialing';
    const isCanceled = sub.status === 'canceled';
    const isPaused = sub.status === 'paused';

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={sub.unique_id}
            description="Subscription details"
            headerActions={
                <div className="flex flex-wrap items-center gap-2">
                    <Button variant="outline" asChild>
                        <Link href={route('subscriptions.subscriptions.index')}>
                            <ArrowLeftIcon data-icon="inline-start" />
                            Back
                        </Link>
                    </Button>
                    {sub.is_trashed && canRestore && (
                        <Button variant="outline" onClick={handleRestore}>
                            <RefreshCwIcon data-icon="inline-start" />
                            Restore
                        </Button>
                    )}
                    {!sub.is_trashed && (
                        <>
                            {isActive && canPause && (
                                <Button variant="outline" onClick={handlePause}>
                                    <PauseIcon data-icon="inline-start" />
                                    Pause
                                </Button>
                            )}
                            {isActive && canCancel && (
                                <Button variant="outline" onClick={() => handleCancel(false)}>
                                    <BanIcon data-icon="inline-start" />
                                    Cancel
                                </Button>
                            )}
                            {(isCanceled && sub.on_grace_period) && canResume && (
                                <Button variant="outline" onClick={handleResume}>
                                    <PlayIcon data-icon="inline-start" />
                                    Resume
                                </Button>
                            )}
                            {isPaused && canResume && (
                                <Button variant="outline" onClick={handleResume}>
                                    <PlayIcon data-icon="inline-start" />
                                    Resume
                                </Button>
                            )}
                            {canEdit && (
                                <Button asChild>
                                    <Link href={route('subscriptions.subscriptions.edit', sub.id)}>
                                        <PencilIcon data-icon="inline-start" />
                                        Edit
                                    </Link>
                                </Button>
                            )}
                        </>
                    )}
                </div>
            }
        >
            <div className="flex flex-col gap-6">
                {sub.is_trashed && (
                    <div className="rounded-lg border border-destructive/50 bg-destructive/10 p-4 text-sm text-destructive">
                        This subscription is in the trash.
                    </div>
                )}

                {sub.on_grace_period && sub.status === 'canceled' && (
                    <div className="rounded-lg border border-warning/50 bg-warning/10 p-4 text-sm text-warning-foreground">
                        This subscription has been cancelled and will expire at the end of the current billing period.
                    </div>
                )}

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="flex flex-col gap-6 lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>Subscription Details</CardTitle>
                            </CardHeader>
                            <CardContent className="divide-y">
                                <DetailRow label="Subscription ID" value={sub.unique_id} />
                                <DetailRow
                                    label="Customer"
                                    value={
                                        sub.subscriber_url ? (
                                            <Link href={sub.subscriber_url} className="font-medium hover:underline">
                                                {sub.subscriber_name}
                                            </Link>
                                        ) : (
                                            sub.subscriber_name
                                        )
                                    }
                                />
                                <DetailRow label="Plan" value={sub.plan_name} />
                                <DetailRow label="Billing Cycle" value={sub.billing_cycle} />
                                <DetailRow label="Price" value={sub.formatted_price} />
                                <DetailRow label="Currency" value={sub.currency} />
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Billing Period</CardTitle>
                            </CardHeader>
                            <CardContent className="divide-y">
                                <DetailRow label="Current Period Start" value={sub.current_period_start_formatted} />
                                <DetailRow label="Current Period End" value={sub.current_period_end_formatted} />
                                <DetailRow label="Trial Ends" value={sub.trial_ends_at_formatted} />
                                <DetailRow label="Cancelled At" value={sub.canceled_at_formatted} />
                                <DetailRow label="Cancels At" value={sub.cancels_at_formatted} />
                                <DetailRow label="Paused At" value={sub.paused_at ? new Date(sub.paused_at).toLocaleDateString() : null} />
                                <DetailRow label="Resumes At" value={sub.resumes_at ? new Date(sub.resumes_at).toLocaleDateString() : null} />
                                <DetailRow label="Ended At" value={sub.ended_at ? new Date(sub.ended_at).toLocaleDateString() : null} />
                            </CardContent>
                        </Card>
                    </div>

                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Status</CardTitle>
                            </CardHeader>
                            <CardContent className="divide-y">
                                <DetailRow
                                    label="Status"
                                    value={
                                        <Badge variant={sub.status_badge as Parameters<typeof Badge>[0]['variant']}>
                                            {sub.status_label}
                                        </Badge>
                                    }
                                />
                                {sub.on_trial && <DetailRow label="On Trial" value={<Badge variant="info">Yes</Badge>} />}
                                {sub.on_grace_period && <DetailRow label="Grace Period" value={<Badge variant="warning">Active</Badge>} />}
                                {sub.cancel_at_period_end && <DetailRow label="Cancels at Period End" value="Yes" />}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Timestamps</CardTitle>
                            </CardHeader>
                            <CardContent className="divide-y">
                                <DetailRow label="Created" value={sub.created_at_formatted} />
                                <DetailRow label="Updated" value={sub.updated_at_formatted} />
                                <DetailRow label="Plan Changed" value={sub.plan_changed_at ? new Date(sub.plan_changed_at).toLocaleDateString() : null} />
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
