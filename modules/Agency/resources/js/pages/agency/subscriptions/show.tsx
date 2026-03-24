import { Link } from '@inertiajs/react';
import { CheckIcon } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Feature = {
    id: number;
    name: string;
    value: string | null;
};

type SubscriptionDetail = {
    id: number;
    plan_name: string;
    formatted_price: string | null;
    billing_cycle_label: string;
    status: string;
    started_at: string | null;
    next_billing_date: string | null;
    trial_ends_at: string | null;
    canceled_at: string | null;
    features: Feature[];
};

type AgencySubscriptionShowPageProps = {
    subscription: SubscriptionDetail;
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

export default function AgencySubscriptionShow({
    subscription,
}: AgencySubscriptionShowPageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Billing', href: route('agency.billing.index') },
        {
            title: 'Subscription',
            href: route('agency.billing.subscriptions.index'),
        },
        {
            title: subscription.plan_name,
            href: route('agency.billing.subscriptions.show', subscription.id),
        },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Subscription Details"
            description="Inspect billing milestones and included plan features."
            headerActions={
                <Button asChild variant="outline">
                    <Link href={route('agency.billing.subscriptions.index')}>
                        Back to Subscriptions
                    </Link>
                </Button>
            }
        >
            <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between gap-3">
                            <div>
                                <CardTitle>{subscription.plan_name}</CardTitle>
                                <CardDescription>
                                    {subscription.formatted_price ?? 'N/A'} /{' '}
                                    {subscription.billing_cycle_label}
                                </CardDescription>
                            </div>
                            <Badge variant="secondary">{subscription.status}</Badge>
                        </div>
                    </CardHeader>
                    <CardContent className="grid gap-4 md:grid-cols-2">
                        <div>
                            <p className="text-sm text-muted-foreground">Started</p>
                            <p className="font-medium">
                                {formatDate(subscription.started_at)}
                            </p>
                        </div>
                        <div>
                            <p className="text-sm text-muted-foreground">Next Billing</p>
                            <p className="font-medium">
                                {formatDate(subscription.next_billing_date)}
                            </p>
                        </div>
                        {subscription.trial_ends_at ? (
                            <div>
                                <p className="text-sm text-muted-foreground">Trial Ends</p>
                                <p className="font-medium">
                                    {formatDate(subscription.trial_ends_at)}
                                </p>
                            </div>
                        ) : null}
                        {subscription.canceled_at ? (
                            <div>
                                <p className="text-sm text-muted-foreground">Canceled</p>
                                <p className="font-medium">
                                    {formatDate(subscription.canceled_at)}
                                </p>
                            </div>
                        ) : null}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Included Features</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {subscription.features.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No feature metadata is attached to this plan.
                            </p>
                        ) : (
                            <div className="space-y-3">
                                {subscription.features.map((feature) => (
                                    <div key={feature.id} className="flex gap-3">
                                        <CheckIcon className="mt-0.5 size-4 text-success" />
                                        <div>
                                            <p className="font-medium">{feature.name}</p>
                                            {feature.value ? (
                                                <p className="text-sm text-muted-foreground">
                                                    {feature.value}
                                                </p>
                                            ) : null}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
