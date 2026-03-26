import { Link } from '@inertiajs/react';
import { ExternalLinkIcon, PencilIcon } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { ConfirmationDialog } from '@/components/ui/confirmation-dialog';
import { useIsMobile } from '@/hooks/use-mobile';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type {
    PlatformActivity,
    ProvisioningRunTimestamps,
    ServerAgencyItem,
    ServerMetadataItem,
    ServerProvisioningStep,
    ServerSecretItem,
    ServerShowData,
} from '../../../types/platform';
import { ServerShowOverview } from './components/server-show-overview';
import { ServerShowTabs } from './components/server-show-tabs';
import { INITIAL_CONFIRM, useOperationAction } from './components/show-shared';
import type { ConfirmState } from './components/show-shared';

type ServersShowPageProps = {
    server: ServerShowData;
    provisioningSteps: ServerProvisioningStep[];
    provisioningRun: ProvisioningRunTimestamps;
    websiteCounts: {
        total: number;
        active: number;
        inactive: number;
        provisioning: number;
    };
    secrets: ServerSecretItem[];
    activities: PlatformActivity[];
    agencies: ServerAgencyItem[];
    metadataItems: ServerMetadataItem[];
    canRevealSecrets: boolean;
    canRevealSshKeyPair: boolean;
    canManageScriptLog: boolean;
};

export default function ServersShow({
    server,
    provisioningSteps,
    provisioningRun,
    websiteCounts,
    secrets,
    activities,
    agencies,
    metadataItems,
    canRevealSecrets,
    canRevealSshKeyPair,
    canManageScriptLog,
}: ServersShowPageProps) {
    const [confirm, setConfirm] = useState<ConfirmState>(INITIAL_CONFIRM);
    const { processing, perform } = useOperationAction();
    const isMobile = useIsMobile();
    const defaultTab = server.provisioning_status === 'provisioning' || server.provisioning_status === 'failed'
        ? 'provision'
        : 'general';
    const [activeTab, setActiveTab] = useState(defaultTab);

    function openConfirm(
        title: string,
        description: string,
        confirmLabel: string,
        action: () => void,
        tone: ConfirmState['tone'] = 'default',
    ) {
        setConfirm({ open: true, title, description, confirmLabel, tone, action });
    }

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
        { title: server.name, href: route('platform.servers.show', server.id) },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={server.name}
            description="Inspect provisioning progress, websites, credentials, and recent operations."
            headerActions={
                <div className="flex flex-wrap items-center gap-2">
                    {server.fqdn ? (
                        <Button variant="outline" asChild>
                            <a
                                href={`https://${server.fqdn.replace(/^https?:\/\//, '')}:${server.port ?? 8443}`}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <ExternalLinkIcon data-icon="inline-start" />
                                Open panel
                            </a>
                        </Button>
                    ) : null}
                    <Button variant="outline" asChild>
                        <Link href={route('platform.servers.edit', server.id)}>
                            <PencilIcon data-icon="inline-start" />
                            Edit server
                        </Link>
                    </Button>
                </div>
            }
        >
            <div className="flex flex-col gap-6">
                <ServerShowOverview
                    server={server}
                    websiteCounts={websiteCounts}
                    processing={processing}
                    openConfirm={openConfirm}
                    perform={perform}
                />

                <ServerShowTabs
                    activeTab={activeTab}
                    setActiveTab={setActiveTab}
                    isMobile={isMobile}
                    server={server}
                    secrets={secrets}
                    agencies={agencies}
                    metadataItems={metadataItems}
                    canRevealSecrets={canRevealSecrets}
                    canRevealSshKeyPair={canRevealSshKeyPair}
                    canManageScriptLog={canManageScriptLog}
                    provisioningSteps={provisioningSteps}
                    provisioningRun={provisioningRun}
                    activities={activities}
                />
            </div>

            <ConfirmationDialog
                open={confirm.open}
                onOpenChange={(open) => {
                    if (!open) {
                        setConfirm(INITIAL_CONFIRM);
                    }
                }}
                title={confirm.title}
                description={confirm.description}
                confirmLabel={confirm.confirmLabel}
                tone={confirm.tone}
                confirmDisabled={processing}
                onConfirm={() => {
                    const action = confirm.action;

                    setConfirm(INITIAL_CONFIRM);
                    action();
                }}
                onCancel={() => setConfirm(INITIAL_CONFIRM)}
            />
        </AppLayout>
    );
}
