import { Link, router, useHttp } from '@inertiajs/react';
import {
    ActivityIcon,
    ArrowLeftIcon,
    CheckCircleIcon,
    ClipboardCopyIcon,
    ClockIcon,
    CodeIcon,
    DatabaseIcon,
    DownloadCloudIcon,
    ExternalLinkIcon,
    EyeIcon,
    EyeOffIcon,
    FileTextIcon,
    InfoIcon,
    KeyRoundIcon,
    ListChecksIcon,
    PauseCircleIcon,
    PencilIcon,
    PlayCircleIcon,
    RefreshCwIcon,
    RotateCcwIcon,
    ServerIcon,
    StickyNoteIcon,
    TimerIcon,
    Trash2Icon,
    ZapIcon,
} from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { showAppToast } from '@/components/forms/form-success-toast';
import PasswordInput from '@/components/password-input';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { ConfirmationDialog } from '@/components/ui/confirmation-dialog';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import {
    Tabs,
    TabsContent,
    TabsList,
    TabsTrigger,
} from '@/components/ui/tabs';
import { useIsMobile } from '@/hooks/use-mobile';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import type {
    PlatformActivity,
    ProvisioningRunTimestamps,
    WebsiteProvisioningStep,
    WebsiteSecretItem,
    WebsiteShowData,
    WebsiteUpdateItem,
} from '../../../types/platform';
import { WebsiteEnvTab } from './components/website-env-tab';
import { WebsiteLaravelLogTab } from './components/website-laravel-log-tab';
import { WebsiteProvisioningDnsInstructions } from './components/website-provisioning-dns-instructions';

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
const PROVISIONING_POLL_INTERVAL_LABEL = 'every 10 seconds';

function getVerifyDnsStep(steps: WebsiteProvisioningStep[]): WebsiteProvisioningStep | undefined {
    return steps.find((step) => step.key === 'verify_dns');
}

function shouldPollProvisioningState(status: string | null, steps: WebsiteProvisioningStep[]): boolean {
    if (status === 'provisioning') {
        return true;
    }

    return status === 'waiting_for_dns'
        && Boolean(getVerifyDnsStep(steps)?.dns_validation?.confirmed_by_user);
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

const STATUS_BADGE_VARIANT: Record<string, 'success' | 'warning' | 'info' | 'danger' | 'secondary'> = {
    active: 'success',
    provisioning: 'info',
    waiting_for_dns: 'warning',
    suspended: 'warning',
    expired: 'danger',
    failed: 'danger',
    trash: 'danger',
    deleted: 'danger',
};

const STEP_STATUS_VARIANT: Record<string, 'success' | 'warning' | 'info' | 'danger' | 'secondary'> = {
    done: 'success',
    failed: 'danger',
    reverted: 'info',
    pending: 'warning',
};

function statusBadgeVariant(status: string | null): 'success' | 'warning' | 'info' | 'danger' | 'secondary' {
    return STATUS_BADGE_VARIANT[status ?? ''] ?? 'secondary';
}

function HealthChip({ label, status }: { label: string; status: string | null }) {
    const display = status ? status.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase()) : 'Unknown';
    const variant = statusBadgeVariant(
        status === 'running' || status === 'active' ? 'active' :
        status === 'stopped' || status === 'not_running' || status === 'degraded' ? 'suspended' :
        status === 'error' ? 'failed' :
        'provisioning',
    );
    return (
        <Badge variant={variant}>
            {label}: {display}
        </Badge>
    );
}

function InfoRow({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div className="flex items-center justify-between gap-2 text-sm">
            <span className="text-muted-foreground">{label}</span>
            <span className="font-medium text-foreground">{children}</span>
        </div>
    );
}

// ---------------------------------------------------------------------------
// Operation actions hook
// ---------------------------------------------------------------------------

type ConfirmState = {
    open: boolean;
    title: string;
    description: string;
    confirmLabel: string;
    tone: 'default' | 'destructive';
    action: () => void;
};

const INITIAL_CONFIRM: ConfirmState = {
    open: false,
    title: '',
    description: '',
    confirmLabel: 'Confirm',
    tone: 'default',
    action: () => {},
};

function useOperationAction() {
    const request = useHttp<Record<string, never>, { status?: string; message?: string }>({});

    function perform(
        method: 'post' | 'delete' | 'patch',
        url: string,
        {
            onSuccess,
            onError,
        }: {
            onSuccess?: (msg: string) => void;
            onError?: (msg: string) => void;
        } = {},
    ) {
        const options = {
            headers: { Accept: 'application/json' } as Record<string, string>,
            preserveScroll: true,
            onSuccess: (response: { status?: string; message?: string } | null) => {
                const msg = response?.message ?? 'Operation completed successfully.';
                const variant = response?.status === 'error'
                    ? 'error' as const
                    : response?.status === 'info'
                        ? 'info' as const
                        : 'success' as const;

                if (response?.status === 'error') {
                    if (onError) {
                        onError(msg);
                    } else {
                        showAppToast({ variant, title: msg });
                    }

                    return;
                }

                if (onSuccess) {
                    onSuccess(msg);
                } else {
                    showAppToast({ variant, title: msg });
                    router.reload();
                }
            },
            onError: (errors: Record<string, string>) => {
                const msg = (errors as unknown as { message?: string })?.message
                    ?? 'Operation failed. Please try again.';

                if (onError) {
                    onError(msg);
                } else {
                    showAppToast({ variant: 'error', title: msg });
                }
            },
        };

        if (method === 'post') {
            void request.post(url, options);
        } else if (method === 'delete') {
            void request.delete(url, options);
        } else {
            void request.patch(url, options);
        }
    }

    return { processing: request.processing, perform };
}

// ---------------------------------------------------------------------------
// Page component
// ---------------------------------------------------------------------------

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
    const op = useOperationAction();
    const isMobile = useIsMobile();

    const isActive = website.status === 'active';
    const isProvisioning = website.status === 'provisioning';
    const isSuspended = website.status === 'suspended';
    const isExpired = website.status === 'expired';
    const isFailed = website.status === 'failed';
    const isDeleted = website.status === 'deleted';

    const defaultTab = isProvisioning || isFailed ? 'provision' : 'general';
    const [activeTab, setActiveTab] = useState(defaultTab);

    function openConfirm(
        title: string,
        description: string,
        confirmLabel: string,
        action: () => void,
        tone: 'default' | 'destructive' = 'default',
    ) {
        setConfirm({ open: true, title, description, confirmLabel, tone, action });
    }

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Platform', href: route('platform.websites.index', { status: 'all' }) },
        { title: 'Websites', href: route('platform.websites.index', { status: 'all' }) },
        { title: `#${website.id}`, href: route('platform.websites.show', website.id) },
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
                            onClick={() => openConfirm(
                                'Retry Provisioning',
                                'This will retry the provisioning process for this website. Failed and pending steps will be retried.',
                                'Retry',
                                () => op.perform('post', route('platform.websites.retry-provision', website.id)),
                            )}
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
                        <Link href={route('platform.websites.index', { status: 'all' })}>
                            <ArrowLeftIcon data-icon="inline-start" />
                            Back
                        </Link>
                    </Button>
                </div>
            }
        >
            <div className="flex flex-col gap-6">
                {/* Status badge below title */}
                <div className="-mt-4 flex items-center gap-2">
                    <Badge variant={statusBadgeVariant(website.status)}>
                        {website.status_label}
                    </Badge>
                </div>

                {/* Update available banner */}
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
                            disabled={op.processing}
                            onClick={() => openConfirm(
                                'Update Website',
                                `Update from v${website.astero_version} to v${website.server_version}? This may take a few minutes.`,
                                'Update Now',
                                () => op.perform('post', route('platform.websites.update-version', website.id)),
                            )}
                        >
                            <RefreshCwIcon data-icon="inline-start" />
                            Update Now
                        </Button>
                    </div>
                ) : null}

                {/* Trashed warning banner */}
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
                                disabled={op.processing}
                                onClick={() => openConfirm(
                                    'Restore Website',
                                    'Restore this website from trash?',
                                    'Restore',
                                    () => op.perform('patch', route('platform.websites.restore', website.id)),
                                )}
                            >
                                <RotateCcwIcon data-icon="inline-start" />
                                Restore
                            </Button>
                            <Button
                                variant="outline"
                                className="border-destructive/30 text-destructive hover:bg-destructive/10"
                                disabled={op.processing}
                                onClick={() => openConfirm(
                                    'Remove from Server',
                                    '⚠️ This will delete the Hestia user, files and database from the server. The website record will be kept for historical tracking.',
                                    'Remove from Server',
                                    () => op.perform('post', route('platform.websites.remove-from-server', website.id)),
                                    'destructive',
                                )}
                            >
                                <ServerIcon data-icon="inline-start" />
                                Remove from Server
                            </Button>
                            <Button
                                variant="destructive"
                                disabled={op.processing}
                                onClick={() => openConfirm(
                                    'Delete Permanently',
                                    '⚠️ This will permanently delete the website record and cannot be undone. Make sure the server data has been removed first.',
                                    'Delete Permanently',
                                    () => op.perform('delete', route('platform.websites.force-delete', website.id)),
                                    'destructive',
                                )}
                            >
                                <Trash2Icon data-icon="inline-start" />
                                Delete Permanently
                            </Button>
                        </div>
                    </div>
                ) : null}

                {/* Deleted (server removed) banner */}
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
                                disabled={op.processing}
                                onClick={() => openConfirm(
                                    'Re-provision Website',
                                    'Create a fresh Hestia user and server files for this website? This will start the provisioning process again.',
                                    'Re-provision',
                                    () => op.perform('post', route('platform.websites.reprovision', website.id)),
                                )}
                            >
                                <RefreshCwIcon data-icon="inline-start" />
                                Re-provision
                            </Button>
                            <Button
                                variant="destructive"
                                disabled={op.processing}
                                onClick={() => openConfirm(
                                    'Delete Permanently',
                                    '⚠️ This will permanently delete this website record from the database. This cannot be undone!',
                                    'Delete Forever',
                                    () => op.perform('delete', route('platform.websites.force-delete', website.id)),
                                    'destructive',
                                )}
                            >
                                <Trash2Icon data-icon="inline-start" />
                                Delete Forever
                            </Button>
                        </div>
                    </div>
                ) : null}

                {/* Command Center + Operations */}
                <div className="grid gap-6 xl:grid-cols-[1fr_380px]">
                    {/* Command Center */}
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
                                    <p className="font-semibold font-mono">{website.uid ?? '—'}</p>
                                    <p className="mt-1 text-muted-foreground">
                                        Last sync: {website.last_synced_at ?? 'Never'}
                                    </p>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-4">
                            {/* Metric boxes */}
                            <div className="grid grid-cols-2 gap-2 lg:grid-cols-4">
                                {[
                                    { label: 'Plan', value: website.plan },
                                    { label: 'Type', value: website.type },
                                    { label: 'Disk', value: website.disk_usage },
                                    { label: 'Astero', value: website.astero_version ? `v${website.astero_version}` : null },
                                ].map((m) => (
                                    <div key={m.label} className="rounded-lg border bg-muted/30 p-3">
                                        <p className="text-[0.7rem] font-semibold tracking-wide text-muted-foreground uppercase">{m.label}</p>
                                        <p className="mt-0.5 text-sm font-bold text-foreground">{m.value ?? '—'}</p>
                                    </div>
                                ))}
                            </div>

                            {/* Health chips */}
                            <div className="flex flex-wrap gap-2">
                                <HealthChip label="Queue" status={website.queue_worker_status} />
                                <HealthChip label="Cron" status={website.cron_status} />
                                <Badge variant={statusBadgeVariant(website.status)}>
                                    {website.status_label}
                                </Badge>
                            </div>

                            <div className="rounded-lg border bg-muted/20 p-4">
                                <div className="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                                    <div className="space-y-2">
                                        <div>
                                            <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">Primary Hostname</p>
                                            <p className="mt-1 text-sm font-semibold text-foreground">{website.primary_hostname ?? website.domain}</p>
                                        </div>
                                        {website.supports_www_feature ? (
                                            <p className="text-sm text-muted-foreground">
                                                Switch the public primary host between the apex domain and <span className="font-medium text-foreground">www</span>. This also re-checks the Hestia redirect and Bunny hostname setup.
                                            </p>
                                        ) : (
                                            <p className="text-sm text-muted-foreground">
                                                This website uses a subdomain, so the www primary-host feature is not available.
                                            </p>
                                        )}
                                        {website.alternate_hostname ? (
                                            <p className="text-xs text-muted-foreground">
                                                Alternate host: <span className="font-medium text-foreground">{website.alternate_hostname}</span>
                                            </p>
                                        ) : null}
                                    </div>

                                    {website.supports_www_feature ? (
                                        <div className="grid grid-cols-2 gap-2 md:min-w-64">
                                            <Button
                                                variant={website.is_www ? 'outline' : 'default'}
                                                disabled={op.processing || !website.is_www}
                                                onClick={() => openConfirm(
                                                    'Use apex domain as primary host',
                                                    'This will switch the primary host to the non-www domain and reconcile Hestia and Bunny state if anything is missing.',
                                                    'Use apex domain',
                                                    () => op.perform('post', route('platform.websites.update-primary-host', { website: website.id, hostnameType: 'apex' })),
                                                )}
                                            >
                                                Use {website.domain}
                                            </Button>
                                            <Button
                                                variant={website.is_www ? 'default' : 'outline'}
                                                disabled={op.processing || website.is_www}
                                                onClick={() => openConfirm(
                                                    'Use www as primary host',
                                                    'This will switch the primary host to the www hostname and reconcile Hestia and Bunny state if anything is missing.',
                                                    'Use www',
                                                    () => op.perform('post', route('platform.websites.update-primary-host', { website: website.id, hostnameType: 'www' })),
                                                )}
                                            >
                                                Use www
                                            </Button>
                                        </div>
                                    ) : null}
                                </div>
                            </div>

                            {/* Infrastructure + Ownership */}
                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="flex flex-col gap-2">
                                    <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">Infrastructure</p>
                                    <InfoRow label="Server">
                                        {website.server_id && route().has('platform.servers.show') ? (
                                            <Link
                                                href={route('platform.servers.show', website.server_id)}
                                                className="text-primary hover:underline"
                                            >
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
                                            <Link
                                                href={route('platform.agencies.show', website.agency_id)}
                                                className="text-primary hover:underline"
                                            >
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

                            <div className="rounded-lg border bg-muted/20 p-4">
                                <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">Shared SSL</p>
                                {website.ssl_summary ? (
                                    <div className="mt-3 flex flex-col gap-2">
                                        <InfoRow label="Certificate">
                                            {website.ssl_summary.certificate_href ? (
                                                <Link href={website.ssl_summary.certificate_href} className="text-primary hover:underline">
                                                    {website.ssl_summary.certificate_name}
                                                </Link>
                                            ) : (
                                                website.ssl_summary.certificate_name
                                            )}
                                        </InfoRow>
                                        <InfoRow label="Root Domain">
                                            {website.ssl_summary.domain_href && website.ssl_summary.domain_name ? (
                                                <Link href={website.ssl_summary.domain_href} className="text-primary hover:underline">
                                                    {website.ssl_summary.domain_name}
                                                </Link>
                                            ) : (
                                                website.ssl_summary.domain_name ?? '—'
                                            )}
                                        </InfoRow>
                                        <InfoRow label="Expires">{website.ssl_summary.expires_at ?? '—'}</InfoRow>
                                        <InfoRow label="Used By">
                                            {website.ssl_summary.websites_count} website{website.ssl_summary.websites_count === 1 ? '' : 's'}
                                        </InfoRow>
                                        <div className="pt-2">
                                            <p className="mb-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                                Websites using this certificate
                                            </p>
                                            <div className="flex flex-wrap gap-2">
                                                {website.ssl_summary.websites.map((linkedWebsite) => (
                                                    <Link
                                                        key={linkedWebsite.id}
                                                        href={linkedWebsite.href}
                                                        className="rounded-full border px-3 py-1 text-xs font-medium text-foreground transition hover:border-primary/40 hover:bg-background"
                                                    >
                                                        {linkedWebsite.domain}
                                                    </Link>
                                                ))}
                                            </div>
                                        </div>
                                    </div>
                                ) : (
                                    <p className="mt-2 text-sm text-muted-foreground">
                                        No domain SSL certificate is linked to this website yet.
                                    </p>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    {/* Operations sidebar */}
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
                                                disabled={op.processing}
                                                onClick={() => openConfirm(
                                                    'Sync Website',
                                                    'Fetch latest information from server?',
                                                    'Sync',
                                                    () => op.perform('post', route('platform.websites.sync-website', website.id)),
                                                )}
                                            >
                                                <RefreshCwIcon data-icon="inline-start" />
                                                Sync
                                            </Button>
                                        ) : null}

                                        {isActive && website.server_id ? (
                                            <Button
                                                variant="outline"
                                                disabled={op.processing}
                                                onClick={() => openConfirm(
                                                    'Recache Application',
                                                    'Run astero:recache on this website? This clears and rebuilds app caches.',
                                                    'Recache',
                                                    () => op.perform('post', route('platform.websites.recache-application', website.id)),
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
                                                disabled={op.processing}
                                                onClick={() => openConfirm(
                                                    'Activate Website',
                                                    'Activate this website and make it publicly accessible?',
                                                    'Activate',
                                                    () => op.perform('post', route('platform.websites.update-status', [website.id, 'active'])),
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
                                                disabled={op.processing}
                                                onClick={() => openConfirm(
                                                    'Suspend Website',
                                                    'Suspend this website? It will become temporarily inaccessible.',
                                                    'Suspend',
                                                    () => op.perform('post', route('platform.websites.update-status', [website.id, 'suspended'])),
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
                                                disabled={op.processing}
                                                onClick={() => openConfirm(
                                                    'Expire Website',
                                                    'Mark this website as expired?',
                                                    'Expire',
                                                    () => op.perform('post', route('platform.websites.update-status', [website.id, 'expired'])),
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
                                            disabled={op.processing}
                                            onClick={() => openConfirm(
                                                'Move to Trash',
                                                'Move this website to trash? You can restore it later.',
                                                'Move to Trash',
                                                () => op.perform('delete', route('platform.websites.destroy', website.id)),
                                                'destructive',
                                            )}
                                        >
                                            <Trash2Icon data-icon="inline-start" />
                                            Trash
                                        </Button>
                                    </div>

                                    {/* Update button */}
                                    {website.has_update && isActive ? (
                                        <Button
                                            disabled={op.processing}
                                            onClick={() => openConfirm(
                                                'Update Website',
                                                `Update from v${website.astero_version} to v${website.server_version}? This may take a few minutes.`,
                                                'Update Now',
                                                () => op.perform('post', route('platform.websites.update-version', website.id)),
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

                            {/* Queue Workers */}
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
                                        disabled={op.processing}
                                        onClick={() => openConfirm(
                                            'Setup Queue Workers',
                                            'Setup Supervisor queue workers for this website?',
                                            'Setup',
                                            () => op.perform('post', route('platform.websites.setup-queue-worker', website.id)),
                                        )}
                                    >
                                        Setup Queue Workers
                                    </Button>
                                ) : null}
                                {isActive &&
                                    website.queue_worker_status !== 'not_configured' &&
                                    website.queue_worker_status !== 'not_installed' &&
                                    website.server_id ? (
                                    <>
                                        <p className="text-xs text-muted-foreground">Scale workers</p>
                                        <div className="grid grid-cols-4 gap-1.5">
                                            {[1, 2, 3, 4].map((count) => (
                                                <Button
                                                    key={count}
                                                    variant={website.queue_worker_total === count ? 'default' : 'outline'}
                                                    size="sm"
                                                    disabled={op.processing}
                                                    onClick={() => openConfirm(
                                                        'Scale Queue Workers',
                                                        `Change queue workers from ${website.queue_worker_total} to ${count}?`,
                                                        'Scale',
                                                        () => op.perform('post', route('platform.websites.scale-queue-worker', { website: website.id, count })),
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

                {/* Tabs section */}
                <Tabs
                    value={activeTab}
                    onValueChange={setActiveTab}
                    size="comfortable"
                    className="min-w-0 flex-1 flex-col"
                    orientation={isMobile ? 'vertical' : 'horizontal'}
                >
                    <TabsList
                        className={cn(
                            'w-full md:w-fit',
                            !isMobile && 'min-w-0 overflow-x-auto pr-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden',
                        )}
                    >
                        <TabsTrigger value="general" className={cn(!isMobile && 'shrink-0')}>
                            <InfoIcon data-icon="inline-start" />
                            General
                        </TabsTrigger>
                        {secrets.length > 0 ? (
                            <TabsTrigger value="secrets" className={cn(!isMobile && 'shrink-0')}>
                                <KeyRoundIcon data-icon="inline-start" />
                                <span>Secrets</span>
                                <Badge variant="secondary" className="rounded-full px-1.5 py-0 text-[0.7rem]">{secrets.length}</Badge>
                            </TabsTrigger>
                        ) : null}
                        <TabsTrigger value="provision" className={cn(!isMobile && 'shrink-0')}>
                            <ListChecksIcon data-icon="inline-start" />
                            Provision
                        </TabsTrigger>
                        <TabsTrigger value="updates" className={cn(!isMobile && 'shrink-0')}>
                            <ClockIcon data-icon="inline-start" />
                            Updates
                        </TabsTrigger>
                        <TabsTrigger value="notes" className={cn(!isMobile && 'shrink-0')}>
                            <StickyNoteIcon data-icon="inline-start" />
                            Notes
                        </TabsTrigger>
                        <TabsTrigger value="metadata" className={cn(!isMobile && 'shrink-0')}>
                            <CodeIcon data-icon="inline-start" />
                            Metadata
                        </TabsTrigger>
                        <TabsTrigger value="env" className={cn(!isMobile && 'shrink-0')}>
                            <ClipboardCopyIcon data-icon="inline-start" />
                            Env
                        </TabsTrigger>
                        <TabsTrigger value="logs" className={cn(!isMobile && 'shrink-0')}>
                            <FileTextIcon data-icon="inline-start" />
                            Logs
                        </TabsTrigger>
                        <TabsTrigger value="activity" className={cn(!isMobile && 'shrink-0')}>
                            <ActivityIcon data-icon="inline-start" />
                            Activity
                        </TabsTrigger>
                    </TabsList>

                    {/* General tab */}
                    <TabsContent value="general">
                        <Card>
                            <CardContent className="pt-6">
                                <div className="grid gap-6 md:grid-cols-2">
                                    <div>
                                        <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase mb-3">Business Niches</p>
                                        {website.niches.length > 0 ? (
                                            <div className="flex flex-wrap gap-2">
                                                {website.niches.map((niche) => (
                                                    <Badge key={niche} variant="default">{niche}</Badge>
                                                ))}
                                            </div>
                                        ) : (
                                            <p className="text-sm text-muted-foreground">—</p>
                                        )}
                                    </div>
                                    <div>
                                        <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase mb-3">Timestamps</p>
                                        <div className="flex flex-col gap-2">
                                            <InfoRow label="Created">{website.created_at ?? '—'}</InfoRow>
                                            <InfoRow label="Updated">{website.updated_at ?? '—'}</InfoRow>
                                            <InfoRow label="Last Synced">{website.last_synced_at ?? 'Never'}</InfoRow>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* Secrets tab */}
                    {secrets.length > 0 ? (
                        <TabsContent value="secrets">
                            <Card>
                                <CardContent className="pt-6">
                                    <SecretsTable websiteId={website.id} secrets={secrets} canReveal={canRevealSecrets} />
                                </CardContent>
                            </Card>
                        </TabsContent>
                    ) : null}

                    {/* Provision tab */}
                    <TabsContent value="provision">
                        <Card>
                            <CardContent className="pt-6">
                                <ProvisioningStepsTable
                                    websiteId={website.id}
                                    steps={provisioningSteps}
                                    provisioningRun={provisioningRun}
                                    isProvisioning={isProvisioning}
                                    websiteStatus={website.status}
                                />
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* Updates tab */}
                    <TabsContent value="updates">
                        <Card>
                            <CardContent className="pt-6">
                                {updates.length === 0 ? (
                                    <div className="py-10 text-center text-muted-foreground">
                                        <ClockIcon className="mx-auto mb-3 size-8 opacity-50" />
                                        <p>No sync data available yet.</p>
                                    </div>
                                ) : (
                                    <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                                        {updates.map((update) => (
                                            <div key={update.key} className="rounded-lg border bg-muted/30 p-3">
                                                <p className="text-[0.7rem] font-semibold tracking-wide text-muted-foreground uppercase">{update.label}</p>
                                                <p className="mt-0.5 text-sm font-medium text-foreground">{update.value}</p>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* Notes tab */}
                    <TabsContent value="notes">
                        <Card>
                            <CardContent className="pt-6">
                                <p className="text-sm text-muted-foreground">Notes functionality will be available here.</p>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* Metadata tab */}
                    <TabsContent value="metadata">
                        <Card>
                            <CardContent className="pt-6">
                                {updates.length === 0 ? (
                                    <div className="py-10 text-center text-muted-foreground">
                                        <CodeIcon className="mx-auto mb-3 size-8 opacity-50" />
                                        <p>No metadata stored for this website.</p>
                                    </div>
                                ) : (
                                    <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                                        {updates.map((update) => (
                                            <div key={update.key} className="rounded-lg border bg-muted/30 p-3">
                                                <p className="text-[0.7rem] font-semibold tracking-wide text-muted-foreground uppercase">{update.label}</p>
                                                <p className="mt-0.5 text-sm font-medium text-foreground break-all">{update.value}</p>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="logs">
                        <Card>
                            <CardContent className="pt-6">
                                <WebsiteLaravelLogTab
                                    websiteId={website.id}
                                    active={activeTab === 'logs'}
                                    canManageLaravelLog={canManageLaravelLog}
                                />
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="env">
                        <Card>
                            <CardContent className="pt-6">
                                <WebsiteEnvTab
                                    websiteId={website.id}
                                    active={activeTab === 'env'}
                                    canManageWebsiteEnv={canManageWebsiteEnv}
                                />
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* Activity tab */}
                    <TabsContent value="activity">
                        <Card>
                            <CardContent className="pt-6">
                                {activities.length === 0 ? (
                                    <div className="py-10 text-center text-muted-foreground">
                                        <ActivityIcon className="mx-auto mb-3 size-8 opacity-50" />
                                        <p>No activity logs found for this website.</p>
                                    </div>
                                ) : (
                                    <div className="flex flex-col gap-3">
                                        {activities.map((activity) => (
                                            <div key={activity.id} className="flex items-start justify-between gap-4 rounded-lg border p-3">
                                                <div>
                                                    <p className="text-sm font-medium text-foreground">{activity.description}</p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {activity.causer_name ? `${activity.causer_name} · ` : ''}
                                                        {activity.created_at ?? 'Unknown time'}
                                                    </p>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>
            </div>

            {/* Confirmation dialog */}
            <ConfirmationDialog
                open={confirm.open}
                onOpenChange={(open) => setConfirm((prev) => ({ ...prev, open }))}
                title={confirm.title}
                description={confirm.description}
                confirmLabel={confirm.confirmLabel}
                tone={confirm.tone}
                confirmVariant={confirm.tone === 'destructive' ? 'destructive' : 'default'}
                onConfirm={confirm.action}
            />
        </AppLayout>
    );
}

// ---------------------------------------------------------------------------
// Secrets table sub-component
// ---------------------------------------------------------------------------

function SecretsTable({
    websiteId,
    secrets,
    canReveal,
}: {
    websiteId: number;
    secrets: WebsiteSecretItem[];
    canReveal: boolean;
}) {
    const [revealedValues, setRevealedValues] = useState<Record<number, string>>({});
    const [revealingId, setRevealingId] = useState<number | null>(null);
    const [passwordModalOpen, setPasswordModalOpen] = useState(false);
    const [pendingSecretId, setPendingSecretId] = useState<number | null>(null);
    const [password, setPassword] = useState('');
    const [passwordError, setPasswordError] = useState('');
    const passwordInputRef = useRef<HTMLInputElement>(null);
    const revealRequest = useHttp<{ password: string }, { success?: boolean; value?: string }>({
        password: '',
    });

    function requestReveal(secretId: number) {
        if (revealedValues[secretId] !== undefined) {
            setRevealedValues((prev) => {
                const next = { ...prev };
                delete next[secretId];
                return next;
            });
            return;
        }

        setPendingSecretId(secretId);
        setPassword('');
        setPasswordError('');
        setPasswordModalOpen(true);
    }

    function handlePasswordSubmit() {
        if (!password.trim()) {
            setPasswordError('Password is required.');
            return;
        }
        if (pendingSecretId === null) return;

        const secretId = pendingSecretId;
        setRevealingId(secretId);

        void (async () => {
            try {
                revealRequest.transform(() => ({ password }));

                const payload = await revealRequest.post(
                    route('platform.websites.secrets.reveal', { website: websiteId, secret: secretId }),
                    {
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    },
                );

                const revealedValue = payload.value;

                if (typeof revealedValue === 'string') {
                    setRevealedValues((prev) => ({ ...prev, [secretId]: revealedValue }));
                }

                setPasswordModalOpen(false);
                setPassword('');
                setPasswordError('');
            } catch {
                setPasswordError('Incorrect password. Please try again.');
            } finally {
                setRevealingId(null);
            }
        })();
    }

    async function copyToClipboard(text: string) {
        await navigator.clipboard.writeText(text);
        showAppToast({ variant: 'success', title: 'Copied to clipboard!' });
    }

    return (
        <>
            <div className="overflow-x-auto">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b text-left">
                            <th className="pb-3 pr-4 font-semibold text-muted-foreground">Key</th>
                            <th className="pb-3 pr-4 font-semibold text-muted-foreground">Username</th>
                            <th className="pb-3 font-semibold text-muted-foreground">Password</th>
                        </tr>
                    </thead>
                    <tbody>
                        {secrets.map((secret) => (
                            <tr key={secret.id} className="border-b last:border-0">
                                <td className="py-4 pr-4">
                                    <Badge variant="danger" className="font-mono">{secret.key}</Badge>
                                </td>
                                <td className="py-4 pr-4">
                                    {secret.username ? (
                                        <div className="flex items-center gap-2">
                                            <code className="rounded border bg-muted px-2 py-1 text-xs">{secret.username}</code>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="size-7"
                                                onClick={() => copyToClipboard(secret.username!)}
                                            >
                                                <ClipboardCopyIcon className="size-3.5" />
                                            </Button>
                                        </div>
                                    ) : (
                                        <span className="text-muted-foreground">—</span>
                                    )}
                                </td>
                                <td className="py-4">
                                    <div className="flex items-center gap-2">
                                        <code className="rounded border bg-muted px-2 py-1 text-xs">
                                            {revealedValues[secret.id] ?? '••••••••'}
                                        </code>
                                        {canReveal ? (
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="size-7"
                                                disabled={revealingId === secret.id}
                                                onClick={() => requestReveal(secret.id)}
                                            >
                                                {revealedValues[secret.id] !== undefined ? (
                                                    <EyeOffIcon className="size-3.5" />
                                                ) : (
                                                    <EyeIcon className="size-3.5" />
                                                )}
                                            </Button>
                                        ) : null}
                                        {revealedValues[secret.id] !== undefined ? (
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="size-7"
                                                onClick={() => copyToClipboard(revealedValues[secret.id])}
                                            >
                                                <ClipboardCopyIcon className="size-3.5" />
                                            </Button>
                                        ) : null}
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <Dialog open={passwordModalOpen} onOpenChange={setPasswordModalOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Reveal Secret</DialogTitle>
                        <DialogDescription>
                            Enter your current account password to continue.
                        </DialogDescription>
                    </DialogHeader>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            handlePasswordSubmit();
                        }}
                    >
                        <div className="flex flex-col gap-2 py-4">
                            <Label htmlFor="reveal-password">Current Password</Label>
                            <PasswordInput
                                ref={passwordInputRef}
                                id="reveal-password"
                                placeholder="Enter current password"
                                value={password}
                                onChange={(e) => {
                                    setPassword(e.target.value);
                                    setPasswordError('');
                                }}
                                autoFocus
                            />
                            {passwordError ? (
                                <p className="text-sm text-destructive">{passwordError}</p>
                            ) : null}
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setPasswordModalOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button type="submit" disabled={revealingId !== null}>
                                Continue
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

// ---------------------------------------------------------------------------
// Provisioning steps sub-component
// ---------------------------------------------------------------------------

function ProvisioningStepsTable({
    websiteId,
    steps,
    provisioningRun,
    isProvisioning,
    websiteStatus,
}: {
    websiteId: number;
    steps: WebsiteProvisioningStep[];
    provisioningRun: ProvisioningRunTimestamps;
    isProvisioning: boolean;
    websiteStatus: string | null;
}) {
    const [currentSteps, setCurrentSteps] = useState(steps);
    const [currentRun, setCurrentRun] = useState(provisioningRun);
    const [currentStatus, setCurrentStatus] = useState<string | null>(websiteStatus);
    const pollingUrl = route('platform.websites.provisioning-status', {
        website: websiteId,
    });
    const [progressPercent, setProgressPercent] = useState(() => {
        const total = steps.length;
        const completed = steps.filter((step) => step.status === 'done').length;

        return total > 0 ? Math.round((completed / total) * 100) : 0;
    });
    const [isPolling, setIsPolling] = useState(
        shouldPollProvisioningState(websiteStatus, steps) || isProvisioning,
    );
    const [lastUpdatedLabel, setLastUpdatedLabel] = useState<string | null>(null);
    const [activeActionKey, setActiveActionKey] = useState<string | null>(null);
    const [pollAttemptCount, setPollAttemptCount] = useState(0);
    const [lastPollError, setLastPollError] = useState<string | null>(null);
    const [lastResponseStatus, setLastResponseStatus] = useState<number | null>(null);
    const stepsRef = useRef(steps);
    const statusRef = useRef<string | null>(websiteStatus);
    const completionReloadedRef = useRef(false);
    const shouldShowDebugState = websiteStatus === 'provisioning'
        || websiteStatus === 'failed'
        || currentStatus === 'provisioning'
        || currentStatus === 'failed';

    useEffect(() => {
        const total = steps.length;
        const completed = steps.filter((step) => step.status === 'done').length;

        setCurrentSteps(steps);
        setCurrentRun(provisioningRun);
        setCurrentStatus(websiteStatus);
        setProgressPercent(total > 0 ? Math.round((completed / total) * 100) : 0);
        setIsPolling(shouldPollProvisioningState(websiteStatus, steps) || isProvisioning);
        setPollAttemptCount(0);
        setLastPollError(null);
        setLastResponseStatus(null);
        stepsRef.current = steps;
        statusRef.current = websiteStatus;
        completionReloadedRef.current = false;
    }, [isProvisioning, provisioningRun, steps, websiteStatus]);

    const refreshProvisioningState = useCallback(async (): Promise<boolean> => {
        setPollAttemptCount((count) => count + 1);

        const response = await fetch(pollingUrl, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        setLastResponseStatus(response.status);

        if (!response.ok) {
            throw new Error('Unable to refresh provisioning status.');
        }

        const payload = (await response.json()) as {
            provisioning_steps?: WebsiteProvisioningStep[];
            provisioning_run?: ProvisioningRunTimestamps;
            percentage?: number;
            current_status?: string | null;
        };

        const nextSteps = Array.isArray(payload.provisioning_steps) ? payload.provisioning_steps : stepsRef.current;
        const nextRun = payload.provisioning_run ?? currentRun;
        const nextStatus = typeof payload.current_status === 'string' ? payload.current_status : null;
        const total = nextSteps.length;
        const completed = nextSteps.filter((step) => step.status === 'done').length;
        const nextProgress = typeof payload.percentage === 'number'
            ? payload.percentage
            : (total > 0 ? Math.round((completed / total) * 100) : 0);
        const previousStatus = statusRef.current;

        setCurrentSteps(nextSteps);
        setCurrentRun(nextRun);
        setCurrentStatus(nextStatus);
        setProgressPercent(nextProgress);
        setLastPollError(null);
        stepsRef.current = nextSteps;
        setLastUpdatedLabel(new Date().toLocaleTimeString([], {
            hour: 'numeric',
            minute: '2-digit',
            second: '2-digit',
        }));
        statusRef.current = nextStatus;

        if (shouldPollProvisioningState(nextStatus, nextSteps)) {
            setIsPolling(true);

            return true;
        }

        setIsPolling(false);

        if (
            previousStatus === 'provisioning' &&
            nextStatus !== 'provisioning' &&
            !completionReloadedRef.current
        ) {
            completionReloadedRef.current = true;
            router.reload();
        }

        return false;
    }, [currentRun, pollingUrl]);

    useEffect(() => {
        if (!isPolling) {
            return;
        }

        let active = true;
        let timeoutId: number | null = null;

        const scheduleNextPoll = () => {
            timeoutId = window.setTimeout(() => {
                void pollProvisioningState();
            }, PROVISIONING_POLL_INTERVAL_MS);
        };

        const pollProvisioningState = async () => {
            try {
                const shouldContinuePolling = await refreshProvisioningState();

                if (active && shouldContinuePolling) {
                    scheduleNextPoll();
                }
            } catch (error) {
                if (active) {
                    const message = error instanceof Error ? error.message : 'Unknown polling error.';

                    setLastPollError(message);
                    setIsPolling(false);
                }
            }
        };

        scheduleNextPoll();

        return () => {
            active = false;
            if (timeoutId !== null) {
                window.clearTimeout(timeoutId);
            }
        };
    }, [isPolling, refreshProvisioningState]);

    async function runStepAction(url: string, actionKey: string, successTitle: string): Promise<void> {
        setActiveActionKey(actionKey);

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({}),
            });

            const payload = (await response.json()) as { status?: string; message?: string };

            if (!response.ok || payload.status !== 'success') {
                throw new Error(payload.message || 'Operation failed.');
            }

            showAppToast({
                variant: 'success',
                title: successTitle,
                description: payload.message,
            });

            await refreshProvisioningState();
        } catch (error) {
            showAppToast({
                variant: 'error',
                title: error instanceof Error ? error.message : 'Operation failed.',
            });
        } finally {
            setActiveActionKey(null);
        }
    }

    function executeStep(stepKey: string) {
        void runStepAction(
            route('platform.websites.execute.step', { website: websiteId, step: stepKey }),
            stepKey,
            'Step executed successfully.',
        );
    }

    function revertStep(stepKey: string) {
        void runStepAction(
            route('platform.websites.revert.step', { website: websiteId, step: stepKey }),
            `revert:${stepKey}`,
            'Step reverted successfully.',
        );
    }

    function executeAll() {
        void runStepAction(
            route('platform.websites.execute.step', { website: websiteId, step: 'all' }),
            'all',
            'Provisioning run started.',
        );
    }

    function revertAll() {
        void runStepAction(
            route('platform.websites.revert.step', { website: websiteId, step: 'all' }),
            'revert:all',
            'All steps reverted.',
        );
    }

    async function updateDnsValidation(url: string, actionKey: string, successTitle: string): Promise<void> {
        setActiveActionKey(actionKey);

        try {
            const csrfToken =
                document
                    .querySelector('meta[name="csrf-token"]')
                    ?.getAttribute('content') ?? '';
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({}),
            });

            const payload = (await response.json()) as {
                status?: string;
                message?: string;
            };

            if (!response.ok || payload.status !== 'success') {
                throw new Error(payload.message || 'Operation failed.');
            }

            showAppToast({
                variant: 'success',
                title: successTitle,
                description: payload.message,
            });

            await refreshProvisioningState();
        } catch (error) {
            showAppToast({
                variant: 'error',
                title: error instanceof Error ? error.message : 'Operation failed.',
            });
        } finally {
            setActiveActionKey(null);
        }
    }

    function startDnsValidation(url: string) {
        void updateDnsValidation(url, 'dns:start', 'DNS validation started.');
    }

    function stopDnsValidation(url: string) {
        void updateDnsValidation(url, 'dns:stop', 'DNS validation stopped.');
    }

    const totalSteps = currentSteps.length;
    const doneSteps = currentSteps.filter((step) => step.status === 'done').length;
    const failedSteps = currentSteps.filter((step) => step.status === 'failed').length;
    const hasAnyDone = currentSteps.some((step) => step.status === 'done');

    return (
        <div className="flex flex-col gap-4">
            <div className="rounded-xl border bg-muted/20 p-4">
                <div className="flex flex-col gap-4">
                    <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                        <div className="flex flex-col gap-1">
                            <h3 className="text-sm font-semibold">Provisioning Steps</h3>
                            <p className="text-sm text-muted-foreground">
                                {isPolling
                                    ? `Auto-updating ${PROVISIONING_POLL_INTERVAL_LABEL} while provisioning is running.`
                                    : 'Step status updates appear here as actions complete.'}
                            </p>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            {isPolling ? (
                                <Badge variant="info" className="gap-1.5">
                                    <Spinner className="size-3.5" />
                                    Live updates
                                </Badge>
                            ) : null}
                            {lastUpdatedLabel ? (
                                <span className="text-xs text-muted-foreground">
                                    Last checked at {lastUpdatedLabel}
                                </span>
                            ) : null}
                        </div>
                    </div>

                    <div className="grid gap-3 md:grid-cols-[minmax(0,1fr)_auto] md:items-center">
                        <div className="flex flex-col gap-2">
                            {currentRun.started_at || currentRun.completed_at ? (
                                <div className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                                    {currentRun.started_at ? <span>Started: {currentRun.started_at}</span> : null}
                                    {currentRun.completed_at ? <span>Completed: {currentRun.completed_at}</span> : null}
                                </div>
                            ) : null}
                            <div className="flex items-center justify-between text-xs text-muted-foreground">
                                <span>{doneSteps} of {totalSteps} completed</span>
                                <span>{progressPercent}%</span>
                            </div>
                            <div className="h-2.5 w-full overflow-hidden rounded-full bg-muted">
                                <div
                                    className={cn(
                                        'h-full rounded-full transition-all',
                                        isPolling ? 'bg-emerald-500/90' : failedSteps > 0 ? 'bg-amber-500' : 'bg-emerald-500',
                                    )}
                                    style={{ width: `${progressPercent}%` }}
                                />
                            </div>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge variant={statusBadgeVariant(currentStatus)}>
                                {currentStatus ? currentStatus.replace(/_/g, ' ').replace(/\b\w/g, (letter) => letter.toUpperCase()) : 'Unknown'}
                            </Badge>
                            {failedSteps > 0 ? (
                                <Badge variant="warning">{failedSteps} failed</Badge>
                            ) : null}
                        </div>
                    </div>

                    {shouldShowDebugState ? (
                    <div className="rounded-lg border border-dashed bg-background/70 p-3 text-xs">
                        <div className="grid gap-2 md:grid-cols-2">
                            <div>
                                <span className="text-muted-foreground">Attempts:</span>{' '}
                                <span className="font-medium">{pollAttemptCount}</span>
                            </div>
                            <div>
                                <span className="text-muted-foreground">Last response:</span>{' '}
                                <span className="font-medium">{lastResponseStatus ?? 'not requested yet'}</span>
                            </div>
                            <div className="md:col-span-2">
                                <span className="text-muted-foreground">Last error:</span>{' '}
                                <span className={cn(lastPollError ? 'font-medium text-destructive' : 'font-medium')}>
                                    {lastPollError ?? 'none'}
                                </span>
                            </div>
                        </div>
                    </div>
                    ) : null}

                    <div className="flex items-center justify-between gap-3">
                        <div className="text-xs text-muted-foreground">
                            Individual steps can be rerun or reverted without leaving the page.
                        </div>
                        <div className="flex gap-2">
                    {hasAnyDone ? (
                        <Button variant="outline" size="sm" disabled={activeActionKey !== null} onClick={revertAll}>
                            <RotateCcwIcon data-icon="inline-start" />
                            Revert All
                        </Button>
                    ) : (
                        <Button size="sm" disabled={activeActionKey !== null} onClick={executeAll}>
                            <PlayCircleIcon data-icon="inline-start" />
                            Run All
                        </Button>
                    )}
                        </div>
                    </div>
                </div>
            </div>

            {currentSteps.length === 0 ? (
                <p className="text-sm text-muted-foreground">No provisioning steps configured.</p>
            ) : (
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b text-left">
                                <th className="pb-2 pr-4 font-medium text-muted-foreground">Step</th>
                                <th className="pb-2 pr-4 font-medium text-muted-foreground">Status</th>
                                <th className="pb-2 pr-4 font-medium text-muted-foreground">Message</th>
                                <th className="pb-2 text-center font-medium text-muted-foreground">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            {currentSteps.map((step) => (
                                <tr key={step.key} className="border-b last:border-0">
                                    <td className="py-3 pr-4">
                                        <p className="font-medium">{step.title}</p>
                                        {step.description ? (
                                            <p className="text-xs text-muted-foreground">{step.description}</p>
                                        ) : null}
                                        {step.started_at || step.completed_at ? (
                                            <div className="mt-1 flex flex-col gap-0.5 text-[11px] text-muted-foreground">
                                                {step.started_at ? <span>Started: {step.started_at}</span> : null}
                                                {step.completed_at ? <span>Completed: {step.completed_at}</span> : null}
                                            </div>
                                        ) : null}
                                    </td>
                                    <td className="py-3 pr-4">
                                        <Badge variant={STEP_STATUS_VARIANT[step.status] ?? 'secondary'}>
                                            {step.status === 'done' ? (
                                                <CheckCircleIcon data-icon="inline-start" />
                                            ) : null}
                                            {step.status.charAt(0).toUpperCase() + step.status.slice(1)}
                                        </Badge>
                                    </td>
                                    <td className="py-3 pr-4 text-muted-foreground">
                                        {step.message ?? ''}
                                        {step.dns_instructions ? (
                                            <WebsiteProvisioningDnsInstructions instructions={step.dns_instructions} />
                                        ) : null}
                                        {step.key === 'verify_dns' && step.dns_validation ? (
                                            <div className="mt-2 rounded-lg border bg-muted/20 p-3">
                                                {step.dns_validation.confirmed_by_user ? (
                                                    <div className="flex flex-col gap-3">
                                                        <div className="space-y-1">
                                                            <p className="text-xs font-medium text-foreground">
                                                                DNS validation is running.
                                                            </p>
                                                            <p className="text-xs text-muted-foreground">
                                                                Automatic checks run {PROVISIONING_POLL_INTERVAL_LABEL}. Current check count:{' '}
                                                                {step.dns_validation.check_count}.
                                                            </p>
                                                            {step.dns_validation.confirmed_at ? (
                                                                <p className="text-xs text-muted-foreground">
                                                                    Started: {step.dns_validation.confirmed_at}
                                                                </p>
                                                            ) : null}
                                                        </div>
                                                        <div className="flex flex-wrap gap-2">
                                                            <Button
                                                                variant="outline"
                                                                size="sm"
                                                                disabled={activeActionKey !== null}
                                                                onClick={() => stopDnsValidation(step.dns_validation!.stop_url)}
                                                            >
                                                                {activeActionKey === 'dns:stop' ? (
                                                                    <Spinner className="size-3.5" />
                                                                ) : (
                                                                    <RotateCcwIcon data-icon="inline-start" />
                                                                )}
                                                                Stop Validation
                                                            </Button>
                                                        </div>
                                                    </div>
                                                ) : (
                                                    <div className="flex flex-col gap-3">
                                                        <div className="space-y-1">
                                                            <p className="text-xs text-muted-foreground">
                                                                Start automatic DNS validation after you update the records above.
                                                            </p>
                                                        </div>
                                                        <div className="flex flex-wrap gap-2">
                                                            <Button
                                                                size="sm"
                                                                disabled={activeActionKey !== null}
                                                                onClick={() => startDnsValidation(step.dns_validation!.confirm_url)}
                                                            >
                                                                {activeActionKey === 'dns:start' ? (
                                                                    <Spinner className="size-3.5" />
                                                                ) : (
                                                                    <PlayCircleIcon data-icon="inline-start" />
                                                                )}
                                                                Start Validation
                                                            </Button>
                                                        </div>
                                                    </div>
                                                )}
                                            </div>
                                        ) : null}
                                    </td>
                                    <td className="py-3 text-center">
                                        {step.status === 'done' ? (
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                disabled={activeActionKey !== null}
                                                onClick={() => revertStep(step.key)}
                                            >
                                                {activeActionKey === `revert:${step.key}` ? (
                                                    <Spinner className="size-3.5" />
                                                ) : (
                                                    <RotateCcwIcon data-icon="inline-start" />
                                                )}
                                            </Button>
                                        ) : (
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                disabled={activeActionKey !== null}
                                                onClick={() => executeStep(step.key)}
                                            >
                                                {activeActionKey === step.key ? (
                                                    <Spinner className="size-3.5" />
                                                ) : (
                                                    <PlayCircleIcon data-icon="inline-start" />
                                                )}
                                            </Button>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}
