import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import WebsiteForm from '../../../components/websites/website-form';
import type { PlatformOption, WebsiteFormValues } from '../../../types/platform';

type WebsitesEditPageProps = {
    website: {
        id: number;
        name: string;
        uid: string | null;
    };
    initialValues: WebsiteFormValues;
    serverOptions: PlatformOption[];
    agencyOptions: PlatformOption[];
    statusOptions: PlatformOption[];
    typeOptions: PlatformOption[];
    planOptions: PlatformOption[];
    dnsProviderOptions: PlatformOption[];
    cdnProviderOptions: PlatformOption[];
};

export default function WebsitesEdit(props: WebsitesEditPageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Platform', href: route('platform.websites.index', { status: 'all' }) },
        { title: 'Websites', href: route('platform.websites.index', { status: 'all' }) },
        { title: props.website.name, href: route('platform.websites.show', props.website.id) },
        { title: 'Edit', href: route('platform.websites.edit', props.website.id) },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={`Edit ${props.website.name}`}
            description="Update lifecycle, routing providers, and infrastructure assignment for this website."
        >
            <WebsiteForm
                mode="edit"
                website={props.website}
                initialValues={props.initialValues}
                serverOptions={props.serverOptions}
                agencyOptions={props.agencyOptions}
                statusOptions={props.statusOptions}
                typeOptions={props.typeOptions}
                planOptions={props.planOptions}
                dnsProviderOptions={props.dnsProviderOptions}
                cdnProviderOptions={props.cdnProviderOptions}
            />
        </AppLayout>
    );
}
