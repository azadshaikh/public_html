import { Link } from '@inertiajs/react';
import {
    ArrowLeftIcon,
    DownloadCloudIcon,
    ExternalLinkIcon,
    PauseCircleIcon,
    PencilIcon,
    PlayCircleIcon,
    RefreshCwIcon,
    RotateCcwIcon,
    ServerCogIcon,
    ShieldCheckIcon,
    ShieldXIcon,
    Trash2Icon,
    ZapIcon,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import type { ServerShowData } from '../../../types/platform';
import type { ConfirmState } from './show-shared';
import { formatStatusLabel, HealthChip, InfoRow, statusBadgeVariant } from './show-shared';

type ServerShowOverviewProps = {
    server: ServerShowData;
    websiteCounts: {
        total: number;
        active: number;
        inactive: number;
        provisioning: number;
    };
    processing: boolean;
    openConfirm: (
        title: string,
        description: string,
        confirmLabel: string,
        action: () => void,
        tone?: ConfirmState['tone'],
    ) => void;
    perform: (method: 'post' | 'delete' | 'patch', url: string) => void;
};

function formatMemoryValue(valueInMb: number | null): string {
    if (!valueInMb || valueInMb <= 0) {
        return '—';
    }

    return `${(valueInMb / 1024).toFixed(1)} GB`;
}

function formatStorageValue(valueInGb: number | null): string {
    if (!valueInGb || valueInGb <= 0) {
        return '—';
    }

    return `${valueInGb} GB`;
}

export function ServerShowOverview({
    server,
    websiteCounts,
    processing,
    openConfirm,
    perform,
}: ServerShowOverviewProps) {
    const isActive = server.status === 'active';
    const isProvisioning = server.provisioning_status === 'provisioning' || server.status === 'provisioning';
    const isFailed = server.provisioning_status === 'failed' || server.status === 'failed';
    const manageUrl = server.fqdn ? `https://${server.fqdn.replace(/^https?:\/\//, '')}:${server.port ?? 8443}` : null;
    const ramPercent = server.server_ram && server.server_ram_used
        ? Math.min(100, Math.round((server.server_ram_used / server.server_ram) * 100))
        : 0;
    const storagePercent = server.server_storage && server.server_storage_used
        ? Math.min(100, Math.round((server.server_storage_used / server.server_storage) * 100))
        : 0;
    const domainsPercent = server.max_domains
        ? Math.min(100, Math.round((server.current_domains / server.max_domains) * 100))
        : null;

    return (
        <div className="flex flex-col gap-6">
            <div className="-mt-4 flex items-center gap-2">
                <Badge variant={statusBadgeVariant(server.status)}>
                    {server.status_label ?? formatStatusLabel(server.status)}
                </Badge>
                <Badge variant={statusBadgeVariant(server.provisioning_status)}>
                    Provisioning: {formatStatusLabel(server.provisioning_status)}
                </Badge>
            </div>

            {server.is_trashed ? (
                <div className="flex flex-col gap-3 rounded-lg border border-destructive/30 bg-destructive/5 p-4 md:flex-row md:items-center md:justify-between">
                    <div className="flex items-center gap-3">
                        <Trash2Icon className="size-5 shrink-0 text-destructive" />
                        <div>
                            <p className="font-semibold text-foreground">This server is in trash</p>
                            <p className="text-sm text-muted-foreground">Restore it before running server operations.</p>
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button
                            className="bg-emerald-600 hover:bg-emerald-700"
                            disabled={processing}
                            onClick={() => openConfirm(
                                'Restore Server',
                                'Restore this server from trash?',
                                'Restore',
                                () => perform('patch', route('platform.servers.restore', server.id)),
                            )}
                        >
                            <RotateCcwIcon data-icon="inline-start" />
                            Restore
                        </Button>
                        <Button
                            variant="destructive"
                            disabled={processing}
                            onClick={() => openConfirm(
                                'Delete Permanently',
                                'Permanently delete this server record? This cannot be undone.',
                                'Delete Permanently',
                                () => perform('delete', route('platform.servers.force-delete', server.id)),
                                'destructive',
                            )}
                        >
                            <Trash2Icon data-icon="inline-start" />
                            Delete Permanently
                        </Button>
                    </div>
                </div>
            ) : null}

            <div className="grid gap-6 xl:grid-cols-[1fr_380px]">
                <Card>
                    <CardHeader>
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">Command Center</p>
                                <CardTitle className="mt-1 text-xl">{server.name}</CardTitle>
                                {server.fqdn ? (
                                    <a href={manageUrl ?? '#'} target="_blank" rel="noreferrer" className="text-sm font-semibold text-primary hover:underline">
                                        {server.fqdn}
                                    </a>
                                ) : (
                                    <p className="text-sm text-muted-foreground">No hostname configured</p>
                                )}
                            </div>
                            <div className="text-right text-sm">
                                <p className="text-muted-foreground">Server ID</p>
                                <p className="font-mono font-semibold">{server.uid ?? server.id}</p>
                                <p className="mt-1 text-muted-foreground">Last sync: {server.last_synced_at ?? 'Never'}</p>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-4">
                        <div className="grid grid-cols-2 gap-2 lg:grid-cols-4">
                            {[
                                { label: 'CPU', value: server.server_ccore ? `${server.server_ccore} Cores` : null },
                                {
                                    label: 'RAM',
                                    value: server.server_ram
                                        ? `${formatMemoryValue(server.server_ram_used)} / ${formatMemoryValue(server.server_ram)}`
                                        : null,
                                },
                                {
                                    label: 'Storage',
                                    value: server.server_storage
                                        ? `${formatStorageValue(server.server_storage_used)} / ${formatStorageValue(server.server_storage)}`
                                        : null,
                                },
                                {
                                    label: 'Domains',
                                    value: server.max_domains
                                        ? `${server.current_domains} / ${server.max_domains}`
                                        : `${server.current_domains} active`,
                                },
                            ].map((metric) => (
                                <div key={metric.label} className="rounded-lg border bg-muted/30 p-3">
                                    <p className="text-[0.7rem] font-semibold tracking-wide text-muted-foreground uppercase">{metric.label}</p>
                                    <p className="mt-0.5 text-sm font-bold text-foreground">{metric.value ?? '—'}</p>
                                </div>
                            ))}
                        </div>

                        <div className="flex flex-wrap gap-2">
                            <Badge variant={statusBadgeVariant(server.status)}>
                                {server.status_label ?? formatStatusLabel(server.status)}
                            </Badge>
                            <HealthChip label="SSH" status={server.has_ssh_credentials ? 'active' : 'failed'} />
                            <HealthChip label="Domains" status={domainsPercent !== null && domainsPercent > 85 ? 'maintenance' : 'provisioning'} />
                            <HealthChip label="SSL" status={server.acme_configured ? 'active' : 'failed'} />
                        </div>

                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="flex flex-col gap-2">
                                <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">Infrastructure</p>
                                <InfoRow label="IP"><span className="font-mono">{server.ip ?? '—'}</span></InfoRow>
                                <InfoRow label="Port"><span className="font-mono">{server.port ?? '—'}</span></InfoRow>
                                <InfoRow label="Provider">
                                    {server.provider_id && route().has('platform.providers.show') ? (
                                        <Link href={route('platform.providers.show', server.provider_id)} className="text-primary hover:underline">
                                            {server.provider_name ?? '—'}
                                        </Link>
                                    ) : (
                                        server.provider_name ?? '—'
                                    )}
                                </InfoRow>
                                <InfoRow label="Location">{server.location_label ?? '—'}</InfoRow>
                            </div>
                            <div className="flex flex-col gap-2">
                                <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">Software</p>
                                <InfoRow label="Operating System">{server.server_os ?? '—'}</InfoRow>
                                <InfoRow label="Hestia CP">{server.hestia_version ? `v${server.hestia_version}` : '—'}</InfoRow>
                                <InfoRow label="Astero">{server.astero_version ? `v${server.astero_version}` : '—'}</InfoRow>
                                <InfoRow label="ACME">
                                    {server.acme_configured ? (
                                        <span className="inline-flex items-center gap-1 text-emerald-600 dark:text-emerald-400">
                                            <ShieldCheckIcon className="size-4" />
                                            Configured
                                        </span>
                                    ) : (
                                        <span className="inline-flex items-center gap-1 text-destructive">
                                            <ShieldXIcon className="size-4" />
                                            Not setup
                                        </span>
                                    )}
                                </InfoRow>
                                <InfoRow label="Uptime">{server.server_uptime ?? '—'}</InfoRow>
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
                            High-impact actions for synchronization, provisioning, and maintenance.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-4">
                        {!server.is_trashed ? (
                            <>
                                <div className="grid grid-cols-2 gap-2">
                                    {manageUrl ? (
                                        <Button variant="outline" asChild>
                                            <a href={manageUrl} target="_blank" rel="noreferrer">
                                                <ExternalLinkIcon data-icon="inline-start" />
                                                Manage
                                            </a>
                                        </Button>
                                    ) : null}

                                    {isActive ? (
                                        <Button
                                            variant="outline"
                                            disabled={processing}
                                            onClick={() => openConfirm(
                                                'Sync Server',
                                                'Fetch the latest server information from Hestia?',
                                                'Sync',
                                                () => perform('post', route('platform.servers.sync-server', server.id)),
                                            )}
                                        >
                                            <RefreshCwIcon data-icon="inline-start" />
                                            Sync
                                        </Button>
                                    ) : null}

                                    {isActive ? (
                                        <Button
                                            variant="outline"
                                            disabled={processing}
                                            onClick={() => openConfirm(
                                                'Update Releases',
                                                'Download the latest release package to this server?',
                                                'Update',
                                                () => perform('post', route('platform.servers.update-releases', server.id)),
                                            )}
                                        >
                                            <DownloadCloudIcon data-icon="inline-start" />
                                            Releases
                                        </Button>
                                    ) : null}

                                    {isActive && server.has_ssh_credentials ? (
                                        <Button
                                            variant="outline"
                                            disabled={processing}
                                            onClick={() => openConfirm(
                                                'Update Scripts',
                                                'Upload the latest Astero scripts and templates via SSH?',
                                                'Update Scripts',
                                                () => perform('post', route('platform.servers.update-scripts', server.id)),
                                            )}
                                        >
                                            <ServerCogIcon data-icon="inline-start" />
                                            Scripts
                                        </Button>
                                    ) : null}

                                    {server.has_ssh_credentials && !isProvisioning && !isFailed ? (
                                        <Button
                                            disabled={processing}
                                            onClick={() => openConfirm(
                                                'Start Provisioning',
                                                'Install HestiaCP and Astero scripts on this server? This can take 15-30 minutes.',
                                                'Start Provisioning',
                                                () => perform('post', route('platform.servers.provision', server.id)),
                                            )}
                                        >
                                            <PlayCircleIcon data-icon="inline-start" />
                                            Provision
                                        </Button>
                                    ) : null}

                                    {isFailed ? (
                                        <Button
                                            className="bg-amber-600 hover:bg-amber-700"
                                            disabled={processing}
                                            onClick={() => openConfirm(
                                                'Retry Provisioning',
                                                'Retry the failed provisioning run for this server?',
                                                'Retry Provisioning',
                                                () => perform('post', route('platform.servers.retry-provisioning', server.id)),
                                            )}
                                        >
                                            <RefreshCwIcon data-icon="inline-start" />
                                            Retry
                                        </Button>
                                    ) : null}

                                    {isProvisioning ? (
                                        <Button
                                            variant="outline"
                                            className="border-destructive/30 text-destructive hover:bg-destructive/10"
                                            disabled={processing}
                                            onClick={() => openConfirm(
                                                'Stop Provisioning',
                                                'Stop the current provisioning run and mark the server as failed?',
                                                'Stop Provisioning',
                                                () => perform('post', route('platform.servers.stop-provisioning', server.id)),
                                                'destructive',
                                            )}
                                        >
                                            <PauseCircleIcon data-icon="inline-start" />
                                            Stop
                                        </Button>
                                    ) : null}

                                    {isActive && server.has_ssh_credentials && !server.acme_configured ? (
                                        <Button
                                            variant="outline"
                                            disabled={processing}
                                            onClick={() => openConfirm(
                                                'Setup ACME (SSL)',
                                                'Install acme.sh and configure SSL certificate automation on this server?',
                                                'Setup ACME',
                                                () => perform('post', route('platform.servers.setup-acme', server.id)),
                                            )}
                                        >
                                            <ShieldCheckIcon data-icon="inline-start" />
                                            Setup ACME
                                        </Button>
                                    ) : null}
                                </div>

                                <Separator />

                                <div className="flex flex-col gap-3">
                                    <div className="flex items-center justify-between text-xs text-muted-foreground">
                                        <span>RAM</span>
                                        <span>{ramPercent}%</span>
                                    </div>
                                    <div className="h-2.5 w-full overflow-hidden rounded-full bg-muted">
                                        <div className="h-full rounded-full bg-emerald-500 transition-all" style={{ width: `${ramPercent}%` }} />
                                    </div>
                                    <div className="flex items-center justify-between text-xs text-muted-foreground">
                                        <span>Storage</span>
                                        <span>{storagePercent}%</span>
                                    </div>
                                    <div className="h-2.5 w-full overflow-hidden rounded-full bg-muted">
                                        <div className="h-full rounded-full bg-sky-500 transition-all" style={{ width: `${storagePercent}%` }} />
                                    </div>
                                    <div className="flex items-center justify-between text-xs text-muted-foreground">
                                        <span>Domains</span>
                                        <span>{domainsPercent !== null ? `${domainsPercent}%` : 'Unlimited'}</span>
                                    </div>
                                </div>

                                <Separator />

                                <div className="grid grid-cols-2 gap-2">
                                    <div className="rounded-lg border bg-muted/20 p-3">
                                        <p className="text-[0.7rem] font-semibold tracking-wide text-muted-foreground uppercase">Websites</p>
                                        <p className="mt-0.5 text-sm font-bold text-foreground">{websiteCounts.total}</p>
                                    </div>
                                    <div className="rounded-lg border bg-muted/20 p-3">
                                        <p className="text-[0.7rem] font-semibold tracking-wide text-muted-foreground uppercase">Provisioning</p>
                                        <p className="mt-0.5 text-sm font-bold text-foreground">{websiteCounts.provisioning}</p>
                                    </div>
                                </div>

                                <Separator />

                                <div className="flex flex-wrap gap-2">
                                    <Button variant="outline" asChild>
                                        <Link href={route('platform.servers.edit', server.id)}>
                                            <PencilIcon data-icon="inline-start" />
                                            Edit
                                        </Link>
                                    </Button>
                                    <Button variant="outline" asChild>
                                        <Link href={route('platform.servers.index', { status: 'all' })}>
                                            <ArrowLeftIcon data-icon="inline-start" />
                                            Back
                                        </Link>
                                    </Button>
                                    <Button
                                        variant="destructive"
                                        disabled={processing}
                                        onClick={() => openConfirm(
                                            'Trash Server',
                                            'Move this server to trash?',
                                            'Trash Server',
                                            () => perform('delete', route('platform.servers.destroy', server.id)),
                                            'destructive',
                                        )}
                                    >
                                        <Trash2Icon data-icon="inline-start" />
                                        Trash
                                    </Button>
                                </div>
                            </>
                        ) : (
                            <p className="text-sm text-muted-foreground">Restore this server before running operations.</p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}