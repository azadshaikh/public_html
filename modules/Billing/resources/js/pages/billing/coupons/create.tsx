import { Link } from '@inertiajs/react';
import { ArrowLeftIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import CouponForm from '../../../components/coupons/coupon-form';
import type { CouponCreatePageProps } from '../../../types/billing';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Billing' },
    { title: 'Coupons', href: route('app.billing.coupons.index') },
    { title: 'Create', href: route('app.billing.coupons.create') },
];

export default function CouponsCreate(props: CouponCreatePageProps) {
    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Create Coupon"
            description="Create a new discount coupon"
            headerActions={
                <Button variant="outline" asChild>
                    <Link href={route('app.billing.coupons.index')}>
                        <ArrowLeftIcon data-icon="inline-start" />
                        Back
                    </Link>
                </Button>
            }
        >
            <CouponForm mode="create" {...props} />
        </AppLayout>
    );
}
