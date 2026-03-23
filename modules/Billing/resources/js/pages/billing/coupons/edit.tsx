import { Link } from '@inertiajs/react';
import { ArrowLeftIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import CouponForm from '../../../components/coupons/coupon-form';
import type { CouponEditPageProps } from '../../../types/billing';

export default function CouponsEdit({ coupon, ...props }: CouponEditPageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Billing' },
        { title: 'Coupons', href: route('app.billing.coupons.index') },
        { title: coupon.name, href: route('app.billing.coupons.show', coupon.id) },
        { title: 'Edit', href: route('app.billing.coupons.edit', coupon.id) },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={`Edit: ${coupon.name}`}
            description="Update coupon details"
            headerActions={
                <Button variant="outline" asChild>
                    <Link href={route('app.billing.coupons.show', coupon.id)}>
                        <ArrowLeftIcon data-icon="inline-start" />
                        Back
                    </Link>
                </Button>
            }
        >
            <CouponForm mode="edit" coupon={coupon} {...props} />
        </AppLayout>
    );
}
