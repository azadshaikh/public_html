import { Link } from '@inertiajs/react';
import {
    ArrowLeftIcon,
    DatabaseIcon,
    DownloadCloudIcon,
    ExternalLinkIcon,
    PauseCircleIcon,
    PencilIcon,
    PlayCircleIcon,
    RefreshCwIcon,
    RotateCcwIcon,
    ServerIcon,
    TimerIcon,
    Trash2Icon,
    ZapIcon,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import type { WebsiteShowData } from '../../../types/platform';
import type { ConfirmState } from './show-shared';
import { HealthChip, InfoRow, statusBadgeVariant } from './show-shared';

type WebsiteShowOverviewProps = {
    website: WebsiteShowData;
    pullzoneId: string | null;
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

export function WebsiteShowOverview({
    website,
    pullzoneId,
    processing,
    openConfirm,
    perform,
}: WebsiteShowOverviewProps) {
    const isActive = website.status === 'active';
    const isProvisioning = website.status === 'provisioning';
    const isSuspended = website.status === 'suspended';
    const isExpired = website.status === 'expired';
    const isFailed = website.status === 'failed';
    const isDeleted = website.status === 'deleted';

    return (
        <div className="flex flex-col gap-6">
            <div className="-mt-4 flex items-center gap-2">
                <Badge variant={statusBadgeVariant(website.status)}>
                    {website.status_label}
                </Badge>
            </div>

            {website.has_update && isActive ? (
                <div className="flex items-center justify-between gap-4 rounded-lg border border-primary/20 bg-primary/5 p-4">
                    <div className="flex items-center gap-3">
                        <div className="flex size-10 items-center justify-center rounded-full bg-primary/10">
                            <DownloadCloudIcon className="size-5 text-primary" />
                        </div>
                        <div>
                            <p className="font-semibold text-foreground">Update Available</p>
                            <p className="text-sm text-muted-foreground">
                                v{website.astero_version} → v{website.server_version}
                            </p>
                        </div>
                    </div>
                    <Button
                        disabled={processing}
                        onClick={() => openConfirm(
                            'Update Website',
                            `Update from v${website.astero_version} to v${website.server_version}? This may take a few minutes.`,
                            'Update Now',
                            () => perform('post', route('platform.websites.update-version', website.id)),
                        )}
                    >
                        <RefreshCwIcon data-icon="inline-start" />
                        Update Now
                    </Button>
                </div>
            ) : null}

            {website.is_trashed && !isDeleted ? (
                <div className="flex flex-col gap-3 rounded-lg border border-destructive/30 bg-destructive/5 p-4 md:flex-row md:items-center md:justify-between">
                    <div className="flex items-center gap-3">
                        <Trash2Icon className="size-5 shrink-0 text-destructive" />
                        <div>
                            <p className="font-semibold text-foreground">This website is in trash</p>
                            <p className="text-sm text-muted-foreground">Restore it to make changes or remove from server.</p>
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button
                            variant="default"
                            className="bg-emerald-600 hover:bg-emerald-700"
                            disabled={processing}
                            onClick={() => openConfirm(
                                'Restore Website',
                                'Restore this website from trash?',
                                'Restore',
                                () => perform('patch', route('platform.websites.restore', website.id)),
                            )}
                        >
                            <RotateCcwIcon data-icon="inline-start" />
                            Restore
                        </Button>
                        <Button
                            variant="outline"
                            className="border-destructive/30 text-destructive hover:bg-destructive/10"
                            disabled={processing}
                            onClick={() => openConfirm(
                                'Remove from Server',
                                '⚠️ This will delete the Hestia user, files and database from the server. The website record will be kept for historical tracking.',
                                'Remove from Server',
                                () => perform('post', route('platform.websites.remove-from-server', website.id)),
                                'destructive',
                            )}
                        >
                            <ServerIcon data-icon="inline-start" />
                            Remove from Server
                        </Button>
                        <Button
                            variant="destructive"
                            disabled={processing}
                            onClick={() => openConfirm(
                                'Delete Permanently',
                                '⚠️ This will permanently delete the website record and cannot be undone. Make sure the server data has been removed first.',
                                'Delete Permanently',
                                () => perform('delete', route('platform.websites.force-delete', website.id)),
                                'destructive',
                            )}
                        >
                            <Trash2Icon data-icon="inline-start" />
                            Delete Permanently
                        </Button>
                    </div>
                </div>
            ) : null}

            {isDeleted ? (
                <div className="flex items-center justify-between gap-4 rounded-lg border border-destructive/30 bg-destructive/5 p-4">
                    <div className="flex items-center gap-3">
                        <ServerIcon className="size-5 shrink-0 text-destructive" />
                        <div>
                            <p className="font-semibold text-foreground">Server data has been removed</p>
                            <p className="text-sm text-muted-foreground">This website&apos;s server files have been deleted. You can re-provision it or permanently delete the record.</p>
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <Button
                            disabled={processing}
                            onClick={() => openConfirm(
                                'Re-provision Website',
                                'Create a fresh Hestia user and server files for this website? This will start the provisioning process again.',
                                'Re-provision',
                                () => perform('post', route('platform.websites.reprovision', website.id)),
                            )}
                        >
                            <RefreshCwIcon data-icon="inline-start" />
                            Re-provision
                        </Button>
                        <Button
                            variant="destructive"
                            disabled={processing}
                            onClick={() => openConfirm(
                                'Delete Permanently',
                                '⚠️ This will permanently delete this website record from the database. This cannot be undone!',
                                'Delete Forever',
                                () => perform('delete', route('platform.websites.force-delete', website.id)),
                                'destructive',
                            )}
                        >
                            <Trash2Icon data-icon="inline-start" />
                            Delete Forever
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
                                <CardTitle className="mt-1 text-xl">{website.name}</CardTitle>
                                {website.domain_url ? (
                                    <a
                                        href={website.domain_url}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="text-sm font-semibold text-primary hover:underline"
                                    >
                                        {website.domain}
                                    </a>
                                ) : (
                                    <p className="text-sm text-muted-foreground">{website.domain}</p>
                                )}
                            </div>
                            <div className="text-right text-sm">
                                <p className="text-muted-foreground">Website ID</p>
                                <p className="font-mono font-semibold">{website.uid ?? '—'}</p>
                                <p className="mt-1 text-muted-foreground">
                                    Last sync: {website.last_synced_at ?? 'Never'}
                                </p>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-4">
                        <div className="grid grid-cols-2 gap-2 lg:grid-cols-4">
                            {[
                                { label: 'Plan', value: website.plan },
                                { label: 'Type', value: website.type },
                                { label: 'Disk', value: website.disk_usage },
                                { label: 'Astero', value: website.astero_version ? `v${website.astero_version}` : null },
                            ].map((metric) => (
                                <div key={metric.label} className="rounded-lg border bg-muted/30 p-3">
                                    <p className="text-[0.7rem] font-semibold tracking-wide text-muted-foreground uppercase">{metric.label}</p>
                                    <p className="mt-0.5 text-sm font-bold text-foreground">{metric.value ?? '—'}</p>
                                </div>
                            ))}
                        </div>

                        <div className="flex flex-wrap gap-2">
                            <HealthChip label="Queue" status={website.queue_worker_status} />
                            <HealthChip label="Cron" status={website.cron_status} />
                            <Badge variant={statusBadgeVariant(website.status)}>
                                {website.status_label}
                            </Badge>
                        </div>

                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="flex flex-col gap-2">
                                <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">Infrastructure</p>
                                <InfoRow label="Server">
                                    {website.server_id && route().has('platform.servers.show') ? (
                                        <Link href={route('platform.servers.show', website.server_id)} className="text-primary hover:underline">
                                            {website.server_name ?? website.server_fqdn ?? '—'}
                                        </Link>
                                    ) : (
                                        website.server_name ?? '—'
                                    )}
                                </InfoRow>
                                <InfoRow label="IP">
                                    <span className="font-mono">{website.server_ip ?? '—'}</span>
                                </InfoRow>
                                <InfoRow label="DNS">{website.dns_provider_name ?? '—'}</InfoRow>
                                <InfoRow label="CDN">{website.cdn_provider_name ?? '—'}</InfoRow>
                            </div>
                            <div className="flex flex-col gap-2">
                                <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">Ownership</p>
                                <InfoRow label="Customer">{website.customer_name ?? 'Not assigned'}</InfoRow>
                                <InfoRow label="Agency">
                                    {website.agency_id && route().has('platform.agencies.show') ? (
                                        <Link href={route('platform.agencies.show', website.agency_id)} className="text-primary hover:underline">
                                            {website.agency_name}
                                        </Link>
                                    ) : (
                                        website.agency_name ?? 'Not assigned'
                                    )}
                                </InfoRow>
                                <InfoRow label="Pull Zone ID">
                                    <span className="font-mono">{pullzoneId ?? '—'}</span>
                                </InfoRow>
                                <InfoRow label="Expiry">{website.expired_on ?? '—'}</InfoRow>
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
                            High-impact actions for sync, state transitions, and lifecycle operations.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-4">
                        {!website.is_trashed ? (
                            <>
                                <div className="grid grid-cols-2 gap-2">
                                    {website.server_id ? (
                                        <Button
                                            variant="outline"
                                            disabled={processing}
                                            onClick={() => openConfirm(
                                                'Sync Website',
                                                'Fetch latest information from server?',
                                                'Sync',
                                                () => perform('post', route('platform.websites.sync-website', website.id)),
                                            )}
                                        >
                                            <RefreshCwIcon data-icon="inline-start" />
                                            Sync
                                        </Button>
                                    ) : null}

                                    {isActive && website.server_id ? (
                                        <Button
                                            variant="outline"
                                            disabled={processing}
                                            onClick={() => openConfirm(
                                                'Recache Application',
                                                'Run astero:recache on this website? This clears and rebuilds app caches.',
                                                'Recache',
                                                () => perform('post', route('platform.websites.recache-application', website.id)),
                                            )}
                                        >
                                            <DatabaseIcon data-icon="inline-start" />
                                            Recache
                                        </Button>
                                    ) : null}

                                    {isActive && website.admin_slug ? (
                                        <Button variant="outline" asChild>
                                            <a
                                                href={`https://${website.domain}/${website.admin_slug}`}
                                                target="_blank"
                                                rel="noreferrer"
                                            >
                                                <ExternalLinkIcon data-icon="inline-start" />
                                                Admin
                                            </a>
                                        </Button>
                                    ) : null}

                                    {isSuspended || isExpired || isProvisioning ? (
                                        <Button
                                            variant="outline"
                                            className="border-emerald-300 text-emerald-700 hover:bg-emerald-50 dark:border-emerald-700 dark:text-emerald-400 dark:hover:bg-emerald-950"
                                            disabled={processing}
                                            onClick={() => openConfirm(
                                                'Activate Website',
                                                'Activate this website and make it publicly accessible?',
                                                'Activate',
                                                () => perform('post', route('platform.websites.update-status', [website.id, 'active'])),
                                            )}
                                        >
                                            <PlayCircleIcon data-icon="inline-start" />
                                            Activate
                                        </Button>
                                    ) : null}

                                    {isActive ? (
                                        <Button
                                            variant="outline"
                                            className="border-amber-300 text-amber-700 hover:bg-amber-50 dark:border-amber-700 dark:text-amber-400 dark:hover:bg-amber-950"
                                            disabled={processing}
                                            onClick={() => openConfirm(
                                                'Suspend Website',
                                                'Suspend this website? It will become temporarily inaccessible.',
                                                'Suspend',
                                                () => perform('post', route('platform.websites.update-status', [website.id, 'suspended'])),
                                                'destructive',
                                            )}
                                        >
                                            <PauseCircleIcon data-icon="inline-start" />
                                            Suspend
                                        </Button>
                                    ) : null}

                                    {isActive || isSuspended ? (
                                        <Button
                                            variant="outline"
                                            disabled={processing}
                                            onClick={() => openConfirm(
                                                'Expire Website',
                                                'Mark this website as expired?',
                                                'Expire',
                                                () => perform('post', route('platform.websites.update-status', [website.id, 'expired'])),
                                                'destructive',
                                            )}
                                        >
                                            <TimerIcon data-icon="inline-start" />
                                            Expire
                                        </Button>
                                    ) : null}

                                    <Button
                                        variant="outline"
                                        className="border-destructive/30 text-destructive hover:bg-destructive/10"
                                        disabled={processing}
                                        onClick={() => openConfirm(
                                            'Move to Trash',
                                            'Move this website to trash? You can restore it later.',
                                            'Move to Trash',
                                            () => perform('delete', route('platform.websites.destroy', website.id)),
                                            'destructive',
                                        )}
                                    >
                                        <Trash2Icon data-icon="inline-start" />
                                        Trash
                                    </Button>
                                </div>

                                {website.has_update && isActive ? (
                                    <Button
                                        disabled={processing}
                                        onClick={() => openConfirm(
                                            'Update Website',
                                            `Update from v${website.astero_version} to v${website.server_version}? This may take a few minutes.`,
                                            'Update Now',
                                            () => perform('post', route('platform.websites.update-version', website.id)),
                                        )}
                                    >
                                        <DownloadCloudIcon data-icon="inline-start" />
                                        Update to v{website.server_version}
                                    </Button>
                                ) : null}
                            </>
                        ) : (
                            <div className="rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-300">
                                This website is in trash mode. Use restore/remove actions from the warning banner.
                            </div>
                        )}

                        <Separator />

                        <div className="flex flex-col gap-2">
                            <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">Queue Workers</p>
                            <div className="flex items-center justify-between">
                                <span className="text-sm text-muted-foreground">Current status</span>
                                <HealthChip label="" status={website.queue_worker_status} />
                            </div>
                            {isActive && website.queue_worker_status === 'not_configured' ? (
                                <Button
                                    variant="outline"
                                    className="w-full"
                                    disabled={processing}
                                    onClick={() => openConfirm(
                                        'Setup Queue Workers',
                                        'Setup Supervisor queue workers for this website?',
                                        'Setup',
                                        () => perform('post', route('platform.websites.setup-queue-worker', website.id)),
                                    )}
                                >
                                    Setup Queue Workers
                                </Button>
                            ) : null}
                            {isActive
                                && website.queue_worker_status !== 'not_configured'
                                && website.queue_worker_status !== 'not_installed'
                                && website.server_id ? (
                                <>
                                    <p className="text-xs text-muted-foreground">Scale workers</p>
                                    <div className="grid grid-cols-4 gap-1.5">
                                        {[1, 2, 3, 4].map((count) => (
                                            <Button
                                                key={count}
                                                variant={website.queue_worker_total === count ? 'default' : 'outline'}
                                                size="sm"
                                                disabled={processing}
                                                onClick={() => openConfirm(
                                                    'Scale Queue Workers',
                                                    `Change queue workers from ${website.queue_worker_total} to ${count}?`,
                                                    'Scale',
                                                    () => perform('post', route('platform.websites.scale-queue-worker', { website: website.id, count })),
                                                )}
                                            >
                                                {count}
                                            </Button>
                                        ))}
                                    </div>
                                </>
                            ) : null}
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div className="flex flex-wrap items-center gap-2">
                {isFailed ? (
                    <Button
                        variant="default"
                        className="bg-amber-600 hover:bg-amber-700"
                        disabled={processing}
                        onClick={() => openConfirm(
                            'Retry Provisioning',
                            'This will retry the provisioning process for this website. Failed and pending steps will be retried.',
                            'Retry',
                            () => perform('post', route('platform.websites.retry-provision', website.id)),
                        )}
                    >
                        <RefreshCwIcon data-icon="inline-start" />
                        Retry Provision
                    </Button>
                ) : null}

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

                <Button variant="outline" asChild>
                    <Link href={route('platform.websites.edit', website.id)}>
                        <PencilIcon data-icon="inline-start" />
                        Edit
                    </Link>
                </Button>

                <Button variant="outline" asChild>
                    <Link href={route('platform.websites.index', { status: 'all' })}>
                        <ArrowLeftIcon data-icon="inline-start" />
                        Back
                    </Link>
                </Button>
            </div>
        </div>
    );
}