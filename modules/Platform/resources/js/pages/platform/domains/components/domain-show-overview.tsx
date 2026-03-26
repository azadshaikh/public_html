import { Link } from '@inertiajs/react';
import {
    GlobeIcon,
    PencilIcon,
    RefreshCwIcon,
    RotateCcwIcon,
    ShieldCheckIcon,
    ShieldIcon,
    Trash2Icon,
    ZapIcon,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import type { DomainShowData } from '../../../../types/platform';
import type { ConfirmState } from './show-shared';
import {
    formatStatusLabel,
    HealthChip,
    InfoRow,
    MetricBox,
    statusBadgeVariant,
} from './show-shared';

type DomainShowOverviewProps = {
    domain: DomainShowData;
    processing: boolean;
    canEdit: boolean;
    canDelete: boolean;
    canRestore: boolean;
    setActiveTab: (tab: string) => void;
    openConfirm: (
        title: string,
        description: string,
        confirmLabel: string,
        action: () => void,
        tone?: ConfirmState['tone'],
    ) => void;
    performVisit: (method: 'post' | 'patch' | 'delete', url: string) => void;
    performJson: (url: string, options?: { method?: 'GET' | 'POST' | 'PATCH' | 'DELETE' }) => Promise<void>;
};

function toneForState(status: string | null): 'success' | 'warning' | 'secondary' | 'danger' {
    return status === 'active'
        ? 'success'
        : status === 'pending'
          ? 'warning'
          : status === 'failed'
            ? 'danger'
            : 'secondary';
}

export function DomainShowOverview({
    domain,
    processing,
    canEdit,
    canDelete,
    canRestore,
    setActiveTab,
    openConfirm,
    performVisit,
    performJson,
}: DomainShowOverviewProps) {
    return (
        <div className="flex flex-col gap-6">
            <div className="-mt-4 flex flex-wrap items-center gap-2">
                <Badge variant={domain.is_trashed ? 'danger' : statusBadgeVariant(domain.status)}>
                    {domain.is_trashed ? 'In Trash' : domain.status_label}
                </Badge>
                <Badge variant={statusBadgeVariant(domain.dns_status)}>
                    DNS: {formatStatusLabel(domain.dns_status)}
                </Badge>
                <Badge variant={statusBadgeVariant(domain.ssl_status)}>
                    SSL: {formatStatusLabel(domain.ssl_status)}
                </Badge>
            </div>

            {domain.is_trashed ? (
                <div className="flex flex-col gap-3 rounded-lg border border-destructive/30 bg-destructive/5 p-4 md:flex-row md:items-center md:justify-between">
                    <div className="flex items-center gap-3">
                        <Trash2Icon className="size-5 shrink-0 text-destructive" />
                        <div>
                            <p className="font-semibold text-foreground">This domain is in trash</p>
                            <p className="text-sm text-muted-foreground">
                                {domain.deleted_at
                                    ? `Trashed on ${domain.deleted_at}.`
                                    : 'Restore it to resume DNS and SSL management.'}
                            </p>
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {canRestore ? (
                            <Button
                                disabled={processing}
                                onClick={() => openConfirm(
                                    'Restore Domain',
                                    'Restore this domain from trash?',
                                    'Restore',
                                    () => performVisit('patch', route('platform.domains.restore', domain.id)),
                                )}
                            >
                                <RotateCcwIcon data-icon="inline-start" />
                                Restore
                            </Button>
                        ) : null}
                        {canDelete ? (
                            <Button
                                variant="destructive"
                                disabled={processing}
                                onClick={() => openConfirm(
                                    'Delete Permanently',
                                    'Permanently delete this domain record? This cannot be undone.',
                                    'Delete Permanently',
                                    () => performVisit('delete', route('platform.domains.force-delete', domain.id)),
                                    'destructive',
                                )}
                            >
                                <Trash2Icon data-icon="inline-start" />
                                Delete Permanently
                            </Button>
                        ) : null}
                    </div>
                </div>
            ) : null}

            <div className="grid gap-6 xl:grid-cols-[1fr_22rem]">
                <Card>
                    <CardHeader>
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">Command Center</p>
                                <CardTitle className="mt-1 text-xl">{domain.name}</CardTitle>
                                <p className="text-sm text-muted-foreground">
                                    {domain.type_label ?? 'Domain'}{domain.dns_mode ? ` · ${formatStatusLabel(domain.dns_mode)} DNS` : ''}
                                </p>
                            </div>
                            <div className="text-right text-sm">
                                <p className="text-muted-foreground">Domain ID</p>
                                <p className="font-mono font-semibold">{domain.id}</p>
                                <p className="mt-1 text-muted-foreground">
                                    Updated: {domain.updated_at ?? '—'}
                                </p>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-4">
                        <div className="grid grid-cols-2 gap-2 lg:grid-cols-4">
                            <MetricBox label="Websites" value={domain.websites_count} />
                            <MetricBox label="DNS Records" value={domain.dns_records_count} />
                            <MetricBox label="Certificates" value={domain.ssl_certificates_count} />
                            <MetricBox
                                label="Latest SSL Usage"
                                value={`${domain.latest_certificate_websites_count} website${domain.latest_certificate_websites_count === 1 ? '' : 's'}`}
                            />
                        </div>

                        <div className="flex flex-wrap gap-2">
                            <HealthChip label={`Domain: ${domain.status_label ?? 'Unknown'}`} tone={toneForState(domain.status)} />
                            <HealthChip label={`DNS: ${formatStatusLabel(domain.dns_status)}`} tone={toneForState(domain.dns_status)} />
                            <HealthChip label={`SSL: ${formatStatusLabel(domain.ssl_status)}`} tone={toneForState(domain.ssl_status)} />
                            <HealthChip
                                label={`Expiry: ${domain.expires_date ? 'Tracked' : 'Unknown'}`}
                                tone={domain.expires_date ? 'success' : 'secondary'}
                            />
                        </div>

                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="flex flex-col gap-2">
                                <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">Ownership</p>
                                <InfoRow label="Agency">
                                    {domain.agency_id && route().has('platform.agencies.show') ? (
                                        <Link href={route('platform.agencies.show', domain.agency_id)} className="text-primary hover:underline">
                                            {domain.agency_name ?? '—'}
                                        </Link>
                                    ) : (
                                        domain.agency_name ?? 'Not assigned'
                                    )}
                                </InfoRow>
                                <InfoRow label="Registrar">{domain.registrar_name ?? '—'}</InfoRow>
                                <InfoRow label="Registered">{domain.registered_date ?? '—'}</InfoRow>
                                <InfoRow label="Expires">{domain.expires_date ?? '—'}</InfoRow>
                            </div>
                            <div className="flex flex-col gap-2">
                                <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">Routing</p>
                                <InfoRow label="DNS Provider">{domain.dns_provider ?? '—'}</InfoRow>
                                <InfoRow label="Zone ID">
                                    <span className="font-mono">{domain.dns_zone_id ?? '—'}</span>
                                </InfoRow>
                                <InfoRow label="DNS Mode">{formatStatusLabel(domain.dns_mode)}</InfoRow>
                                <InfoRow label="Latest Cert Expiry">{domain.latest_certificate_expires_at ?? '—'}</InfoRow>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <div className="flex items-center gap-2">
                            <ZapIcon className="size-4 text-muted-foreground" />
                            <CardTitle>Operations</CardTitle>
                        </div>
                        <CardDescription>
                            High-impact actions for DNS, WHOIS, certificates, and lifecycle state.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-4">
                        <div className="grid grid-cols-2 gap-2">
                            <Button
                                variant="outline"
                                disabled={processing}
                                onClick={() => {
                                    setActiveTab('dns');
                                }}
                            >
                                <GlobeIcon data-icon="inline-start" />
                                View DNS
                            </Button>
                            <Button
                                variant="outline"
                                disabled={processing}
                                onClick={() => {
                                    setActiveTab('ssl');
                                }}
                            >
                                <ShieldIcon data-icon="inline-start" />
                                View SSL
                            </Button>
                            {canEdit ? (
                                <Button
                                    variant="outline"
                                    disabled={processing}
                                    onClick={() => {
                                        void performJson(route('platform.domains.refresh-whois', domain.id), { method: 'POST' });
                                    }}
                                >
                                    <RefreshCwIcon data-icon="inline-start" />
                                    Refresh WHOIS
                                </Button>
                            ) : null}
                            {canEdit ? (
                                <Button variant="outline" asChild>
                                    <Link href={route('platform.domains.edit', domain.id)}>
                                        <PencilIcon data-icon="inline-start" />
                                        Edit Domain
                                    </Link>
                                </Button>
                            ) : null}
                        </div>

                        <div className="grid gap-2">
                            <Button variant="outline" asChild>
                                <Link href={route('platform.dns.index', { status: 'all', domain_id: domain.id })}>
                                    <GlobeIcon data-icon="inline-start" />
                                    Manage DNS
                                </Link>
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href={route('platform.domains.ssl-certificates.create', domain.id)}>
                                    <ShieldCheckIcon data-icon="inline-start" />
                                    Add Certificate
                                </Link>
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href={route('platform.domains.ssl-certificates.generate-self-signed', domain.id)}>
                                    <ShieldIcon data-icon="inline-start" />
                                    Generate Self-Signed
                                </Link>
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}
