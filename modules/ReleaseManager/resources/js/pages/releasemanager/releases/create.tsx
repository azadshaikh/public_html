import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { ReleaseForm } from '../../../components/release-form';
import { releaseRouteParams } from '../../../lib/helpers';

export default function ReleaseCreate({ initialValues, versionTypes, statusOptions, type }: any) {
    const title = type === 'application' ? 'Add Application Release' : 'Add Module Release';

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Releases', href: route('releasemanager.releases.index', releaseRouteParams(type, { status: 'all' })) },
        { title, href: route('releasemanager.releases.create', releaseRouteParams(type)) },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs} title={title} description="Create a new release or module version">
            <ReleaseForm
                initialValues={initialValues}
                versionTypes={versionTypes}
                statusOptions={statusOptions}
                type={type}
                submitUrl={route('releasemanager.releases.store', releaseRouteParams(type))}
                method="post"
            />
        </AppLayout>
    );
}
