import { Link } from '@inertiajs/react';
import { PencilIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Badge } from '@/components/ui/badge';
import { format } from 'date-fns';

export default function ReleaseShow({ release, type, statusOptions }: any) {
    const title = `Release ${release.version}`;
    const displayType = type === 'application' ? 'Application Releases' : 'Module Releases';
    const routeNamespace = type === 'module' ? 'releasemanager.module' : 'releasemanager.application';

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Releases', href: route(`${routeNamespace}.index`) },
        { title, href: route(`${routeNamespace}.show`, { release: release.id }) },
    ];

    const currentStatusLabel = statusOptions?.find((o: any) => o.value === release.status)?.label || release.status || 'Draft';

    // Auto-resolve badge variant
    const getBadgeVariant = (val: string) => {
        val = String(val).toLowerCase();
        if (['published', 'success', 'active'].includes(val)) return 'success';
        if (['draft', 'pending', 'warning'].includes(val)) return 'warning';
        if (['deprecate', 'failed', 'danger', 'trash'].includes(val)) return 'destructive';
        if (['module'].includes(val)) return 'info';
        if (['application'].includes(val)) return 'secondary';
        return 'secondary';
    };

    const formatBytes = (bytes: number) => {
        if (!bytes || bytes === 0) return '—';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    };

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={title}
            description={`${displayType} details`}
            headerActions={
                <Button asChild>
                    <Link href={route(`${routeNamespace}.edit`, { release: release.id })}>
                        <PencilIcon data-icon="inline-start" />
                        Edit Release
                    </Link>
                </Button>
            }
        >
            <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div className="flex flex-col gap-6 lg:col-span-2">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle>Details</CardTitle>
                            <Badge variant={getBadgeVariant(release.status)}>{currentStatusLabel}</Badge>
                        </CardHeader>
                        <CardContent className="grid gap-4 pt-4">
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4 border-b pb-4">
                                <div className="text-sm font-medium text-muted-foreground">Release Type</div>
                                <div className="md:col-span-2 capitalize">{release.release_type || '—'}</div>
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4 border-b pb-4">
                                <div className="text-sm font-medium text-muted-foreground">Package Identifier</div>
                                <div className="md:col-span-2">{release.package_identifier || '—'}</div>
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4 border-b pb-4">
                                <div className="text-sm font-medium text-muted-foreground">Version Type</div>
                                <div className="md:col-span-2 capitalize">{release.version_type || '—'}</div>
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4 border-b pb-4">
                                <div className="text-sm font-medium text-muted-foreground">Release Date</div>
                                <div className="md:col-span-2">
                                    {release.release_at ? format(new Date(release.release_at), 'PPP') : '—'}
                                </div>
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4 border-b pb-4">
                                <div className="text-sm font-medium text-muted-foreground">Release File</div>
                                <div className="md:col-span-2 break-all">
                                    {release.release_link ? (
                                        <a href={release.release_link} target="_blank" rel="noopener noreferrer" className="text-primary hover:underline">
                                            {release.file_name || release.release_link}
                                        </a>
                                    ) : '—'}
                                </div>
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4 border-b pb-4">
                                <div className="text-sm font-medium text-muted-foreground">File Size</div>
                                <div className="md:col-span-2">{formatBytes(release.file_size)}</div>
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4 border-b pb-4">
                                <div className="text-sm font-medium text-muted-foreground">Checksum</div>
                                <div className="md:col-span-2 font-mono text-xs break-all">
                                    {release.checksum || '—'}
                                </div>
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div className="text-sm font-medium text-muted-foreground">Change Log</div>
                                <div className="md:col-span-2 whitespace-pre-wrap text-sm">
                                    {release.change_log || '—'}
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div className="flex flex-col gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Record Info</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            <div className="flex justify-between border-b pb-2">
                                <div className="text-sm text-muted-foreground">Created</div>
                                <div className="text-sm">
                                    {release.created_at ? format(new Date(release.created_at), 'PPP') : '—'}
                                </div>
                            </div>
                            <div className="flex justify-between">
                                <div className="text-sm text-muted-foreground">Updated</div>
                                <div className="text-sm">
                                    {release.updated_at ? format(new Date(release.updated_at), 'PPP') : '—'}
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
