import { Link } from '@inertiajs/react';
import { ArrowLeftIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import PlanForm from '../../../components/plans/plan-form';
import type { PlanEditPageProps } from '../../../types/subscriptions';

export default function PlansEdit({ plan, ...props }: PlanEditPageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Subscriptions', href: route('subscriptions.plans.index') },
        { title: 'Plans', href: route('subscriptions.plans.index') },
        { title: plan.name, href: route('subscriptions.plans.show', plan.id) },
        { title: 'Edit', href: route('subscriptions.plans.edit', plan.id) },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={`Edit: ${plan.name}`}
            description="Update plan details"
            headerActions={
                <Button variant="outline" asChild>
                    <Link href={route('subscriptions.plans.show', plan.id)}>
                        <ArrowLeftIcon data-icon="inline-start" />
                        Back
                    </Link>
                </Button>
            }
        >
            <PlanForm mode="edit" plan={plan} {...props} />
        </AppLayout>
    );
}
