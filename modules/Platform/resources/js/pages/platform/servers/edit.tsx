import { Link } from '@inertiajs/react';

import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import ServerForm from '../../../components/servers/server-form';
import type { PlatformOption, ServerFormValues } from '../../../types/platform';

type ServersEditPageProps = {
    server: {
        id: number;
        name: string;
        provisioning_status?: string | null;
        has_ssh_credentials?: boolean;
        has_ssh_private_key?: boolean;
    };
    initialValues: ServerFormValues;
    typeOptions: PlatformOption[];
    providerOptions: PlatformOption[];
    statusOptions: PlatformOption[];
    sshCommand?: string | null;
};

export default function ServersEdit(props: ServersEditPageProps) {
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
        {
            title: props.server.name,
            href: route('platform.servers.show', props.server.id),
        },
        {
            title: 'Edit',
            href: route('platform.servers.edit', props.server.id),
        },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={`Edit ${props.server.name}`}
            description="Update connectivity, credentials, and operational settings for this server."
            headerActions={
                <div className="flex flex-wrap items-center gap-2">
                    <Button variant="outline" asChild>
                        <Link href={route('platform.servers.show', props.server.id)}>Show</Link>
                    </Button>
                    <Button variant="outline" asChild>
                        <Link href={route('platform.servers.index', { status: 'all' })}>Back</Link>
                    </Button>
                </div>
            }
        >
            <ServerForm
                server={props.server}
                initialValues={props.initialValues}
                typeOptions={props.typeOptions}
                providerOptions={props.providerOptions}
                statusOptions={props.statusOptions}
                sshCommand={props.sshCommand}
            />
        </AppLayout>
    );
}
