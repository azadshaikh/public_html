import { Link } from '@inertiajs/react';
import { AlertTriangleIcon, GlobeIcon } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type WebsiteData = {
    id: number;
    name: string | null;
    domain: string;
    status: string;
    status_label: string;
    created_at: string | null;
};

type DnsRecord = {
    id?: number;
    type?: string;
    name?: string;
    host?: string;
    value?: string;
    target?: string;
    ttl?: number;
    system?: boolean;
};

type DnsPayload = {
    dns_status?: string;
    nameservers?: string[];
    records?: DnsRecord[];
};

type AgencyDomainShowPageProps = {
    website: WebsiteData;
    dnsMode: 'managed' | 'external' | 'subdomain';
    dnsData: DnsPayload | null;
    dnsError: string | null;
};

function formatDate(value: string | null): string {
    if (!value) {
        return 'N/A';
    }

    return new Intl.DateTimeFormat('en', {
        month: 'short',
        day: '2-digit',
        year: 'numeric',
    }).format(new Date(value));
}

export default function AgencyDomainShow({
    website,
    dnsMode,
    dnsData,
    dnsError,
}: AgencyDomainShowPageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Domains', href: route('agency.domains.index') },
        { title: website.domain, href: route('agency.domains.show', website.id) },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={website.domain}
            description="View nameserver requirements and current DNS records for this domain."
            headerActions={
                <Button asChild variant="outline">
                    <Link href={route('agency.domains.index')}>Back</Link>
                </Button>
            }
        >
            <div className="space-y-6">
                <Card>
                    <CardHeader>
                        <div className="flex items-start justify-between gap-4">
                            <div className="flex items-center gap-4">
                                <div className="flex size-12 items-center justify-center rounded-2xl bg-muted">
                                    <GlobeIcon className="size-5" />
                                </div>
                                <div>
                                    <CardTitle>{website.domain}</CardTitle>
                                    <CardDescription>
                                        {website.name && website.name !== website.domain
                                            ? website.name
                                            : 'Connected website'}
                                    </CardDescription>
                                </div>
                            </div>
                            <Badge variant="secondary">{website.status_label}</Badge>
                        </div>
                    </CardHeader>
                    <CardContent className="grid gap-4 md:grid-cols-3">
                        <div>
                            <p className="text-sm text-muted-foreground">DNS Mode</p>
                            <p className="font-medium">{dnsMode}</p>
                        </div>
                        <div>
                            <p className="text-sm text-muted-foreground">DNS Status</p>
                            <p className="font-medium">{dnsData?.dns_status ?? 'Unknown'}</p>
                        </div>
                        <div>
                            <p className="text-sm text-muted-foreground">Created</p>
                            <p className="font-medium">{formatDate(website.created_at)}</p>
                        </div>
                    </CardContent>
                </Card>

                {dnsError ? (
                    <Card className="border-danger/40">
                        <CardContent className="flex items-start gap-3 pt-6">
                            <AlertTriangleIcon className="mt-0.5 size-5 text-danger" />
                            <p className="text-sm">{dnsError}</p>
                        </CardContent>
                    </Card>
                ) : null}

                {dnsMode === 'subdomain' ? (
                    <Card>
                        <CardContent className="pt-6 text-sm text-muted-foreground">
                            DNS is automatically managed for subdomain-based websites.
                            No manual changes are required.
                        </CardContent>
                    </Card>
                ) : null}

                {dnsData?.nameservers && dnsData.nameservers.length > 0 ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>Nameservers</CardTitle>
                            <CardDescription>
                                Configure these at your registrar when using managed DNS.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            {dnsData.nameservers.map((nameserver) => (
                                <div
                                    key={nameserver}
                                    className="rounded-lg border bg-muted px-3 py-2 font-mono text-sm"
                                >
                                    {nameserver}
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                ) : null}

                {dnsData?.records && dnsData.records.length > 0 ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>DNS Records</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Type</TableHead>
                                        <TableHead>Name</TableHead>
                                        <TableHead>Value</TableHead>
                                        <TableHead>TTL</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {dnsData.records.map((record, index) => (
                                        <TableRow key={record.id ?? `${record.type}-${index}`}>
                                            <TableCell>{record.type ?? 'N/A'}</TableCell>
                                            <TableCell>{record.name ?? record.host ?? '@'}</TableCell>
                                            <TableCell className="max-w-[360px] truncate">
                                                {record.value ?? record.target ?? 'N/A'}
                                            </TableCell>
                                            <TableCell>{record.ttl ?? 300}</TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                ) : null}
            </div>
        </AppLayout>
    );
}
