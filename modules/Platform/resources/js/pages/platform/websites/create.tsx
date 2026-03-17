import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import WebsiteForm from '../../../components/websites/website-form';
import type { PlatformOption, WebsiteFormValues } from '../../../types/platform';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Platform', href: route('platform.websites.index', { status: 'all' }) },
    { title: 'Websites', href: route('platform.websites.index', { status: 'all' }) },
    { title: 'Create', href: route('platform.websites.create') },
];

type WebsitesCreatePageProps = {
    initialValues: WebsiteFormValues;
    serverOptions: PlatformOption[];
    agencyOptions: PlatformOption[];
    statusOptions: PlatformOption[];
    typeOptions: PlatformOption[];
    planOptions: PlatformOption[];
    dnsProviderOptions: PlatformOption[];
    cdnProviderOptions: PlatformOption[];
    order?: {
        id: number;
        reference?: string | null;
    } | null;
};

export default function WebsitesCreate(props: WebsitesCreatePageProps) {
    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Create website"
            description="Add a provisioned website and assign its infrastructure, agency, and provider routing."
        >
            <WebsiteForm
                mode="create"
                initialValues={props.initialValues}
                serverOptions={props.serverOptions}
                agencyOptions={props.agencyOptions}
                statusOptions={props.statusOptions}
                typeOptions={props.typeOptions}
                planOptions={props.planOptions}
                dnsProviderOptions={props.dnsProviderOptions}
                cdnProviderOptions={props.cdnProviderOptions}
                order={props.order}
            />
        </AppLayout>
    );
}
