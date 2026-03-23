import { Link } from '@inertiajs/react';
import { ArrowLeftIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import SubscriptionForm from '../../../components/subscriptions/subscription-form';
import type { SubscriptionEditPageProps } from '../../../types/subscriptions';

export default function SubscriptionsEdit({ subscription, ...props }: SubscriptionEditPageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Subscriptions', href: route('subscriptions.plans.index') },
        { title: 'Subscriptions', href: route('subscriptions.subscriptions.index') },
        { title: subscription.name, href: route('subscriptions.subscriptions.show', subscription.id) },
        { title: 'Edit', href: route('subscriptions.subscriptions.edit', subscription.id) },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={`Edit: ${subscription.name}`}
            description="Update subscription details"
            headerActions={
                <Button variant="outline" asChild>
                    <Link href={route('subscriptions.subscriptions.show', subscription.id)}>
                        <ArrowLeftIcon data-icon="inline-start" />
                        Back
                    </Link>
                </Button>
            }
        >
            <SubscriptionForm mode="edit" subscription={subscription} {...props} />
        </AppLayout>
    );
}
