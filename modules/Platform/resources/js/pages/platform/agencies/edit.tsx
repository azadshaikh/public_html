import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import AgencyForm from '../../../components/agencies/agency-form';
import type {
    AgencyFormValues,
    PlatformMediaPickerPageProps,
    PlatformOption,
} from '../../../types/platform';

type AgenciesEditPageProps = {
    agency: {
        id: number;
        name: string;
        uid: string | null;
    };
    initialValues: AgencyFormValues;
    typeOptions: PlatformOption[];
    ownerOptions: PlatformOption[];
    planOptions: PlatformOption[];
    statusOptions: PlatformOption[];
    websiteOptions: PlatformOption[];
    country_codes: PlatformOption[];
    default_country_code: string;
    default_phone_code: string;
} & PlatformMediaPickerPageProps;

export default function AgenciesEdit(props: AgenciesEditPageProps) {
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
        {
            title: props.agency.name,
            href: route('platform.agencies.show', props.agency.id),
        },
        {
            title: 'Edit',
            href: route('platform.agencies.edit', props.agency.id),
        },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={`Edit ${props.agency.name}`}
            description="Update agency defaults, ownership, and provisioning metadata."
            headerActions={
                <div className="flex flex-wrap items-center gap-2">
                    <Button variant="outline" asChild>
                        <Link href={route('platform.agencies.show', props.agency.id)}>
                            Show
                        </Link>
                    </Button>
                    <Button variant="outline" asChild>
                        <Link
                            href={route('platform.agencies.index', {
                                status: 'all',
                            })}
                        >
                            Back
                        </Link>
                    </Button>
                </div>
            }
        >
            <AgencyForm
                mode="edit"
                agency={props.agency}
                initialValues={props.initialValues}
                typeOptions={props.typeOptions}
                ownerOptions={props.ownerOptions}
                planOptions={props.planOptions}
                statusOptions={props.statusOptions}
                websiteOptions={props.websiteOptions}
                phoneCodeOptions={props.country_codes}
                defaultCountryCode={props.default_country_code}
                defaultPhoneCode={props.default_phone_code}
                pickerMedia={props.pickerMedia}
                pickerFilters={props.pickerFilters}
                uploadSettings={props.uploadSettings}
                pickerStatistics={props.pickerStatistics}
            />
        </AppLayout>
    );
}
