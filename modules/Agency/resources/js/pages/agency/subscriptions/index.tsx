import { Link } from '@inertiajs/react';
import { CreditCardIcon } from 'lucide-react';
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

type SubscriptionRow = {
    id: number;
    plan_name: string;
    formatted_price: string | null;
    billing_cycle_label: string;
    status: string;
    next_billing_date: string | null;
};

type PaginationData = {
    current_page: number;
    last_page: number;
};

type AgencySubscriptionsIndexPageProps = {
    subscriptions: SubscriptionRow[];
    pagination: PaginationData;
    statusCounts: {
        total: number;
        active: number;
        trialing: number;
        canceled: number;
    };
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Billing', href: route('agency.billing.index') },
    { title: 'Subscription', href: route('agency.billing.subscriptions.index') },
];

function statusVariant(status: string): 'success' | 'warning' | 'secondary' {
    switch (status) {
        case 'active':
            return 'success';
        case 'trialing':
            return 'warning';
        default:
            return 'secondary';
    }
}

function formatDate(value: string | null): string {
    if (!value) {
        return 'N/A';
    }

    return new Intl.DateTimeFormat('en', {
        month: 'short',
        day: '2-digit',
        year: 'numeric',
    }).format(new Date(value));
}

export default function AgencySubscriptionsIndex({
    subscriptions,
    pagination,
}: AgencySubscriptionsIndexPageProps) {
    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Subscription"
            description="Review active subscriptions and upcoming renewals."
            headerActions={
                <Button variant="outline" asChild>
                    <Link href={route('agency.billing.index')}>
                        Back to Billing
                    </Link>
                </Button>
            }
        >
            <div className="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Plans</CardTitle>
                        <CardDescription>
                            Every active purchase tied to your customer profile
                            appears here.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {subscriptions.length === 0 ? (
                            <div className="flex flex-col items-center justify-center rounded-xl border border-dashed py-12 text-center">
                                <CreditCardIcon className="mb-3 size-10 text-muted-foreground" />
                                <p className="font-medium">No subscriptions yet</p>
                                <p className="text-sm text-muted-foreground">
                                    Your active plans will show up here after checkout.
                                </p>
                            </div>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Plan</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Billing Cycle</TableHead>
                                        <TableHead>Next Billing</TableHead>
                                        <TableHead className="text-right">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {subscriptions.map((subscription) => (
                                        <TableRow key={subscription.id}>
                                            <TableCell>
                                                <div className="font-medium">
                                                    {subscription.plan_name}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    {subscription.formatted_price ?? 'N/A'}
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant={statusVariant(subscription.status)}>
                                                    {subscription.status}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>{subscription.billing_cycle_label}</TableCell>
                                            <TableCell>
                                                {formatDate(subscription.next_billing_date)}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <Button asChild size="sm" variant="outline">
                                                    <Link
                                                        href={route(
                                                            'agency.billing.subscriptions.show',
                                                            subscription.id,
                                                        )}
                                                    >
                                                        View
                                                    </Link>
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}

                        <div className="mt-4 flex items-center justify-between text-sm text-muted-foreground">
                            <span>
                                Page {pagination.current_page} of {pagination.last_page}
                            </span>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
