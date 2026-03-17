import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import TldForm from '../../../components/tlds/tld-form';
import type { TldFormValues } from '../../../types/platform';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Platform', href: route('platform.tlds.index', { status: 'all' }) },
    { title: 'TLDs', href: route('platform.tlds.index', { status: 'all' }) },
    { title: 'Create', href: route('platform.tlds.create') },
];

type TldsCreatePageProps = {
    initialValues: TldFormValues;
};

export default function TldsCreate(props: TldsCreatePageProps) {
    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Create TLD"
            description="Add a new domain extension with pricing, WHOIS, and merchandising metadata."
        >
            <TldForm mode="create" initialValues={props.initialValues} />
        </AppLayout>
    );
}
