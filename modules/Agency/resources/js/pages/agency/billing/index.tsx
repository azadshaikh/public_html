import { Link } from '@inertiajs/react';
import {
    ChevronRightIcon,
    CreditCardIcon,
    FileTextIcon,
    ReceiptTextIcon,
} from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Billing', href: route('agency.billing.index') },
];

type BillingCard = {
    title: string;
    description: string;
    href: string;
    icon: typeof CreditCardIcon;
    status?: {
        label: string;
        className: string;
    };
};

const sections: BillingCard[] = [
    {
        title: 'Subscription',
        description:
            'View your current plan and usage as well as other active purchases.',
        href: route('agency.billing.subscriptions.index'),
        icon: CreditCardIcon,
    },
    {
        title: 'Billing History',
        description:
            'View email receipts for past purchases.',
        href: route('agency.billing.invoices.index'),
        icon: FileTextIcon,
    },
    {
        title: 'Tax Details',
        description:
            'Configure tax details (VAT/GST/CT) to be included on all receipts.',
        href: route('agency.billing.tax-details'),
        icon: ReceiptTextIcon,
    },
];

function BillingStatus({
    label,
    className,
}: {
    label: string;
    className: string;
}) {
    return (
        <span
            className={cn(
                'inline-flex h-5 items-center rounded-full px-2 text-[11px] font-semibold',
                className,
            )}
        >
            {label}
        </span>
    );
}

function BillingCardLink({
    title,
    description,
    href,
    icon: Icon,
    status,
}: BillingCard) {
    return (
        <Link
            href={href}
            className="group block rounded-xl border bg-card transition-all duration-150 hover:-translate-y-0.5 hover:border-foreground/20 hover:shadow-[0_14px_30px_-22px_rgba(15,23,42,0.4)]"
        >
            <div className="flex items-start justify-between gap-4 px-6 py-5">
                <div className="min-w-0 flex-1">
                    <h2 className="flex items-center gap-2 text-[1.05rem] font-semibold text-foreground">
                        <Icon className="size-[18px] shrink-0 text-foreground" />
                        <span>{title}</span>
                    </h2>

                    <p className="mt-3 text-sm leading-6 text-muted-foreground">
                        {description}
                    </p>

                    {status ? (
                        <div className="mt-4">
                            <BillingStatus {...status} />
                        </div>
                    ) : null}
                </div>

                <ChevronRightIcon className="mt-1 size-5 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5" />
            </div>
        </Link>
    );
}

export default function AgencyBillingIndex() {
    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Billing"
            description="View your billing information and payment history."
        >
            <div className="mx-auto flex w-full max-w-5xl flex-col gap-4">
                {sections.map((section) => (
                    <BillingCardLink key={section.title} {...section} />
                ))}
            </div>
        </AppLayout>
    );
}
