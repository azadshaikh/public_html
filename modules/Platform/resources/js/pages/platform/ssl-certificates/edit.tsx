import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import SslCertificateForm from '../../../components/ssl-certificates/ssl-certificate-form';
import type { PlatformOption, SslCertificateFormValues, SslCertificateShowData } from '../../../types/platform';

type SslCertificatesEditPageProps = {
    domain: {
        id: number;
        name: string;
    };
    certificate: {
        id: number;
        name: string;
    };
    initialValues: SslCertificateFormValues;
    certificateDetails: SslCertificateShowData;
    certificateAuthorityOptions: PlatformOption[];
};

function MetricCard({ label, value }: { label: string; value: string | number | null | undefined }) {
    return (
        <div className="rounded-lg border bg-muted/20 p-4">
            <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">{label}</p>
            <p className="mt-1 text-sm font-medium text-foreground">{value || '—'}</p>
        </div>
    );
}

export default function SslCertificatesEdit(props: SslCertificatesEditPageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Platform', href: route('platform.domains.index', { status: 'all' }) },
        { title: 'Domains', href: route('platform.domains.index', { status: 'all' }) },
        { title: props.domain.name, href: route('platform.domains.show', props.domain.id) },
        {
            title: props.certificate.name,
            href: route('platform.domains.ssl-certificates.show', [props.domain.id, props.certificate.id]),
        },
        { title: 'Edit', href: route('platform.domains.ssl-certificates.edit', [props.domain.id, props.certificate.id]) },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={`Edit ${props.certificate.name}`}
            description="Update the PEM material or metadata stored for this SSL certificate."
        >
            <div className="flex flex-col gap-6">
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <MetricCard label="Authority" value={props.certificateDetails.certificate_authority} />
                    <MetricCard label="Issuer" value={props.certificateDetails.issuer} />
                    <MetricCard label="Expires" value={props.certificateDetails.expires_at} />
                    <MetricCard label="Wildcard" value={props.certificateDetails.is_wildcard ? 'Yes' : 'No'} />
                </div>

                <SslCertificateForm
                    mode="edit"
                    domain={props.domain}
                    certificate={props.certificate}
                    initialValues={props.initialValues}
                    certificateAuthorityOptions={props.certificateAuthorityOptions}
                />
            </div>
        </AppLayout>
    );
}
