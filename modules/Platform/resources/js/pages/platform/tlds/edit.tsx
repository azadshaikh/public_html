import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import TldForm from '../../../components/tlds/tld-form';
import type { TldFormValues } from '../../../types/platform';

type TldsEditPageProps = {
    tld: {
        id: number;
        tld: string;
    };
    initialValues: TldFormValues;
};

export default function TldsEdit(props: TldsEditPageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        {
            title: 'Platform',
            href: route('platform.tlds.index', { status: 'all' }),
        },
        {
            title: 'TLDs',
            href: route('platform.tlds.index', { status: 'all' }),
        },
        {
            title: props.tld.tld,
            href: route('platform.tlds.show', props.tld.id),
        },
        { title: 'Edit', href: route('platform.tlds.edit', props.tld.id) },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={`Edit ${props.tld.tld}`}
            description="Update pricing, visibility flags, and WHOIS routing for this TLD."
        >
            <TldForm
                mode="edit"
                tldRecord={props.tld}
                initialValues={props.initialValues}
            />
        </AppLayout>
    );
}
