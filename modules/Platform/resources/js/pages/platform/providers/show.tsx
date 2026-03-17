import { Link } from '@inertiajs/react';
import { ActivityIcon, PencilIcon, ServerCogIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { AgencyRelationItem, PlatformActivity, ProviderShowData } from '../../../types/platform';

type ProvidersShowPageProps = {
    provider: ProviderShowData;
    websites: AgencyRelationItem[];
    domains: AgencyRelationItem[];
    servers: AgencyRelationItem[];
    agencies: AgencyRelationItem[];
    activities: PlatformActivity[];
};

function MetricCard({ label, value }: { label: string; value: string | number | null | undefined }) {
    return (
        <div className="rounded-lg border bg-muted/20 p-4">
            <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">{label}</p>
            <p className="mt-1 text-sm font-medium text-foreground">{value || '—'}</p>
        </div>
    );
}

function RelationList({ title, description, items }: { title: string; description: string; items: AgencyRelationItem[] }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>{title}</CardTitle>
                <CardDescription>{description}</CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-3">
                {items.length === 0 ? (
                    <p className="text-sm text-muted-foreground">No related records yet.</p>
                ) : (
                    items.map((item) => {
                        const content = (
                            <>
                                <p className="font-medium text-foreground">{item.name}</p>
                                {item.subtitle ? <p className="text-sm text-muted-foreground">{item.subtitle}</p> : null}
                                {item.status ? <p className="text-xs uppercase tracking-wide text-muted-foreground">{item.status}</p> : null}
                            </>
                        );

                        return item.href ? (
                            <Link key={item.id} href={item.href} className="rounded-lg border p-3 transition hover:border-primary/40 hover:bg-muted/30">
                                {content}
                            </Link>
                        ) : (
                            <div key={item.id} className="rounded-lg border p-3">
                                {content}
                            </div>
                        );
                    })
                )}
            </CardContent>
        </Card>
    );
}

export default function ProvidersShow({ provider, websites, domains, servers, agencies, activities }: ProvidersShowPageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Platform', href: route('platform.providers.index', { status: 'all' }) },
        { title: 'Providers', href: route('platform.providers.index', { status: 'all' }) },
        { title: provider.name, href: route('platform.providers.show', provider.id) },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={provider.name}
            description="Inspect provider coverage, credential presence, and related platform resources."
            headerActions={
                <Button variant="outline" asChild>
                    <Link href={route('platform.providers.edit', provider.id)}>
                        <PencilIcon data-icon="inline-start" />
                        Edit provider
                    </Link>
                </Button>
            }
        >
            <div className="flex flex-col gap-6">
                <Card>
                    <CardHeader>
                        <div className="flex items-center gap-2">
                            <ServerCogIcon className="size-4 text-muted-foreground" />
                            <CardTitle>Provider overview</CardTitle>
                        </div>
                        <CardDescription>Account classification, vendor metadata, and relationship coverage.</CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <MetricCard label="Name" value={provider.name} />
                        <MetricCard label="Email" value={provider.email} />
                        <MetricCard label="Type" value={provider.type_label} />
                        <MetricCard label="Vendor" value={provider.vendor_label} />
                        <MetricCard label="Status" value={provider.status_label} />
                        <MetricCard label="Websites" value={provider.websites_count} />
                        <MetricCard label="Domains" value={provider.domains_count} />
                        <MetricCard label="Servers" value={provider.servers_count} />
                        <MetricCard label="Agencies" value={provider.agencies_count} />
                        <MetricCard label="Credential keys" value={provider.credential_keys.length ? provider.credential_keys.join(', ') : 'None'} />
                        <MetricCard label="Created" value={provider.created_at} />
                        <MetricCard label="Updated" value={provider.updated_at} />
                    </CardContent>
                </Card>

                <div className="grid gap-6 xl:grid-cols-2">
                    <RelationList title="Websites" description="Websites currently routed through this provider." items={websites} />
                    <RelationList title="Domains" description="Domains using this provider as registrar or linked integration." items={domains} />
                    <RelationList title="Servers" description="Servers provisioned or associated with this provider." items={servers} />
                    <RelationList title="Agencies" description="Agencies with DNS or CDN affinity to this provider." items={agencies} />
                </div>

                <Card>
                    <CardHeader>
                        <div className="flex items-center gap-2">
                            <ActivityIcon className="size-4 text-muted-foreground" />
                            <CardTitle>Recent activity</CardTitle>
                        </div>
                        <CardDescription>Latest provider synchronization and lifecycle events.</CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-3">
                        {activities.length === 0 ? (
                            <p className="text-sm text-muted-foreground">No recent activity.</p>
                        ) : (
                            activities.map((activity) => (
                                <div key={activity.id} className="rounded-lg border p-3">
                                    <p className="text-sm font-medium text-foreground">{activity.description}</p>
                                    <p className="text-xs text-muted-foreground">
                                        {activity.causer_name ? `${activity.causer_name} · ` : ''}
                                        {activity.created_at || 'Unknown time'}
                                    </p>
                                </div>
                            ))
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
