import { Link } from '@inertiajs/react';
import { EyeIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { ReleaseForm } from '../../../components/release-form';

export default function ReleaseEdit({ release, initialValues, versionTypes, statusOptions, type }: any) {
    const title = `Edit Release ${initialValues.version}`;
    const routeNamespace = type === 'module' ? 'releasemanager.module' : 'releasemanager.application';

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Releases', href: route(`${routeNamespace}.index`) },
        { title, href: route(`${routeNamespace}.edit`, { release: release.id }) },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={title}
            description="Manage this release version."
            headerActions={
                <Button variant="outline" asChild>
                    <Link href={route(`${routeNamespace}.show`, { release: release.id })}>
                        <EyeIcon data-icon="inline-start" />
                        View Page
                    </Link>
                </Button>
            }
        >
            <ReleaseForm
                initialValues={initialValues}
                versionTypes={versionTypes}
                statusOptions={statusOptions}
                type={type}
                submitUrl={route(`${routeNamespace}.update`, { release: release.id })}
                method="put"
            />
        </AppLayout>
    );
}
