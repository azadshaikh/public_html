import { Link } from '@inertiajs/react';
import { CreditCardIcon, FileTextIcon, ReceiptTextIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Billing', href: route('agency.billing.index') },
];

const sections = [
    {
        title: 'Subscription',
        description:
            'Review your active plans, billing cycles, and subscription details.',
        href: route('agency.billing.subscriptions.index'),
        icon: CreditCardIcon,
    },
    {
        title: 'Billing History',
        description:
            'Browse issued invoices and track what is still outstanding.',
        href: route('agency.billing.invoices.index'),
        icon: FileTextIcon,
    },
    {
        title: 'Tax Details',
        description:
            'Manage the billing identity and tax information shown on receipts.',
        href: route('agency.billing.tax-details'),
        icon: ReceiptTextIcon,
    },
];

export default function AgencyBillingIndex() {
    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Billing"
            description="Manage subscription details, invoices, and tax settings for your account."
        >
            <div className="grid gap-4 md:grid-cols-3">
                {sections.map((section) => {
                    const Icon = section.icon;

                    return (
                        <Card key={section.title} className="border-border/60">
                            <CardHeader className="gap-4">
                                <div className="flex size-11 items-center justify-center rounded-2xl bg-muted">
                                    <Icon className="size-5" />
                                </div>
                                <div className="space-y-1">
                                    <CardTitle>{section.title}</CardTitle>
                                    <CardDescription>
                                        {section.description}
                                    </CardDescription>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <Button asChild className="w-full">
                                    <Link href={section.href}>Open</Link>
                                </Button>
                            </CardContent>
                        </Card>
                    );
                })}
            </div>
        </AppLayout>
    );
}
