import { ActivityIcon, CodeIcon, GlobeIcon, InfoIcon, ServerIcon, ShieldCheckIcon } from 'lucide-react';
import type { Dispatch, SetStateAction } from 'react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { cn } from '@/lib/utils';
import type {
    DomainDnsRecordItem,
    DomainShowData,
    DomainSslCertificateItem,
    DomainWebsiteItem,
    PlatformActivity,
} from '../../../../types/platform';
import { InfoRow } from './show-shared';

type DomainShowTabsProps = {
    activeTab: string;
    setActiveTab: Dispatch<SetStateAction<string>>;
    isMobile: boolean;
    domain: DomainShowData;
    dnsRecords: DomainDnsRecordItem[];
    sslCertificates: DomainSslCertificateItem[];
    websites: DomainWebsiteItem[];
    activities: PlatformActivity[];
};

export function DomainShowTabs({
    activeTab,
    setActiveTab,
    isMobile,
    domain,
    dnsRecords,
    sslCertificates,
    websites,
    activities,
}: DomainShowTabsProps) {
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
                    !isMobile && 'min-w-0 overflow-x-auto pr-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden',
                )}
            >
                <TabsTrigger value="general" className={cn(!isMobile && 'shrink-0')}>
                    <InfoIcon data-icon="inline-start" />
                    General
                </TabsTrigger>
                <TabsTrigger value="dns" className={cn(!isMobile && 'shrink-0')}>
                    <GlobeIcon data-icon="inline-start" />
                    DNS
                    <Badge variant="secondary" className="rounded-full px-1.5 py-0 text-[0.7rem]">
                        {dnsRecords.length}
                    </Badge>
                </TabsTrigger>
                <TabsTrigger value="ssl" className={cn(!isMobile && 'shrink-0')}>
                    <ShieldCheckIcon data-icon="inline-start" />
                    SSL
                    <Badge variant="secondary" className="rounded-full px-1.5 py-0 text-[0.7rem]">
                        {sslCertificates.length}
                    </Badge>
                </TabsTrigger>
                <TabsTrigger value="websites" className={cn(!isMobile && 'shrink-0')}>
                    <ServerIcon data-icon="inline-start" />
                    Websites
                    <Badge variant="secondary" className="rounded-full px-1.5 py-0 text-[0.7rem]">
                        {websites.length}
                    </Badge>
                </TabsTrigger>
                <TabsTrigger value="activity" className={cn(!isMobile && 'shrink-0')}>
                    <ActivityIcon data-icon="inline-start" />
                    Activity
                </TabsTrigger>
            </TabsList>

            <TabsContent value="general">
                <Card>
                    <CardContent className="pt-6">
                        <div className="grid gap-6 md:grid-cols-2">
                            <div>
                                <p className="mb-3 text-xs font-semibold tracking-wide text-muted-foreground uppercase">Lifecycle</p>
                                <div className="flex flex-col gap-2">
                                    <InfoRow label="Status">{domain.status_label ?? '—'}</InfoRow>
                                    <InfoRow label="DNS Status">{domain.dns_status ? domain.dns_status.replace(/_/g, ' ') : '—'}</InfoRow>
                                    <InfoRow label="SSL Status">{domain.ssl_status ? domain.ssl_status.replace(/_/g, ' ') : '—'}</InfoRow>
                                    <InfoRow label="DNS Mode">{domain.dns_mode ? domain.dns_mode.replace(/_/g, ' ') : '—'}</InfoRow>
                                </div>
                            </div>
                            <div>
                                <p className="mb-3 text-xs font-semibold tracking-wide text-muted-foreground uppercase">Timestamps</p>
                                <div className="flex flex-col gap-2">
                                    <InfoRow label="Created">{domain.created_at ?? '—'}</InfoRow>
                                    <InfoRow label="Updated">{domain.updated_at ?? '—'}</InfoRow>
                                    <InfoRow label="WHOIS Updated">{domain.updated_date ?? '—'}</InfoRow>
                                    <InfoRow label="Deleted At">{domain.deleted_at ?? '—'}</InfoRow>
                                </div>
                            </div>
                            <div>
                                <p className="mb-3 text-xs font-semibold tracking-wide text-muted-foreground uppercase">Name Servers</p>
                                {domain.name_servers.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">No name servers recorded.</p>
                                ) : (
                                    <div className="flex flex-col gap-2">
                                        {domain.name_servers.map((nameServer) => (
                                            <div key={nameServer} className="rounded-lg border bg-muted/30 p-3 text-sm font-medium text-foreground">
                                                {nameServer}
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                            <div>
                                <p className="mb-3 text-xs font-semibold tracking-wide text-muted-foreground uppercase">Counts</p>
                                <div className="grid gap-3 sm:grid-cols-2">
                                    {[
                                        ['Websites', domain.websites_count],
                                        ['DNS Records', domain.dns_records_count],
                                        ['Certificates', domain.ssl_certificates_count],
                                        ['Latest SSL Usage', domain.latest_certificate_websites_count],
                                    ].map(([label, value]) => (
                                        <div key={label} className="rounded-lg border bg-muted/30 p-3">
                                            <p className="text-[0.7rem] font-semibold tracking-wide text-muted-foreground uppercase">{label}</p>
                                            <p className="mt-0.5 text-sm font-bold text-foreground">{value}</p>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </TabsContent>

            <TabsContent value="dns">
                <Card>
                    <CardContent className="pt-6">
                        {dnsRecords.length === 0 ? (
                            <div className="py-10 text-center text-muted-foreground">
                                <GlobeIcon className="mx-auto mb-3 size-8 opacity-50" />
                                <p>No DNS records found for this domain.</p>
                            </div>
                        ) : (
                            <div className="flex flex-col gap-3">
                                {dnsRecords.map((record) => (
                                    <div key={record.id} className="rounded-lg border p-3">
                                        <div className="flex items-center justify-between gap-3">
                                            <p className="font-medium text-foreground">
                                                {record.type} · {record.name}
                                            </p>
                                            <span className="text-xs tracking-wide text-muted-foreground uppercase">
                                                TTL {record.ttl ?? 'auto'}
                                            </span>
                                        </div>
                                        <p className="mt-1 text-sm text-muted-foreground">{record.value}</p>
                                        {record.disabled ? (
                                            <p className="mt-2 text-xs text-muted-foreground">Disabled</p>
                                        ) : null}
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </TabsContent>

            <TabsContent value="ssl">
                <Card>
                    <CardContent className="pt-6">
                        {sslCertificates.length === 0 ? (
                            <div className="py-10 text-center text-muted-foreground">
                                <ShieldCheckIcon className="mx-auto mb-3 size-8 opacity-50" />
                                <p>No SSL certificates have been attached yet.</p>
                            </div>
                        ) : (
                            <div className="grid gap-3 md:grid-cols-2">
                                {sslCertificates.map((certificate) => (
                                    <a
                                        key={certificate.id}
                                        href={certificate.href}
                                        className="rounded-lg border p-4 transition hover:border-primary/40 hover:bg-muted/30"
                                    >
                                        <div className="flex items-center justify-between gap-3">
                                            <p className="font-medium text-foreground">{certificate.name}</p>
                                            <Badge variant={certificate.is_expired ? 'danger' : 'success'}>
                                                {certificate.is_expired ? 'Expired' : 'Active'}
                                            </Badge>
                                        </div>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            {certificate.authority} · Expires {certificate.expires_at || '—'}
                                        </p>
                                        <p className="mt-2 text-xs text-muted-foreground">
                                            Used by {certificate.websites_count} website{certificate.websites_count === 1 ? '' : 's'}
                                        </p>
                                    </a>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </TabsContent>

            <TabsContent value="websites">
                <Card>
                    <CardContent className="pt-6">
                        {websites.length === 0 ? (
                            <div className="py-10 text-center text-muted-foreground">
                                <ServerIcon className="mx-auto mb-3 size-8 opacity-50" />
                                <p>No websites are attached to this domain yet.</p>
                            </div>
                        ) : (
                            <div className="grid gap-3 md:grid-cols-2">
                                {websites.map((website) => (
                                    <a
                                        key={website.id}
                                        href={website.href}
                                        className="rounded-lg border p-4 transition hover:border-primary/40 hover:bg-muted/30"
                                    >
                                        <div className="flex items-center justify-between gap-3">
                                            <p className="font-medium text-foreground">{website.domain}</p>
                                            <Badge variant={website.uses_latest_ssl ? 'success' : 'secondary'}>
                                                {website.uses_latest_ssl ? 'Latest SSL' : 'Out of Sync'}
                                            </Badge>
                                        </div>
                                        <p className="mt-1 text-sm text-muted-foreground">{website.name}</p>
                                        <p className="mt-2 text-xs text-muted-foreground">{website.status_label}</p>
                                    </a>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </TabsContent>

            <TabsContent value="activity">
                <Card>
                    <CardContent className="pt-6">
                        {activities.length === 0 ? (
                            <div className="py-10 text-center text-muted-foreground">
                                <CodeIcon className="mx-auto mb-3 size-8 opacity-50" />
                                <p>No recent activity for this domain.</p>
                            </div>
                        ) : (
                            <div className="flex flex-col gap-3">
                                {activities.map((activity) => (
                                    <div key={activity.id} className="rounded-lg border p-3">
                                        <p className="text-sm font-medium text-foreground">{activity.description}</p>
                                        <p className="text-xs text-muted-foreground">
                                            {activity.causer_name ? `${activity.causer_name} · ` : ''}
                                            {activity.created_at ?? 'Unknown time'}
                                        </p>
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
