import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { PlatformOption, ServerFormValues } from '../../../types/platform';
import ServerCreateWizard from './components/server-create-wizard';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    {
        title: 'Platform',
        href: route('platform.servers.index', { status: 'all' }),
    },
    {
        title: 'Servers',
        href: route('platform.servers.index', { status: 'all' }),
    },
    { title: 'Create', href: route('platform.servers.create') },
];

type ServersCreatePageProps = {
    initialValues: ServerFormValues;
    typeOptions: PlatformOption[];
    providerOptions: PlatformOption[];
    sshCommand: string | null;
};

export default function ServersCreate(props: ServersCreatePageProps) {
    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Add server"
            description="Choose a two-step onboarding flow for either connecting an existing HestiaCP endpoint or provisioning a fresh server."
        >
            <ServerCreateWizard
                initialValues={props.initialValues}
                typeOptions={props.typeOptions}
                providerOptions={props.providerOptions}
                sshCommand={props.sshCommand}
            />
        </AppLayout>
    );
}
