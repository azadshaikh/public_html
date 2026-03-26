import { Link } from '@inertiajs/react';
import { ArrowLeftIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import WebsiteForm from '../../../components/websites/website-form';
import type {
    PlatformOption,
    WebsiteFormValues,
} from '../../../types/platform';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    {
        title: 'Platform',
        href: route('platform.websites.index', { status: 'all' }),
    },
    {
        title: 'Websites',
        href: route('platform.websites.index', { status: 'all' }),
    },
    { title: 'Create', href: route('platform.websites.create') },
];

type WebsitesCreatePageProps = {
    initialValues: WebsiteFormValues;
    serverOptions: PlatformOption[];
    agencyOptions: PlatformOption[];
    statusOptions: PlatformOption[];
    typeOptions: PlatformOption[];
    planOptions: PlatformOption[];
    dnsModeOptions: PlatformOption[];
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
            title="Add website"
            description="Set up a new website, assign its infrastructure, and configure provisioning options before launch."
            headerActions={
                <Button asChild variant="outline">
                    <Link href={route('platform.websites.index', { status: 'all' })}>
                        <ArrowLeftIcon data-icon="inline-start" />
                        Back
                    </Link>
                </Button>
            }
        >
            <WebsiteForm
                mode="create"
                initialValues={props.initialValues}
                serverOptions={props.serverOptions}
                agencyOptions={props.agencyOptions}
                statusOptions={props.statusOptions}
                typeOptions={props.typeOptions}
                planOptions={props.planOptions}
                dnsModeOptions={props.dnsModeOptions}
                dnsProviderOptions={props.dnsProviderOptions}
                cdnProviderOptions={props.cdnProviderOptions}
                order={props.order}
            />
        </AppLayout>
    );
}
