import { Link } from '@inertiajs/react';
import { ArrowLeftIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import TaxForm from '../../../components/taxes/tax-form';
import type { TaxEditPageProps } from '../../../types/billing';

export default function TaxesEdit({ tax, ...props }: TaxEditPageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Billing' },
        { title: 'Taxes', href: route('app.billing.taxes.index') },
        { title: tax.name, href: route('app.billing.taxes.show', tax.id) },
        { title: 'Edit', href: route('app.billing.taxes.edit', tax.id) },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={`Edit: ${tax.name}`}
            description="Update tax rate details"
            headerActions={
                <Button variant="outline" asChild>
                    <Link href={route('app.billing.taxes.show', tax.id)}>
                        <ArrowLeftIcon data-icon="inline-start" />
                        Back
                    </Link>
                </Button>
            }
        >
            <TaxForm mode="edit" tax={tax} {...props} />
        </AppLayout>
    );
}
