import { Link } from '@inertiajs/react';
import {
    ExternalLinkIcon,
    GlobeIcon,
    PencilIcon,
    RefreshCwIcon,
    RotateCcwIcon,
    ServerIcon,
    ShieldCheckIcon,
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
import { cn } from '@/lib/utils';
import type {
    AgencyProviderItem,
    AgencyServerItem,
    AgencyShowData,
} from '../../../../types/platform';
import type { ConfirmState } from './show-shared';
import {
    HealthChip,
    hostFromUrl,
    InfoRow,
    MetricBox,
    statusBadgeVariant,
} from './show-shared';

type AgencyShowOverviewProps = {
    agency: AgencyShowData;
    servers: AgencyServerItem[];
    dnsProviders: AgencyProviderItem[];
    cdnProviders: AgencyProviderItem[];
    canEdit: boolean;
    canDelete: boolean;
    canRestore: boolean;
    processing: boolean;
    setActiveTab: (tab: string) => void;
    openConfirm: (
        title: string,
        description: string,
        confirmLabel: string,
        action: () => void,
        tone?: ConfirmState['tone'],
    ) => void;
    perform: (method: 'post' | 'patch' | 'delete', url: string) => void;
};

export function AgencyShowOverview({
    agency,
    servers,
    dnsProviders,
    cdnProviders,
    canEdit,
    canDelete,
    canRestore,
    processing,
    setActiveTab,
    openConfirm,
    perform,
}: AgencyShowOverviewProps) {
    const brandingHost = hostFromUrl(agency.branding.website);
    const primaryServer = servers.find((server) => server.is_primary) ?? null;
    const primaryDnsProvider =
        dnsProviders.find((provider) => provider.is_primary) ?? null;
    const primaryCdnProvider =
        cdnProviders.find((provider) => provider.is_primary) ?? null;
    const capacityTone =
        agency.plan_usage_percent !== null && agency.plan_usage_percent > 85
            ? 'warning'
            : 'info';

    return (
        <div className="flex flex-col gap-6">
            <div className="-mt-4 flex flex-wrap items-center gap-2">
                <Badge
                    variant={
                        agency.is_trashed
                            ? 'danger'
                            : statusBadgeVariant(agency.status)
                    }
                >
                    {agency.is_trashed ? 'In Trash' : agency.status_label}
                </Badge>
                <Badge variant="secondary">{agency.plan_label ?? 'Plan'}</Badge>
            </div>

            {agency.is_trashed ? (
                <div className="flex flex-col gap-3 rounded-lg border border-destructive/30 bg-destructive/5 p-4 md:flex-row md:items-center md:justify-between">
                    <div className="flex items-center gap-3">
                        <Trash2Icon className="size-5 shrink-0 text-destructive" />
                        <div>
                            <p className="font-semibold text-foreground">
                                This agency is in trash
                            </p>
                            <p className="text-sm text-muted-foreground">
                                {agency.deleted_at
                                    ? `Trashed on ${agency.deleted_at}.`
                                    : 'Restore it to continue managing profile and infrastructure links.'}
                            </p>
                        </div>
                    </div>
                    {canRestore ? (
                        <Button
                            disabled={processing}
                            onClick={() =>
                                openConfirm(
                                    'Restore Agency',
                                    'Restore this agency from trash?',
                                    'Restore',
                                    () =>
                                        perform(
                                            'patch',
                                            route('platform.agencies.restore', agency.id),
                                        ),
                                )
                            }
                        >
                            <RotateCcwIcon data-icon="inline-start" />
                            Restore
                        </Button>
                    ) : null}
                </div>
            ) : null}

            <div className="grid gap-6 xl:grid-cols-[1fr_22rem]">
                <Card>
                    <CardHeader>
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                    Command Center
                                </p>
                                <CardTitle className="mt-1 text-xl">
                                    {agency.name}
                                </CardTitle>
                                {agency.branding.website ? (
                                    <a
                                        href={agency.branding.website}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="text-sm font-semibold text-primary hover:underline"
                                    >
                                        {brandingHost ?? agency.branding.website}
                                    </a>
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        No branded website configured
                                    </p>
                                )}
                            </div>
                            <div className="text-right text-sm">
                                <p className="text-muted-foreground">Agency ID</p>
                                <p className="font-mono font-semibold">
                                    {agency.uid ?? '—'}
                                </p>
                                <p className="mt-1 text-muted-foreground">
                                    Updated: {agency.updated_at ?? '—'}
                                </p>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-4">
                        <div className="grid grid-cols-2 gap-2 lg:grid-cols-4">
                            <MetricBox
                                label="Plan"
                                value={agency.plan_label ?? '—'}
                            />
                            <MetricBox
                                label="Type"
                                value={agency.type_label ?? '—'}
                            />
                            <MetricBox
                                label="Websites"
                                value={agency.statistics.websites}
                            />
                            <MetricBox
                                label="Providers"
                                value={agency.statistics.providers}
                            />
                        </div>

                        <div className="flex flex-wrap gap-2">
                            <HealthChip
                                label={agency.status_label ?? 'Unknown'}
                                tone={
                                    agency.is_trashed
                                        ? 'warning'
                                        : agency.status === 'active'
                                          ? 'success'
                                          : 'secondary'
                                }
                            />
                            <HealthChip
                                label={`Branding: ${
                                    agency.is_whitelabel
                                        ? 'Whitelabel Ready'
                                        : 'Plan Restricted'
                                }`}
                                tone={
                                    agency.is_whitelabel ? 'success' : 'warning'
                                }
                            />
                            <HealthChip
                                label={`Agency Site: ${
                                    agency.agency_website ? 'Connected' : 'Not Linked'
                                }`}
                                tone={
                                    agency.agency_website
                                        ? 'success'
                                        : 'secondary'
                                }
                            />
                            <HealthChip
                                label={`Secret Key: ${
                                    agency.has_secret_key ? 'Configured' : 'Missing'
                                }`}
                                tone={
                                    agency.has_secret_key ? 'success' : 'warning'
                                }
                            />
                            <HealthChip
                                label={`Webhook: ${
                                    agency.webhook_url ? 'Configured' : 'Missing'
                                }`}
                                tone={
                                    agency.webhook_url ? 'success' : 'warning'
                                }
                            />
                        </div>

                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="flex flex-col gap-2">
                                <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                    Ownership
                                </p>
                                <InfoRow label="Owner">
                                    {agency.owner_id && route().has('app.users.show') ? (
                                        <Link
                                            href={route('app.users.show', agency.owner_id)}
                                            className="text-primary hover:underline"
                                        >
                                            {agency.owner_name ?? 'Not assigned'}
                                        </Link>
                                    ) : (
                                        agency.owner_name ?? 'Not assigned'
                                    )}
                                </InfoRow>
                                <InfoRow label="Email">{agency.email ?? '—'}</InfoRow>
                                <InfoRow label="Phone">
                                    {[
                                        agency.address.phone_code,
                                        agency.address.phone,
                                    ]
                                        .filter(Boolean)
                                        .join(' ') || '—'}
                                </InfoRow>
                                <InfoRow label="Created">
                                    {agency.created_at ?? '—'}
                                </InfoRow>
                            </div>

                            <div className="flex flex-col gap-2">
                                <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                    Infrastructure
                                </p>
                                <InfoRow label="Primary Server">
                                    {primaryServer?.href ? (
                                        <Link
                                            href={primaryServer.href}
                                            className="text-primary hover:underline"
                                        >
                                            {primaryServer.name}
                                        </Link>
                                    ) : (
                                        primaryServer?.name ?? '—'
                                    )}
                                </InfoRow>
                                <InfoRow label="Primary DNS">
                                    {primaryDnsProvider?.href ? (
                                        <Link
                                            href={primaryDnsProvider.href}
                                            className="text-primary hover:underline"
                                        >
                                            {primaryDnsProvider.name}
                                        </Link>
                                    ) : (
                                        primaryDnsProvider?.name ?? '—'
                                    )}
                                </InfoRow>
                                <InfoRow label="Primary CDN">
                                    {primaryCdnProvider?.href ? (
                                        <Link
                                            href={primaryCdnProvider.href}
                                            className="text-primary hover:underline"
                                        >
                                            {primaryCdnProvider.name}
                                        </Link>
                                    ) : (
                                        primaryCdnProvider?.name ?? '—'
                                    )}
                                </InfoRow>
                                <InfoRow label="Agency Platform">
                                    {agency.agency_website ? (
                                        <Link
                                            href={agency.agency_website.href}
                                            className="text-primary hover:underline"
                                        >
                                            {agency.agency_website.name}
                                        </Link>
                                    ) : (
                                        'Not linked'
                                    )}
                                </InfoRow>
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
                            High-impact actions for profile management,
                            infrastructure links, and lifecycle changes.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-4">
                        {!agency.is_trashed ? (
                            <div className="grid grid-cols-2 gap-2">
                                {agency.branding.website ? (
                                    <Button variant="outline" asChild>
                                        <a
                                            href={agency.branding.website}
                                            target="_blank"
                                            rel="noreferrer"
                                        >
                                            <ExternalLinkIcon data-icon="inline-start" />
                                            Website
                                        </a>
                                    </Button>
                                ) : null}

                                {canEdit ? (
                                    <Button variant="outline" asChild>
                                        <Link
                                            href={route(
                                                'platform.agencies.edit',
                                                agency.id,
                                            )}
                                        >
                                            <PencilIcon data-icon="inline-start" />
                                            Edit
                                        </Link>
                                    </Button>
                                ) : null}

                                <Button
                                    variant="outline"
                                    type="button"
                                    onClick={() => setActiveTab('websites')}
                                >
                                    <GlobeIcon data-icon="inline-start" />
                                    Websites
                                </Button>

                                <Button
                                    variant="outline"
                                    type="button"
                                    onClick={() => setActiveTab('servers')}
                                >
                                    <ServerIcon data-icon="inline-start" />
                                    Servers
                                </Button>

                                <Button
                                    variant="outline"
                                    type="button"
                                    onClick={() => setActiveTab('providers')}
                                >
                                    <ShieldCheckIcon data-icon="inline-start" />
                                    Providers
                                </Button>

                                {canEdit ? (
                                    <Button
                                        variant="outline"
                                        type="button"
                                        disabled={processing}
                                        onClick={() =>
                                            openConfirm(
                                                'Regenerate Secret Key',
                                                'Generate a new agency secret key? The connected agency instance will need the new key immediately.',
                                                'Regenerate',
                                                () =>
                                                    perform(
                                                        'post',
                                                        route(
                                                            'platform.agencies.regenerate-secret-key',
                                                            agency.id,
                                                        ),
                                                    ),
                                            )
                                        }
                                    >
                                        <RefreshCwIcon data-icon="inline-start" />
                                        Regenerate
                                    </Button>
                                ) : null}

                                {canDelete ? (
                                    <Button
                                        variant="outline"
                                        className="col-span-2 border-destructive/30 text-destructive hover:bg-destructive/10"
                                        disabled={processing}
                                        onClick={() =>
                                            openConfirm(
                                                'Move to Trash',
                                                'Move this agency to trash? You can restore it later.',
                                                'Move to Trash',
                                                () =>
                                                    perform(
                                                        'delete',
                                                        route(
                                                            'platform.agencies.destroy',
                                                            agency.id,
                                                        ),
                                                    ),
                                                'destructive',
                                            )
                                        }
                                    >
                                        <Trash2Icon data-icon="inline-start" />
                                        Trash
                                    </Button>
                                ) : null}
                            </div>
                        ) : (
                            <div className="rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-300">
                                This agency is in trash mode. Restore it from the
                                warning banner before editing or rotating
                                credentials.
                            </div>
                        )}

                        <Separator />

                        <div className="flex flex-col gap-3">
                            <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                Capacity
                            </p>
                            <InfoRow label="Websites">
                                {agency.website_limit && agency.website_limit > 0
                                    ? `${agency.statistics.websites}/${agency.website_limit}`
                                    : `${agency.statistics.websites} total`}
                            </InfoRow>
                            {agency.plan_usage_percent !== null ? (
                                <div className="flex flex-col gap-1">
                                    <div className="h-2 overflow-hidden rounded-full bg-muted">
                                        <div
                                            className={cn(
                                                'h-full rounded-full transition-all',
                                                capacityTone === 'warning'
                                                    ? 'bg-amber-500'
                                                    : 'bg-sky-500',
                                            )}
                                            style={{
                                                width: `${agency.plan_usage_percent}%`,
                                            }}
                                        />
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                        {agency.plan_usage_percent}% of plan usage
                                    </p>
                                </div>
                            ) : null}
                            <InfoRow label="Servers">
                                {agency.statistics.servers}
                            </InfoRow>
                            <InfoRow label="CDN/DNS Providers">
                                {agency.statistics.providers}
                            </InfoRow>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}
