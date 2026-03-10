import { Head } from '@inertiajs/react';
import AppearanceTabs from '@/components/appearance-tabs';
import Heading from '@/components/heading';
import AppLayout from '@/layouts/app-layout';
import AccountLayout from '@/layouts/account/layout';
import { edit as editAppearance } from '@/routes/appearance';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Appearance',
        href: editAppearance(),
    },
];

export default function Appearance() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Appearance" />

            <h1 className="sr-only">Appearance</h1>

            <AccountLayout>
                <div className="space-y-6">
                    <Heading
                        variant="small"
                        title="Appearance"
                        description="Update your account appearance"
                    />
                    <AppearanceTabs />
                </div>
            </AccountLayout>
        </AppLayout>
    );
}
