import { Link, usePage } from '@inertiajs/react';
import { ArrowLeftIcon, GlobeIcon, PencilIcon, PlusIcon, SparklesIcon } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { ConfirmationDialog } from '@/components/ui/confirmation-dialog';
import { useIsMobile } from '@/hooks/use-mobile';
import AppLayout from '@/layouts/app-layout';
import type { AuthenticatedSharedData } from '@/types';
import type { BreadcrumbItem } from '@/types';
import type {
    DomainDnsRecordItem,
    DomainShowData,
    DomainSslCertificateItem,
    DomainWebsiteItem,
    PlatformActivity,
} from '../../../types/platform';
import { DomainShowOverview } from './components/domain-show-overview';
import { DomainShowTabs } from './components/domain-show-tabs';
import { INITIAL_CONFIRM, useDomainOperationAction } from './components/show-shared';
import type { ConfirmState } from './components/show-shared';

type DomainsShowPageProps = {
    domain: DomainShowData;
    dnsRecords: DomainDnsRecordItem[];
    sslCertificates: DomainSslCertificateItem[];
    websites: DomainWebsiteItem[];
    activities: PlatformActivity[];
};

export default function DomainsShow({
    domain,
    dnsRecords,
    sslCertificates,
    websites,
    activities,
}: DomainsShowPageProps) {
    const page = usePage<AuthenticatedSharedData>();
    const canEdit = Boolean(page.props.auth.abilities.editDomains);
    const canDelete = Boolean(page.props.auth.abilities.deleteDomains);
    const canRestore = Boolean(page.props.auth.abilities.restoreDomains);
    const isMobile = useIsMobile();
    const [confirm, setConfirm] = useState<ConfirmState>(INITIAL_CONFIRM);
    const [activeTab, setActiveTab] = useState('general');
    const { processing, performVisit, performJson } = useDomainOperationAction();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Platform', href: route('platform.domains.index', { status: 'all' }) },
        { title: 'Domains', href: route('platform.domains.index', { status: 'all' }) },
        { title: domain.name, href: route('platform.domains.show', domain.id) },
    ];

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
            title={domain.name}
            description="Inspect ownership, DNS state, certificate usage, and recent activity for this domain."
            headerActions={
                <div className="flex flex-wrap items-center gap-2">
                    <Button variant="outline" asChild>
                        <Link href={route('platform.domains.ssl-certificates.generate-self-signed', domain.id)}>
                            <SparklesIcon data-icon="inline-start" />
                            Generate self-signed
                        </Link>
                    </Button>
                    <Button variant="outline" asChild>
                        <Link href={route('platform.domains.ssl-certificates.create', domain.id)}>
                            <PlusIcon data-icon="inline-start" />
                            Add certificate
                        </Link>
                    </Button>
                    <Button variant="outline" asChild>
                        <Link href={route('platform.dns.index', { status: 'all', domain_id: domain.id })}>
                            <GlobeIcon data-icon="inline-start" />
                            Manage DNS
                        </Link>
                    </Button>
                    {canEdit ? (
                        <Button variant="outline" asChild>
                            <Link href={route('platform.domains.edit', domain.id)}>
                                <PencilIcon data-icon="inline-start" />
                                Edit domain
                            </Link>
                        </Button>
                    ) : null}
                    <Button variant="outline" asChild>
                        <Link href={route('platform.domains.index', { status: 'all' })}>
                            <ArrowLeftIcon data-icon="inline-start" />
                            Back
                        </Link>
                    </Button>
                </div>
            }
        >
            <div className="flex flex-col gap-6">
                <DomainShowOverview
                    domain={domain}
                    processing={processing}
                    canEdit={canEdit}
                    canDelete={canDelete}
                    canRestore={canRestore}
                    setActiveTab={setActiveTab}
                    openConfirm={openConfirm}
                    performVisit={performVisit}
                    performJson={performJson}
                />

                <DomainShowTabs
                    activeTab={activeTab}
                    setActiveTab={setActiveTab}
                    isMobile={isMobile}
                    domain={domain}
                    dnsRecords={dnsRecords}
                    sslCertificates={sslCertificates}
                    websites={websites}
                    activities={activities}
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
                confirmVariant={confirm.tone === 'destructive' ? 'destructive' : 'default'}
                confirmDisabled={processing}
                onConfirm={confirm.action}
            />
        </AppLayout>
    );
}
