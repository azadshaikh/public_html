import { Link } from '@inertiajs/react';
import { ArrowLeftIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import CreditForm from '../../../components/credits/credit-form';
import type { CreditEditPageProps } from '../../../types/billing';

export default function CreditsEdit({ credit, ...props }: CreditEditPageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Billing' },
        { title: 'Credits', href: route('app.billing.credits.index') },
        { title: credit.name, href: route('app.billing.credits.show', credit.id) },
        { title: 'Edit', href: route('app.billing.credits.edit', credit.id) },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={`Edit: ${credit.name}`}
            description="Update credit details"
            headerActions={
                <Button variant="outline" asChild>
                    <Link href={route('app.billing.credits.show', credit.id)}>
                        <ArrowLeftIcon data-icon="inline-start" />
                        Back
                    </Link>
                </Button>
            }
        >
            <CreditForm mode="edit" credit={credit} {...props} />
        </AppLayout>
    );
}
