import { Link } from '@inertiajs/react';
import { ArrowLeftIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import AgencyForm from '../../../components/agencies/agency-form';
import type { AgencyFormValues, PlatformOption } from '../../../types/platform';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    {
        title: 'Platform',
        href: route('platform.agencies.index', { status: 'all' }),
    },
    {
        title: 'Agencies',
        href: route('platform.agencies.index', { status: 'all' }),
    },
    { title: 'Create', href: route('platform.agencies.create') },
];

type AgenciesCreatePageProps = {
    initialValues: AgencyFormValues;
    typeOptions: PlatformOption[];
    ownerOptions: PlatformOption[];
    planOptions: PlatformOption[];
    statusOptions: PlatformOption[];
    websiteOptions: PlatformOption[];
    country_codes: PlatformOption[];
    default_country_code: string;
    default_phone_code: string;
};

export default function AgenciesCreate(props: AgenciesCreatePageProps) {
    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Create agency"
            description="Add a new agency account and define its ownership, defaults, and branding."
            headerActions={
                <Button asChild variant="outline">
                    <Link
                        href={route('platform.agencies.index', {
                            status: 'all',
                        })}
                    >
                        <ArrowLeftIcon data-icon="inline-start" />
                        Back
                    </Link>
                </Button>
            }
        >
            <AgencyForm
                mode="create"
                initialValues={props.initialValues}
                typeOptions={props.typeOptions}
                ownerOptions={props.ownerOptions}
                planOptions={props.planOptions}
                statusOptions={props.statusOptions}
                websiteOptions={props.websiteOptions}
                phoneCodeOptions={props.country_codes}
                defaultCountryCode={props.default_country_code}
                defaultPhoneCode={props.default_phone_code}
            />
        </AppLayout>
    );
}
