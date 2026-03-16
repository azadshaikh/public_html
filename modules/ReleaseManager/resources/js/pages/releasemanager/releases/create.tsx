import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { ReleaseForm } from '../../../components/release-form';

export default function ReleaseCreate({ initialValues, versionTypes, statusOptions, type }: any) {
    const title = type === 'application' ? 'Add Application Release' : 'Add Module Release';
    const routeNamespace = type === 'module' ? 'releasemanager.module' : 'releasemanager.application';

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Releases', href: route(`${routeNamespace}.index`) },
        { title, href: route(`${routeNamespace}.create`) },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs} title={title} description="Create a new release or module version">
            <ReleaseForm
                initialValues={initialValues}
                versionTypes={versionTypes}
                statusOptions={statusOptions}
                type={type}
                submitUrl={route(`${routeNamespace}.store`)}
                method="post"
            />
        </AppLayout>
    );
}
