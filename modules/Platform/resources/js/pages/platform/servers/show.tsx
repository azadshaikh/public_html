import { Link } from '@inertiajs/react';
import { ActivityIcon, HardDriveIcon, KeyRoundIcon, PencilIcon, ServerCogIcon, Settings2Icon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { PlatformActivity, ServerProvisioningStep, ServerSecretItem, ServerShowData } from '../../../types/platform';

type ServersShowPageProps = {
    server: ServerShowData;
    provisioningSteps: ServerProvisioningStep[];
    websiteCounts: {
        total: number;
        active: number;
        inactive: number;
        provisioning: number;
    };
    secrets: ServerSecretItem[];
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

export default function ServersShow({ server, provisioningSteps, websiteCounts, secrets, activities }: ServersShowPageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Platform', href: route('platform.servers.index', { status: 'all' }) },
        { title: 'Servers', href: route('platform.servers.index', { status: 'all' }) },
        { title: server.name, href: route('platform.servers.show', server.id) },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={server.name}
            description="Inspect provisioning progress, capacity, credentials, and recent operations."
            headerActions={
                <Button variant="outline" asChild>
                    <Link href={route('platform.servers.edit', server.id)}>
                        <PencilIcon data-icon="inline-start" />
                        Edit server
                    </Link>
                </Button>
            }
        >
            <div className="flex flex-col gap-6">
                <Card>
                    <CardHeader>
                        <div className="flex items-center gap-2">
                            <HardDriveIcon className="size-4 text-muted-foreground" />
                            <CardTitle>Server overview</CardTitle>
                        </div>
                        <CardDescription>Connection details, provider assignment, and operating metadata.</CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <MetricCard label="UID" value={server.uid} />
                        <MetricCard label="IP" value={server.ip} />
                        <MetricCard label="FQDN" value={server.fqdn} />
                        <MetricCard label="Provider" value={server.provider_name} />
                        <MetricCard label="Status" value={server.status_label} />
                        <MetricCard label="Provisioning" value={server.provisioning_status} />
                        <MetricCard label="Type" value={server.type_label} />
                        <MetricCard label="Hestia port" value={server.port} />
                        <MetricCard label="SSH user" value={server.ssh_user} />
                        <MetricCard label="SSH port" value={server.ssh_port} />
                        <MetricCard label="Astero version" value={server.astero_version} />
                        <MetricCard label="Hestia version" value={server.hestia_version} />
                        <MetricCard label="Server OS" value={server.server_os} />
                        <MetricCard label="Uptime" value={server.server_uptime} />
                        <MetricCard label="Current domains" value={server.current_domains} />
                        <MetricCard label="Max domains" value={server.max_domains} />
                    </CardContent>
                </Card>

                <div className="grid gap-6 xl:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <Settings2Icon className="size-4 text-muted-foreground" />
                                <CardTitle>Provisioning steps</CardTitle>
                            </div>
                            <CardDescription>Current bootstrap state for this server.</CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-3">
                            {provisioningSteps.length === 0 ? (
                                <p className="text-sm text-muted-foreground">No provisioning steps recorded.</p>
                            ) : (
                                provisioningSteps.map((step) => (
                                    <div key={step.key} className="rounded-lg border p-3">
                                        <div className="flex items-center justify-between gap-3">
                                            <p className="font-medium text-foreground">{step.title}</p>
                                            <span className="text-xs uppercase tracking-wide text-muted-foreground">
                                                {step.status}
                                            </span>
                                        </div>
                                        <p className="mt-1 text-sm text-muted-foreground">{step.description}</p>
                                        {step.message ? <p className="mt-2 text-xs text-muted-foreground">{step.message}</p> : null}
                                    </div>
                                ))
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <ServerCogIcon className="size-4 text-muted-foreground" />
                                <CardTitle>Website capacity</CardTitle>
                            </div>
                            <CardDescription>Summary of websites currently assigned to this server.</CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4 md:grid-cols-2">
                            <MetricCard label="Total websites" value={websiteCounts.total} />
                            <MetricCard label="Active websites" value={websiteCounts.active} />
                            <MetricCard label="Inactive websites" value={websiteCounts.inactive} />
                            <MetricCard label="Provisioning websites" value={websiteCounts.provisioning} />
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-6 xl:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <KeyRoundIcon className="size-4 text-muted-foreground" />
                                <CardTitle>Stored secrets</CardTitle>
                            </div>
                            <CardDescription>Available credential entries attached to this server.</CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-3">
                            {secrets.length === 0 ? (
                                <p className="text-sm text-muted-foreground">No stored secrets.</p>
                            ) : (
                                secrets.map((secret) => (
                                    <div key={secret.id} className="rounded-lg border p-3 text-sm">
                                        <p className="font-medium text-foreground">{secret.label}</p>
                                        <p className="text-muted-foreground">{secret.key}</p>
                                    </div>
                                ))
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <ActivityIcon className="size-4 text-muted-foreground" />
                                <CardTitle>Recent activity</CardTitle>
                            </div>
                            <CardDescription>Latest operations executed against this server.</CardDescription>
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
            </div>
        </AppLayout>
    );
}
