import { Link } from '@inertiajs/react';
import { ArrowLeftIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import CreditForm from '../../../components/credits/credit-form';
import type { CreditCreatePageProps } from '../../../types/billing';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Billing' },
    { title: 'Credits', href: route('app.billing.credits.index') },
    { title: 'Create', href: route('app.billing.credits.create') },
];

export default function CreditsCreate(props: CreditCreatePageProps) {
    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Create Credit"
            description="Create a new billing credit"
            headerActions={
                <Button variant="outline" asChild>
                    <Link href={route('app.billing.credits.index')}>
                        <ArrowLeftIcon data-icon="inline-start" />
                        Back
                    </Link>
                </Button>
            }
        >
            <CreditForm mode="create" {...props} />
        </AppLayout>
    );
}
