import { Link } from '@inertiajs/react';
import { ArrowLeftIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import TaxForm from '../../../components/taxes/tax-form';
import type { TaxCreatePageProps } from '../../../types/billing';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Billing' },
    { title: 'Taxes', href: route('app.billing.taxes.index') },
    { title: 'Create', href: route('app.billing.taxes.create') },
];

export default function TaxesCreate(props: TaxCreatePageProps) {
    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Create Tax"
            description="Create a new tax rate"
            headerActions={
                <Button variant="outline" asChild>
                    <Link href={route('app.billing.taxes.index')}>
                        <ArrowLeftIcon data-icon="inline-start" />
                        Back
                    </Link>
                </Button>
            }
        >
            <TaxForm mode="create" {...props} />
        </AppLayout>
    );
}
