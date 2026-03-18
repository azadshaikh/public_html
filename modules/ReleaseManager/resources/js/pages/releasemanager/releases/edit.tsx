import { Link } from '@inertiajs/react';
import { EyeIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { ReleaseForm } from '../../../components/release-form';
import { releaseRouteParams } from '../../../lib/helpers';

export default function ReleaseEdit({
    release,
    initialValues,
    versionTypes,
    statusOptions,
    type,
}: any) {
    const title = `Edit Release ${initialValues.version}`;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Releases', href: route('releasemanager.releases.index', releaseRouteParams(type, { status: 'all' })) },
        {
            title,
            href: route('releasemanager.releases.edit', releaseRouteParams(type, { release: release.id })),
        },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={title}
            description="Manage this release version."
            headerActions={
                <Button variant="outline" asChild>
                    <Link
                        href={route('releasemanager.releases.show', releaseRouteParams(type, { release: release.id }))}
                    >
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
                submitUrl={route('releasemanager.releases.update', releaseRouteParams(type, { release: release.id }))}
                method="put"
            />
        </AppLayout>
    );
}
