import { AlertCircleIcon, KeyRoundIcon } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import ServerForm from '../../../components/servers/server-form';
import type { PlatformOption, ServerFormValues } from '../../../types/platform';

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
    statusOptions: PlatformOption[];
    sshCommand: string | null;
};

export default function ServersCreate(props: ServersCreatePageProps) {
    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Create server"
            description="Add an existing Hestia endpoint or provision a fresh server with the standard install profile."
        >
            <div className="flex flex-col gap-6">
                <Alert>
                    <KeyRoundIcon />
                    <AlertTitle>
                        SSH keys are pre-generated for provision mode
                    </AlertTitle>
                    <AlertDescription>
                        Manual mode expects existing Hestia API credentials.
                        Provision mode uses the generated SSH key pair to
                        bootstrap the target host.
                    </AlertDescription>
                </Alert>

                <Alert>
                    <AlertCircleIcon />
                    <AlertTitle>
                        Provisioning can take several minutes
                    </AlertTitle>
                    <AlertDescription>
                        After creation, the server detail page will show
                        step-by-step progress and synchronization status.
                    </AlertDescription>
                </Alert>

                <ServerForm
                    mode="create"
                    initialValues={props.initialValues}
                    typeOptions={props.typeOptions}
                    providerOptions={props.providerOptions}
                    statusOptions={props.statusOptions}
                    sshCommand={props.sshCommand}
                />
            </div>
        </AppLayout>
    );
}
