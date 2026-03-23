import { Link } from '@inertiajs/react';
import { ArrowLeftIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import PlanForm from '../../../components/plans/plan-form';
import type { PlanCreatePageProps } from '../../../types/subscriptions';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Subscriptions', href: route('subscriptions.plans.index') },
    { title: 'Plans', href: route('subscriptions.plans.index') },
    { title: 'Create', href: route('subscriptions.plans.create') },
];

export default function PlansCreate(props: PlanCreatePageProps) {
    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Create Plan"
            description="Create a new subscription plan"
            headerActions={
                <Button variant="outline" asChild>
                    <Link href={route('subscriptions.plans.index')}>
                        <ArrowLeftIcon data-icon="inline-start" />
                        Back
                    </Link>
                </Button>
            }
        >
            <PlanForm mode="create" {...props} />
        </AppLayout>
    );
}
