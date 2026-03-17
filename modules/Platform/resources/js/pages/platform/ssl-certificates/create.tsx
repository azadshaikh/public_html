import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import SslCertificateForm from '../../../components/ssl-certificates/ssl-certificate-form';
import type { PlatformOption, SslCertificateFormValues } from '../../../types/platform';

type SslCertificatesCreatePageProps = {
    domain: {
        id: number;
        name: string;
    };
    initialValues: SslCertificateFormValues;
    certificateAuthorityOptions: PlatformOption[];
};

export default function SslCertificatesCreate(props: SslCertificatesCreatePageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Platform', href: route('platform.domains.index', { status: 'all' }) },
        { title: 'Domains', href: route('platform.domains.index', { status: 'all' }) },
        { title: props.domain.name, href: route('platform.domains.show', props.domain.id) },
        { title: 'Add certificate', href: route('platform.domains.ssl-certificates.create', props.domain.id) },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={`Add certificate for ${props.domain.name}`}
            description="Attach a PEM certificate bundle to this domain and track its lifecycle in the platform."
        >
            <SslCertificateForm
                mode="create"
                domain={props.domain}
                initialValues={props.initialValues}
                certificateAuthorityOptions={props.certificateAuthorityOptions}
            />
        </AppLayout>
    );
}
