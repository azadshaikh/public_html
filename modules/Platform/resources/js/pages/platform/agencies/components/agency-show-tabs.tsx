import { Link } from '@inertiajs/react';
import {
    ActivityIcon,
    Building2Icon,
    ClockIcon,
    CodeIcon,
    GlobeIcon,
    InfoIcon,
    KeyRoundIcon,
    PaintbrushIcon,
    RefreshCwIcon,
    StickyNoteIcon,
    WebhookIcon,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { cn } from '@/lib/utils';
import type {
    AgencyProviderItem,
    AgencyRelationItem,
    AgencyServerItem,
    AgencyShowData,
    PlatformActivity,
} from '../../../types/platform';
import { AgencyProvidersTab } from './agency-providers-tab';
import { AgencyServersTab } from './agency-servers-tab';
import type { ConfirmState } from './show-shared';
import { InfoRow } from './show-shared';

type AgencyShowTabsProps = {
    agency: AgencyShowData;
    websites: AgencyRelationItem[];
    servers: AgencyServerItem[];
    dnsProviders: AgencyProviderItem[];
    cdnProviders: AgencyProviderItem[];
    activities: PlatformActivity[];
    canEdit: boolean;
    isMobile: boolean;
    activeTab: string;
    setActiveTab: (value: string) => void;
    processing: boolean;
    openConfirm: (
        title: string,
        description: string,
        confirmLabel: string,
        action: () => void,
        tone?: ConfirmState['tone'],
    ) => void;
    perform: (method: 'post' | 'patch' | 'delete', url: string) => void;
};

function AgencyGeneralTab({
    agency,
    canEdit,
    processing,
    openConfirm,
    perform,
}: {
    agency: AgencyShowData;
    canEdit: boolean;
    processing: boolean;
    openConfirm: AgencyShowTabsProps['openConfirm'];
    perform: AgencyShowTabsProps['perform'];
}) {
    return (
        <div className="grid gap-6 md:grid-cols-2">
            <Card>
                <CardHeader>
                    <div className="flex items-center gap-2">
                        <Building2Icon className="size-4 text-muted-foreground" />
                        <CardTitle>Contact &amp; Address</CardTitle>
                    </div>
                </CardHeader>
                <CardContent className="flex flex-col gap-3">
                    <InfoRow label="Email">
                        {agency.email ? (
                            <a
                                href={`mailto:${agency.email}`}
                                className="text-primary hover:underline"
                            >
                                {agency.email}
                            </a>
                        ) : (
                            '—'
                        )}
                    </InfoRow>
                    <InfoRow label="Phone">
                        {[agency.address.phone_code, agency.address.phone]
                            .filter(Boolean)
                            .join(' ') || '—'}
                    </InfoRow>
                    <Separator />
                    <InfoRow label="Street">
                        {agency.address.address1 ?? '—'}
                    </InfoRow>
                    <InfoRow label="City">{agency.address.city ?? '—'}</InfoRow>
                    <InfoRow label="State">
                        {agency.address.state ?? agency.address.state_code ?? '—'}
                    </InfoRow>
                    <InfoRow label="Country">
                        {agency.address.country ??
                            agency.address.country_code ??
                            '—'}
                    </InfoRow>
                    <InfoRow label="ZIP Code">
                        {agency.address.zip ?? '—'}
                    </InfoRow>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <div className="flex items-center gap-2">
                        <PaintbrushIcon className="size-4 text-muted-foreground" />
                        <CardTitle>Branding</CardTitle>
                    </div>
                </CardHeader>
                <CardContent className="flex flex-col gap-4">
                    <InfoRow label="Brand Name">
                        {agency.branding.name ?? '—'}
                    </InfoRow>
                    <div className="flex flex-wrap items-center gap-3">
                        {agency.branding.logo ? (
                            <img
                                src={agency.branding.logo}
                                alt={`${agency.name} logo`}
                                className="max-h-16 rounded-lg border bg-background p-2 object-contain"
                            />
                        ) : (
                            <div className="flex h-16 w-16 items-center justify-center rounded-lg border border-dashed text-muted-foreground">
                                <PaintbrushIcon className="size-5" />
                            </div>
                        )}

                        {agency.branding.icon ? (
                            <img
                                src={agency.branding.icon}
                                alt={`${agency.name} icon`}
                                className="size-12 rounded-lg border bg-background p-1.5 object-contain"
                            />
                        ) : null}
                    </div>
                    <InfoRow label="Website">
                        {agency.branding.website ? (
                            <a
                                href={agency.branding.website}
                                target="_blank"
                                rel="noreferrer"
                                className="text-primary hover:underline"
                            >
                                {agency.branding.website}
                            </a>
                        ) : (
                            'Not configured'
                        )}
                    </InfoRow>
                    {!agency.is_whitelabel ? (
                        <Badge variant="warning">
                            Branding is configured, but this plan is not
                            white-label enabled.
                        </Badge>
                    ) : null}
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <div className="flex items-center gap-2">
                        <KeyRoundIcon className="size-4 text-muted-foreground" />
                        <CardTitle>Secret Key</CardTitle>
                    </div>
                </CardHeader>
                <CardContent className="flex flex-col gap-4">
                    <div className="rounded-lg border bg-muted/30 px-3 py-2 font-mono text-sm tracking-[0.2em] text-muted-foreground">
                        {agency.has_secret_key
                            ? '••••••••••••••••••••••••••••••••'
                            : 'Not configured'}
                    </div>
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <Badge
                            variant={
                                agency.has_secret_key ? 'success' : 'warning'
                            }
                        >
                            {agency.has_secret_key ? 'Configured' : 'Missing'}
                        </Badge>
                        {canEdit && !agency.is_trashed ? (
                            <Button
                                variant="outline"
                                disabled={processing}
                                onClick={() =>
                                    openConfirm(
                                        'Regenerate Secret Key',
                                        'Generate a new agency secret key? The connected agency instance must be updated immediately.',
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
                    </div>
                    <p className="text-sm text-muted-foreground">
                        Stored securely for agency-to-platform API authentication.
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <div className="flex items-center gap-2">
                        <WebhookIcon className="size-4 text-muted-foreground" />
                        <CardTitle>Webhook URL</CardTitle>
                    </div>
                </CardHeader>
                <CardContent className="flex flex-col gap-4">
                    <div className="rounded-lg border bg-muted/30 px-3 py-2 text-sm break-all text-foreground">
                        {agency.webhook_url ?? 'Not configured'}
                    </div>
                    <Badge variant={agency.webhook_url ? 'success' : 'warning'}>
                        {agency.webhook_url ? 'Configured' : 'Missing'}
                    </Badge>
                    <p className="text-sm text-muted-foreground">
                        Provisioning and lifecycle updates are delivered to this
                        endpoint.
                    </p>
                </CardContent>
            </Card>

            <Card className="md:col-span-2">
                <CardHeader>
                    <div className="flex items-center gap-2">
                        <ClockIcon className="size-4 text-muted-foreground" />
                        <CardTitle>Timestamps</CardTitle>
                    </div>
                </CardHeader>
                <CardContent className="grid gap-3 md:grid-cols-3">
                    <InfoRow label="Created">{agency.created_at ?? '—'}</InfoRow>
                    <InfoRow label="Updated">{agency.updated_at ?? '—'}</InfoRow>
                    <InfoRow label="Deleted">{agency.deleted_at ?? '—'}</InfoRow>
                </CardContent>
            </Card>
        </div>
    );
}

function AgencyWebsitesTab({ websites }: { websites: AgencyRelationItem[] }) {
    return (
        <Card>
            <CardHeader>
                <div className="flex items-center gap-2">
                    <GlobeIcon className="size-4 text-muted-foreground" />
                    <CardTitle>Websites</CardTitle>
                </div>
            </CardHeader>
            <CardContent className="flex flex-col gap-3">
                {websites.length === 0 ? (
                    <div className="rounded-lg border border-dashed px-4 py-8 text-center text-sm text-muted-foreground">
                        No websites are attached to this agency yet.
                    </div>
                ) : (
                    websites.map((website) => (
                        <div
                            key={website.id}
                            className="flex items-center justify-between gap-3 rounded-lg border bg-muted/20 p-3"
                        >
                            <div className="min-w-0">
                                {website.href ? (
                                    <Link
                                        href={website.href}
                                        className="font-medium text-foreground hover:text-primary"
                                    >
                                        {website.name}
                                    </Link>
                                ) : (
                                    <p className="font-medium text-foreground">
                                        {website.name}
                                    </p>
                                )}
                                {website.subtitle ? (
                                    <p className="truncate text-sm text-muted-foreground">
                                        {website.subtitle}
                                    </p>
                                ) : null}
                            </div>
                            <div className="flex shrink-0 items-center gap-2">
                                {website.is_primary ? (
                                    <Badge variant="info">Agency Site</Badge>
                                ) : null}
                                {website.status_label || website.status ? (
                                    <Badge variant="secondary">
                                        {website.status_label ?? website.status}
                                    </Badge>
                                ) : null}
                            </div>
                        </div>
                    ))
                )}
            </CardContent>
        </Card>
    );
}

function AgencyMetadataTab({ agency }: { agency: AgencyShowData }) {
    const metadataItems = [
        { label: 'Agency UID', value: agency.uid ?? '—' },
        { label: 'Owner', value: agency.owner_name ?? 'Not assigned' },
        { label: 'Type', value: agency.type_label ?? '—' },
        { label: 'Plan', value: agency.plan_label ?? '—' },
        { label: 'Status', value: agency.status_label ?? '—' },
        { label: 'Website Prefix', value: agency.website_id_prefix ?? '—' },
        {
            label: 'Zero Padding',
            value: agency.website_id_zero_padding ?? '—',
        },
        {
            label: 'Agency Platform',
            value: agency.agency_website?.name ?? 'Not linked',
        },
        {
            label: 'Webhook',
            value: agency.webhook_url ?? 'Not configured',
        },
        {
            label: 'White-label',
            value: agency.is_whitelabel ? 'Enabled' : 'Plan restricted',
        },
        {
            label: 'Plan Limit',
            value:
                agency.website_limit && agency.website_limit > 0
                    ? `${agency.website_limit} websites`
                    : 'Unlimited',
        },
        {
            label: 'Usage',
            value:
                agency.plan_usage_percent !== null
                    ? `${agency.plan_usage_percent}%`
                    : 'Not limited',
        },
    ];

    return (
        <Card>
            <CardContent className="pt-6">
                <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    {metadataItems.map((item) => (
                        <div
                            key={item.label}
                            className="rounded-lg border bg-muted/30 p-3"
                        >
                            <p className="text-[0.7rem] font-semibold tracking-wide text-muted-foreground uppercase">
                                {item.label}
                            </p>
                            <div className="mt-1 text-sm font-medium break-words text-foreground">
                                {item.value}
                            </div>
                        </div>
                    ))}
                </div>
            </CardContent>
        </Card>
    );
}

export function AgencyShowTabs({
    agency,
    websites,
    servers,
    dnsProviders,
    cdnProviders,
    activities,
    canEdit,
    isMobile,
    activeTab,
    setActiveTab,
    processing,
    openConfirm,
    perform,
}: AgencyShowTabsProps) {
    return (
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
                    !isMobile &&
                        'min-w-0 overflow-x-auto pr-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden',
                )}
            >
                <TabsTrigger value="general" className={cn(!isMobile && 'shrink-0')}>
                    <InfoIcon data-icon="inline-start" />
                    General
                </TabsTrigger>
                <TabsTrigger value="websites" className={cn(!isMobile && 'shrink-0')}>
                    <GlobeIcon data-icon="inline-start" />
                    Websites
                    <Badge
                        variant="secondary"
                        className="rounded-full px-1.5 py-0 text-[0.7rem]"
                    >
                        {websites.length}
                    </Badge>
                </TabsTrigger>
                <TabsTrigger value="servers" className={cn(!isMobile && 'shrink-0')}>
                    <GlobeIcon data-icon="inline-start" />
                    Servers
                    <Badge
                        variant="secondary"
                        className="rounded-full px-1.5 py-0 text-[0.7rem]"
                    >
                        {servers.length}
                    </Badge>
                </TabsTrigger>
                <TabsTrigger value="providers" className={cn(!isMobile && 'shrink-0')}>
                    <GlobeIcon data-icon="inline-start" />
                    CDN/DNS
                    <Badge
                        variant="secondary"
                        className="rounded-full px-1.5 py-0 text-[0.7rem]"
                    >
                        {dnsProviders.length + cdnProviders.length}
                    </Badge>
                </TabsTrigger>
                <TabsTrigger value="notes" className={cn(!isMobile && 'shrink-0')}>
                    <StickyNoteIcon data-icon="inline-start" />
                    Notes
                </TabsTrigger>
                <TabsTrigger value="metadata" className={cn(!isMobile && 'shrink-0')}>
                    <CodeIcon data-icon="inline-start" />
                    Metadata
                </TabsTrigger>
                <TabsTrigger value="activity" className={cn(!isMobile && 'shrink-0')}>
                    <ActivityIcon data-icon="inline-start" />
                    Activity
                </TabsTrigger>
            </TabsList>

            <TabsContent value="general">
                <AgencyGeneralTab
                    agency={agency}
                    canEdit={canEdit}
                    processing={processing}
                    openConfirm={openConfirm}
                    perform={perform}
                />
            </TabsContent>

            <TabsContent value="websites">
                <AgencyWebsitesTab websites={websites} />
            </TabsContent>

            <TabsContent value="servers">
                <AgencyServersTab
                    agency={agency}
                    servers={servers}
                    canEdit={canEdit}
                    openConfirm={openConfirm}
                />
            </TabsContent>

            <TabsContent value="providers">
                <AgencyProvidersTab
                    agency={agency}
                    dnsProviders={dnsProviders}
                    cdnProviders={cdnProviders}
                    canEdit={canEdit}
                    openConfirm={openConfirm}
                />
            </TabsContent>

            <TabsContent value="notes">
                <Card>
                    <CardContent className="pt-6">
                        <div className="rounded-lg border border-dashed px-4 py-10 text-center text-sm text-muted-foreground">
                            Notes will live here once the agency notes panel is
                            wired into the React show page.
                        </div>
                    </CardContent>
                </Card>
            </TabsContent>

            <TabsContent value="metadata">
                <AgencyMetadataTab agency={agency} />
            </TabsContent>

            <TabsContent value="activity">
                <Card>
                    <CardContent className="pt-6">
                        {activities.length === 0 ? (
                            <div className="py-10 text-center text-muted-foreground">
                                <ActivityIcon className="mx-auto mb-3 size-8 opacity-50" />
                                <p>No activity logs found for this agency.</p>
                            </div>
                        ) : (
                            <div className="flex flex-col gap-3">
                                {activities.map((activity) => (
                                    <div
                                        key={activity.id}
                                        className="flex items-start justify-between gap-4 rounded-lg border p-3"
                                    >
                                        <div>
                                            <p className="text-sm font-medium text-foreground">
                                                {activity.description}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {activity.causer_name
                                                    ? `${activity.causer_name} · `
                                                    : ''}
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
    );
}
