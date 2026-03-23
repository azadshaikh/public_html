import { Link } from '@inertiajs/react';
import { ArrowLeftIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import SubscriptionForm from '../../../components/subscriptions/subscription-form';
import type { SubscriptionCreatePageProps } from '../../../types/subscriptions';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Subscriptions', href: route('subscriptions.plans.index') },
    { title: 'Subscriptions', href: route('subscriptions.subscriptions.index') },
    { title: 'Create', href: route('subscriptions.subscriptions.create') },
];

export default function SubscriptionsCreate(props: SubscriptionCreatePageProps) {
    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Create Subscription"
            description="Create a new subscription"
            headerActions={
                <Button variant="outline" asChild>
                    <Link href={route('subscriptions.subscriptions.index')}>
                        <ArrowLeftIcon data-icon="inline-start" />
                        Back
                    </Link>
                </Button>
            }
        >
            <SubscriptionForm mode="create" {...props} />
        </AppLayout>
    );
}
