import { Link } from '@inertiajs/react';
import {
    ActivityIcon,
    GlobeIcon,
    PencilIcon,
    PlusIcon,
    ShieldCheckIcon,
    SparklesIcon,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type {
    DomainDnsRecordItem,
    DomainShowData,
    DomainSslCertificateItem,
    DomainWebsiteItem,
    PlatformActivity,
} from '../../../types/platform';

type DomainsShowPageProps = {
    domain: DomainShowData;
    dnsRecords: DomainDnsRecordItem[];
    sslCertificates: DomainSslCertificateItem[];
    websites: DomainWebsiteItem[];
    activities: PlatformActivity[];
};

function MetricCard({
    label,
    value,
}: {
    label: string;
    value: string | number | null | undefined;
}) {
    return (
        <div className="rounded-lg border bg-muted/20 p-4">
            <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                {label}
            </p>
            <p className="mt-1 text-sm font-medium text-foreground">
                {value || '—'}
            </p>
        </div>
    );
}

export default function DomainsShow({
    domain,
    dnsRecords,
    sslCertificates,
    websites,
    activities,
}: DomainsShowPageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        {
            title: 'Platform',
            href: route('platform.domains.index', { status: 'all' }),
        },
        {
            title: 'Domains',
            href: route('platform.domains.index', { status: 'all' }),
        },
        { title: domain.name, href: route('platform.domains.show', domain.id) },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={domain.name}
            description="Inspect ownership, DNS state, certificate coverage, and recent activity for this domain."
            headerActions={
                <div className="flex flex-wrap items-center gap-3">
                    <Button variant="outline" asChild>
                        <Link
                            href={route(
                                'platform.domains.ssl-certificates.generate-self-signed',
                                domain.id,
                            )}
                        >
                            <SparklesIcon data-icon="inline-start" />
                            Generate self-signed
                        </Link>
                    </Button>
                    <Button variant="outline" asChild>
                        <Link
                            href={route(
                                'platform.domains.ssl-certificates.create',
                                domain.id,
                            )}
                        >
                            <PlusIcon data-icon="inline-start" />
                            Add certificate
                        </Link>
                    </Button>
                    <Button variant="outline" asChild>
                        <Link href={route('platform.domains.edit', domain.id)}>
                            <PencilIcon data-icon="inline-start" />
                            Edit domain
                        </Link>
                    </Button>
                    <Button variant="outline" asChild>
                        <Link href={route('platform.dns.index', { status: 'all', domain_id: domain.id })}>
                            <GlobeIcon data-icon="inline-start" />
                            Manage DNS
                        </Link>
                    </Button>
                </div>
            }
        >
            <div className="flex flex-col gap-6">
                <Card>
                    <CardHeader>
                        <div className="flex items-center gap-2">
                            <GlobeIcon className="size-4 text-muted-foreground" />
                            <CardTitle>Domain overview</CardTitle>
                        </div>
                        <CardDescription>
                            Core ownership, registrar, and DNS lifecycle
                            details.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <MetricCard label="Domain" value={domain.name} />
                        <MetricCard label="Type" value={domain.type_label} />
                        <MetricCard
                            label="Status"
                            value={domain.status_label}
                        />
                        <MetricCard label="Agency" value={domain.agency_name} />
                        <MetricCard
                            label="Registrar"
                            value={domain.registrar_name}
                        />
                        <MetricCard
                            label="DNS provider"
                            value={domain.dns_provider}
                        />
                        <MetricCard
                            label="DNS zone"
                            value={domain.dns_zone_id}
                        />
                        <MetricCard
                            label="Registered"
                            value={domain.registered_date}
                        />
                        <MetricCard
                            label="Expires"
                            value={domain.expires_date}
                        />
                        <MetricCard
                            label="Updated"
                            value={domain.updated_date}
                        />
                        <MetricCard
                            label="DNS records"
                            value={domain.dns_records_count}
                        />
                        <MetricCard
                            label="Websites"
                            value={domain.websites_count}
                        />
                        <MetricCard
                            label="Certificates"
                            value={domain.ssl_certificates_count}
                        />
                        <MetricCard
                            label="Latest SSL in use by"
                            value={`${domain.latest_certificate_websites_count} website${domain.latest_certificate_websites_count === 1 ? '' : 's'}`}
                        />
                        <MetricCard
                            label="Latest certificate expiry"
                            value={domain.latest_certificate_expires_at}
                        />
                        <MetricCard label="Created" value={domain.created_at} />
                        <MetricCard
                            label="Last changed"
                            value={domain.updated_at}
                        />
                    </CardContent>
                </Card>

                <div className="grid gap-6 xl:grid-cols-3">
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <ShieldCheckIcon className="size-4 text-muted-foreground" />
                                <CardTitle>SSL certificates</CardTitle>
                            </div>
                            <CardDescription>
                                Certificates currently attached to this domain.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-3">
                            {sslCertificates.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No SSL certificates have been attached yet.
                                </p>
                            ) : (
                                sslCertificates.map((certificate) => (
                                    <Link
                                        key={certificate.id}
                                        href={certificate.href}
                                        className="rounded-lg border p-3 transition hover:border-primary/40 hover:bg-muted/30"
                                    >
                                        <div className="flex items-center justify-between gap-3">
                                            <p className="font-medium text-foreground">
                                                {certificate.name}
                                            </p>
                                            <span className="text-xs tracking-wide text-muted-foreground uppercase">
                                                {certificate.is_expired
                                                    ? 'Expired'
                                                    : 'Active'}
                                            </span>
                                        </div>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            {certificate.authority} · Expires{' '}
                                            {certificate.expires_at || '—'}
                                        </p>
                                        <p className="mt-2 text-xs text-muted-foreground">
                                            Used by {certificate.websites_count} website{certificate.websites_count === 1 ? '' : 's'}
                                        </p>
                                    </Link>
                                ))
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Websites on this domain</CardTitle>
                            <CardDescription>
                                Root and subdomain websites currently attached to this domain record.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-3">
                            {websites.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No websites are attached to this domain yet.
                                </p>
                            ) : (
                                websites.map((website) => (
                                    <Link
                                        key={website.id}
                                        href={website.href}
                                        className="rounded-lg border p-3 transition hover:border-primary/40 hover:bg-muted/30"
                                    >
                                        <div className="flex items-center justify-between gap-3">
                                            <p className="font-medium text-foreground">
                                                {website.domain}
                                            </p>
                                            <span className="text-xs tracking-wide text-muted-foreground uppercase">
                                                {website.status_label}
                                            </span>
                                        </div>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            {website.name}
                                        </p>
                                        <p className="mt-2 text-xs text-muted-foreground">
                                            {website.uses_latest_ssl ? 'Using latest shared SSL' : 'Not using latest shared SSL'}
                                        </p>
                                    </Link>
                                ))
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Name servers</CardTitle>
                            <CardDescription>
                                Current WHOIS name server values stored for this
                                domain.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-3">
                            {domain.name_servers.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No name servers are recorded yet.
                                </p>
                            ) : (
                                domain.name_servers.map((nameServer) => (
                                    <div
                                        key={nameServer}
                                        className="rounded-lg border p-3 text-sm font-medium text-foreground"
                                    >
                                        {nameServer}
                                    </div>
                                ))
                            )}
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-6 xl:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between gap-3">
                                <div>
                                    <CardTitle>DNS records</CardTitle>
                                    <CardDescription>Latest known DNS records attached to this domain.</CardDescription>
                                </div>
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={route('platform.dns.create', { domain_id: domain.id })}>
                                        <PlusIcon data-icon="inline-start" />
                                        Add record
                                    </Link>
                                </Button>
                            </div>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-3">
                            {dnsRecords.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No DNS records found.
                                </p>
                            ) : (
                                dnsRecords.map((record) => (
                                    <div
                                        key={record.id}
                                        className="rounded-lg border p-3"
                                    >
                                        <div className="flex items-center justify-between gap-3">
                                            <p className="font-medium text-foreground">
                                                {record.type} · {record.name}
                                            </p>
                                            <span className="text-xs tracking-wide text-muted-foreground uppercase">
                                                TTL {record.ttl ?? 'auto'}
                                            </span>
                                        </div>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            {record.value}
                                        </p>
                                        {record.disabled ? (
                                            <p className="mt-2 text-xs text-muted-foreground">
                                                Disabled
                                            </p>
                                        ) : null}
                                    </div>
                                ))
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <ActivityIcon className="size-4 text-muted-foreground" />
                                <CardTitle>Recent activity</CardTitle>
                            </div>
                            <CardDescription>
                                Most recent WHOIS refreshes, metadata changes,
                                and certificate updates.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-3">
                            {activities.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No recent activity.
                                </p>
                            ) : (
                                activities.map((activity) => (
                                    <div
                                        key={activity.id}
                                        className="rounded-lg border p-3"
                                    >
                                        <p className="text-sm font-medium text-foreground">
                                            {activity.description}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {activity.causer_name
                                                ? `${activity.causer_name} · `
                                                : ''}
                                            {activity.created_at ||
                                                'Unknown time'}
                                        </p>
                                    </div>
                                ))
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
