import { Link, usePoll } from '@inertiajs/react';
import {
    ArrowLeftIcon,
    ExternalLinkIcon,
    PencilIcon,
    RefreshCwIcon,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { ConfirmationDialog } from '@/components/ui/confirmation-dialog';
import { useIsMobile } from '@/hooks/use-mobile';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type {
    PlatformActivity,
    ProvisioningRunTimestamps,
    WebsiteProvisioningStep,
    WebsiteSecretItem,
    WebsiteShowData,
    WebsiteUpdateItem,
} from '../../../types/platform';
import type { ConfirmState } from './components/show-shared';
import { INITIAL_CONFIRM, useOperationAction } from './components/show-shared';
import { WebsiteShowOverview } from './components/website-show-overview';
import { WebsiteShowTabs } from './components/website-show-tabs';

type WebsitesShowPageProps = {
    website: WebsiteShowData;
    provisioningSteps: WebsiteProvisioningStep[];
    provisioningRun: ProvisioningRunTimestamps;
    updates: WebsiteUpdateItem[];
    secrets: WebsiteSecretItem[];
    activities: PlatformActivity[];
    pullzoneId: string | null;
    canRevealSecrets: boolean;
    canManageLaravelLog: boolean;
    canManageWebsiteEnv: boolean;
};

const PROVISIONING_POLL_INTERVAL_MS = 10_000;

function shouldPollPrimaryHostnameSync(status: string | null): boolean {
    return status === 'queued' || status === 'processing';
}

export default function WebsitesShow({
    website,
    provisioningSteps,
    provisioningRun,
    updates,
    secrets,
    activities,
    pullzoneId,
    canRevealSecrets,
    canManageLaravelLog,
    canManageWebsiteEnv,
}: WebsitesShowPageProps) {
    const [confirm, setConfirm] = useState<ConfirmState>(INITIAL_CONFIRM);
    const [activeTab, setActiveTab] = useState(
        website.status === 'provisioning' || website.status === 'failed'
            ? 'provision'
            : 'general',
    );
    const op = useOperationAction();
    const isMobile = useIsMobile();
    const isActive = website.status === 'active';
    const isFailed = website.status === 'failed';
    const isPrimaryHostnameSyncPending = shouldPollPrimaryHostnameSync(
        website.primary_hostname_sync?.status ?? null,
    );
    const primaryHostnameSyncPoll = usePoll(
        PROVISIONING_POLL_INTERVAL_MS,
        {
            only: ['website'],
        },
        {
            autoStart: false,
        },
    );

    useEffect(() => {
        if (isPrimaryHostnameSyncPending) {
            primaryHostnameSyncPoll.start();

            return () => {
                primaryHostnameSyncPoll.stop();
            };
        }

        primaryHostnameSyncPoll.stop();

        return undefined;
    }, [isPrimaryHostnameSyncPending, primaryHostnameSyncPoll]);

    function openConfirm(
        title: string,
        description: string,
        confirmLabel: string,
        action: () => void,
        tone: ConfirmState['tone'] = 'default',
    ) {
        setConfirm({
            open: true,
            title,
            description,
            confirmLabel,
            tone,
            action,
        });
    }

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
        {
            title: `#${website.id}`,
            href: route('platform.websites.show', website.id),
        },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={website.name}
            description="Manage your website configuration, settings, and provisioning status."
            headerActions={
                <div className="flex flex-wrap items-center gap-2">
                    {isActive && website.admin_slug ? (
                        <Button variant="outline" asChild>
                            <a
                                href={`https://${website.domain}/${website.admin_slug}`}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <ExternalLinkIcon data-icon="inline-start" />
                                Open Admin
                            </a>
                        </Button>
                    ) : null}

                    {isFailed ? (
                        <Button
                            variant="default"
                            className="bg-amber-600 hover:bg-amber-700"
                            disabled={op.processing}
                            onClick={() =>
                                openConfirm(
                                    'Retry Provisioning',
                                    'This will retry the provisioning process for this website. Failed and pending steps will be retried.',
                                    'Retry',
                                    () =>
                                        op.perform(
                                            'post',
                                            route(
                                                'platform.websites.retry-provision',
                                                website.id,
                                            ),
                                        ),
                                )
                            }
                        >
                            <RefreshCwIcon data-icon="inline-start" />
                            Retry Provision
                        </Button>
                    ) : null}

                    <Button variant="outline" asChild>
                        <Link href={route('platform.websites.edit', website.id)}>
                            <PencilIcon data-icon="inline-start" />
                            Edit
                        </Link>
                    </Button>

                    <Button variant="outline" asChild>
                        <Link
                            href={route('platform.websites.index', {
                                status: 'all',
                            })}
                        >
                            <ArrowLeftIcon data-icon="inline-start" />
                            Back
                        </Link>
                    </Button>
                </div>
            }
        >
            <div className="flex flex-col gap-6">
                <WebsiteShowOverview
                    website={website}
                    pullzoneId={pullzoneId}
                    processing={op.processing}
                    openConfirm={openConfirm}
                    perform={op.perform}
                    isPrimaryHostnameSyncPending={isPrimaryHostnameSyncPending}
                />

                <WebsiteShowTabs
                    activeTab={activeTab}
                    setActiveTab={setActiveTab}
                    isMobile={isMobile}
                    website={website}
                    secrets={secrets}
                    canRevealSecrets={canRevealSecrets}
                    provisioningSteps={provisioningSteps}
                    provisioningRun={provisioningRun}
                    updates={updates}
                    activities={activities}
                    canManageLaravelLog={canManageLaravelLog}
                    canManageWebsiteEnv={canManageWebsiteEnv}
                />
            </div>

            <ConfirmationDialog
                open={confirm.open}
                onOpenChange={(open) =>
                    setConfirm((previous) => ({ ...previous, open }))
                }
                title={confirm.title}
                description={confirm.description}
                confirmLabel={confirm.confirmLabel}
                tone={confirm.tone}
                confirmVariant={
                    confirm.tone === 'destructive' ? 'destructive' : 'default'
                }
                onConfirm={confirm.action}
            />
        </AppLayout>
    );
}
