import { Link, router, usePage } from '@inertiajs/react';
import { ArrowLeftIcon, ExternalLinkIcon, PencilIcon } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { ConfirmationDialog } from '@/components/ui/confirmation-dialog';
import { useIsMobile } from '@/hooks/use-mobile';
import AppLayout from '@/layouts/app-layout';
import type { AuthenticatedSharedData, BreadcrumbItem } from '@/types';
import type {
    AgencyProviderItem,
    AgencyRelationItem,
    AgencyServerItem,
    AgencyShowData,
    PlatformActivity,
} from '../../../types/platform';
import { AgencyShowOverview } from './components/agency-show-overview';
import { AgencyShowTabs } from './components/agency-show-tabs';
import type { ConfirmState } from './components/show-shared';
import { INITIAL_CONFIRM } from './components/show-shared';

type AgenciesShowPageProps = {
    agency: AgencyShowData;
    websites: AgencyRelationItem[];
    servers: AgencyServerItem[];
    dnsProviders: AgencyProviderItem[];
    cdnProviders: AgencyProviderItem[];
    activities: PlatformActivity[];
};

export default function AgenciesShow({
    agency,
    websites,
    servers,
    dnsProviders,
    cdnProviders,
    activities,
}: AgenciesShowPageProps) {
    const page = usePage<AuthenticatedSharedData>();
    const canEdit = Boolean(page.props.auth.abilities.editAgencies);
    const canDelete = Boolean(page.props.auth.abilities.deleteAgencies);
    const canRestore = Boolean(page.props.auth.abilities.restoreAgencies);
    const isMobile = useIsMobile();
    const [processing, setProcessing] = useState(false);
    const [confirm, setConfirm] = useState<ConfirmState>(INITIAL_CONFIRM);
    const [activeTab, setActiveTab] = useState('general');

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
            title: `#${agency.id}`,
            href: route('platform.agencies.show', agency.id),
        },
    ];

    function perform(method: 'post' | 'patch' | 'delete', url: string): void {
        setProcessing(true);

        const options = {
            preserveScroll: true,
            onFinish: () => setProcessing(false),
        };

        if (method === 'post') {
            router.post(url, {}, options);

            return;
        }

        if (method === 'patch') {
            router.patch(url, {}, options);

            return;
        }

        router.delete(url, options);
    }

    function openConfirm(
        title: string,
        description: string,
        confirmLabel: string,
        action: () => void,
        tone: 'default' | 'destructive' = 'default',
    ): void {
        setConfirm({
            open: true,
            title,
            description,
            confirmLabel,
            tone,
            action,
        });
    }

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={agency.name}
            description="Manage your agency profile, infrastructure links, and operational defaults."
            headerActions={
                <div className="flex flex-wrap items-center gap-2">
                    {agency.branding.website ? (
                        <Button variant="outline" asChild>
                            <a
                                href={agency.branding.website}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <ExternalLinkIcon data-icon="inline-start" />
                                Visit Website
                            </a>
                        </Button>
                    ) : null}

                    {canEdit && !agency.is_trashed ? (
                        <Button variant="outline" asChild>
                            <Link href={route('platform.agencies.edit', agency.id)}>
                                <PencilIcon data-icon="inline-start" />
                                Edit
                            </Link>
                        </Button>
                    ) : null}

                    <Button variant="outline" asChild>
                        <Link
                            href={route('platform.agencies.index', {
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
                <AgencyShowOverview
                    agency={agency}
                    servers={servers}
                    dnsProviders={dnsProviders}
                    cdnProviders={cdnProviders}
                    canEdit={canEdit}
                    canDelete={canDelete}
                    canRestore={canRestore}
                    processing={processing}
                    setActiveTab={setActiveTab}
                    openConfirm={openConfirm}
                    perform={perform}
                />

                <AgencyShowTabs
                    agency={agency}
                    websites={websites}
                    servers={servers}
                    dnsProviders={dnsProviders}
                    cdnProviders={cdnProviders}
                    activities={activities}
                    canEdit={canEdit}
                    isMobile={isMobile}
                    activeTab={activeTab}
                    setActiveTab={setActiveTab}
                    processing={processing}
                    openConfirm={openConfirm}
                    perform={perform}
                />
            </div>

            <ConfirmationDialog
                open={confirm.open}
                onOpenChange={(open) =>
                    setConfirm((current) => ({ ...current, open }))
                }
                title={confirm.title}
                description={confirm.description}
                confirmLabel={confirm.confirmLabel}
                tone={confirm.tone}
                confirmVariant={
                    confirm.tone === 'destructive' ? 'destructive' : 'default'
                }
                confirmDisabled={processing}
                onConfirm={confirm.action}
            />
        </AppLayout>
    );
}
