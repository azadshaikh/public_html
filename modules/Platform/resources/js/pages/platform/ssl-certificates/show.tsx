import { Link } from '@inertiajs/react';
import { DownloadIcon, PencilIcon, ShieldCheckIcon } from 'lucide-react';
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
import type { SslCertificateShowData } from '../../../types/platform';

type SslCertificatesShowPageProps = {
    domain: {
        id: number;
        name: string;
    };
    certificate: SslCertificateShowData;
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

export default function SslCertificatesShow({
    domain,
    certificate,
}: SslCertificatesShowPageProps) {
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
        {
            title: certificate.name,
            href: route('platform.domains.ssl-certificates.show', [
                domain.id,
                certificate.id,
            ]),
        },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={certificate.name}
            description="Inspect the current certificate chain, covered domains, and expiry posture for this domain."
            headerActions={
                <div className="flex flex-wrap items-center gap-3">
                    <Button variant="outline" asChild>
                        <a href={certificate.download_private_key_url}>
                            <DownloadIcon data-icon="inline-start" />
                            Download key
                        </a>
                    </Button>
                    <Button variant="outline" asChild>
                        <a href={certificate.download_certificate_url}>
                            <DownloadIcon data-icon="inline-start" />
                            Download certificate
                        </a>
                    </Button>
                    <Button variant="outline" asChild>
                        <Link
                            href={route(
                                'platform.domains.ssl-certificates.edit',
                                [domain.id, certificate.id],
                            )}
                        >
                            <PencilIcon data-icon="inline-start" />
                            Edit certificate
                        </Link>
                    </Button>
                </div>
            }
        >
            <div className="flex flex-col gap-6">
                <Card>
                    <CardHeader>
                        <div className="flex items-center gap-2">
                            <ShieldCheckIcon className="size-4 text-muted-foreground" />
                            <CardTitle>Certificate overview</CardTitle>
                        </div>
                        <CardDescription>
                            Issuer details, subject metadata, and current expiry
                            health.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <MetricCard
                            label="Authority"
                            value={certificate.certificate_authority}
                        />
                        <MetricCard label="Issuer" value={certificate.issuer} />
                        <MetricCard
                            label="Subject"
                            value={certificate.subject}
                        />
                        <MetricCard
                            label="Wildcard"
                            value={certificate.is_wildcard ? 'Yes' : 'No'}
                        />
                        <MetricCard
                            label="Issued at"
                            value={certificate.issued_at}
                        />
                        <MetricCard
                            label="Expires at"
                            value={certificate.expires_at}
                        />
                        <MetricCard
                            label="Expired"
                            value={certificate.is_expired ? 'Yes' : 'No'}
                        />
                        <MetricCard
                            label="Expiring soon"
                            value={certificate.is_expiring_soon ? 'Yes' : 'No'}
                        />
                        <MetricCard
                            label="Days until expiry"
                            value={certificate.days_until_expiry}
                        />
                        <MetricCard
                            label="Serial number"
                            value={certificate.serial_number}
                        />
                        <MetricCard
                            label="Fingerprint"
                            value={certificate.fingerprint}
                        />
                        <MetricCard
                            label="Created"
                            value={certificate.created_at}
                        />
                        <MetricCard
                            label="Updated"
                            value={certificate.updated_at}
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Covered domains</CardTitle>
                        <CardDescription>
                            All names included in the certificate subject or SAN
                            list.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        {certificate.domains.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No SAN domains were parsed.
                            </p>
                        ) : (
                            certificate.domains.map((hostname) => (
                                <div
                                    key={hostname}
                                    className="rounded-lg border p-3 text-sm font-medium text-foreground"
                                >
                                    {hostname}
                                </div>
                            ))
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
