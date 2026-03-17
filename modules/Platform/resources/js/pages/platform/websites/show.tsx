import { Link } from '@inertiajs/react';
import { ActivityIcon, ExternalLinkIcon, GlobeIcon, KeyRoundIcon, PencilIcon, RefreshCwIcon, ShieldCheckIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type {
    PlatformActivity,
    WebsiteProvisioningStep,
    WebsiteSecretItem,
    WebsiteShowData,
    WebsiteUpdateItem,
} from '../../../types/platform';

type WebsitesShowPageProps = {
    website: WebsiteShowData;
    provisioningSteps: WebsiteProvisioningStep[];
    updates: WebsiteUpdateItem[];
    secrets: WebsiteSecretItem[];
    activities: PlatformActivity[];
    pullzoneId: string | null;
};

function MetricCard({ label, value }: { label: string; value: string | number | null | undefined }) {
    return (
        <div className="rounded-lg border bg-muted/20 p-4">
            <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">{label}</p>
            <p className="mt-1 text-sm font-medium text-foreground">{value || '—'}</p>
        </div>
    );
}

export default function WebsitesShow({
    website,
    provisioningSteps,
    updates,
    secrets,
    activities,
    pullzoneId,
}: WebsitesShowPageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Platform', href: route('platform.websites.index', { status: 'all' }) },
        { title: 'Websites', href: route('platform.websites.index', { status: 'all' }) },
        { title: website.name, href: route('platform.websites.show', website.id) },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={website.name}
            description="Inspect provisioning state, runtime metadata, and infrastructure links for this website."
            headerActions={
                <div className="flex flex-wrap items-center gap-3">
                    {website.domain_url ? (
                        <Button variant="outline" asChild>
                            <a href={website.domain_url} target="_blank" rel="noreferrer">
                                <ExternalLinkIcon data-icon="inline-start" />
                                Visit site
                            </a>
                        </Button>
                    ) : null}
                    <Button variant="outline" asChild>
                        <Link href={route('platform.websites.edit', website.id)}>
                            <PencilIcon data-icon="inline-start" />
                            Edit website
                        </Link>
                    </Button>
                </div>
            }
        >
            <div className="flex flex-col gap-6">
                <Card>
                    <CardHeader>
                        <div className="flex items-center gap-2">
                            <GlobeIcon className="size-4 text-muted-foreground" />
                            <CardTitle>Website overview</CardTitle>
                        </div>
                        <CardDescription>Runtime status, domain routing, and infrastructure placement.</CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <MetricCard label="UID" value={website.uid} />
                        <MetricCard label="Domain" value={website.domain} />
                        <MetricCard label="Status" value={website.status_label} />
                        <MetricCard label="Type" value={website.type} />
                        <MetricCard label="Plan" value={website.plan} />
                        <MetricCard label="Server" value={website.server_name} />
                        <MetricCard label="Agency" value={website.agency_name} />
                        <MetricCard label="DNS mode" value={website.dns_mode} />
                        <MetricCard label="Astero version" value={website.astero_version} />
                        <MetricCard label="Admin slug" value={website.admin_slug} />
                        <MetricCard label="Media slug" value={website.media_slug} />
                        <MetricCard label="Pull zone" value={pullzoneId} />
                        <MetricCard label="Queue worker" value={website.queue_worker_status} />
                        <MetricCard label="Cron" value={website.cron_status} />
                        <MetricCard label="Created" value={website.created_at} />
                        <MetricCard label="Expires" value={website.expired_on} />
                    </CardContent>
                </Card>

                <div className="grid gap-6 xl:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <RefreshCwIcon className="size-4 text-muted-foreground" />
                                <CardTitle>Provisioning timeline</CardTitle>
                            </div>
                            <CardDescription>Current step state for initial provisioning and retries.</CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-3">
                            {provisioningSteps.length === 0 ? (
                                <p className="text-sm text-muted-foreground">No provisioning steps available.</p>
                            ) : (
                                provisioningSteps.map((step) => (
                                    <div key={step.key} className="rounded-lg border p-3">
                                        <div className="flex items-center justify-between gap-3">
                                            <p className="font-medium text-foreground">{step.title}</p>
                                            <span className="text-xs uppercase tracking-wide text-muted-foreground">
                                                {step.status}
                                            </span>
                                        </div>
                                        {step.description ? (
                                            <p className="mt-1 text-sm text-muted-foreground">{step.description}</p>
                                        ) : null}
                                        {step.message ? <p className="mt-2 text-xs text-muted-foreground">{step.message}</p> : null}
                                    </div>
                                ))
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <ShieldCheckIcon className="size-4 text-muted-foreground" />
                                <CardTitle>Operational flags</CardTitle>
                            </div>
                            <CardDescription>Provisioning shortcuts and routing-specific switches.</CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4 md:grid-cols-2">
                            <MetricCard label="Uses www" value={website.is_www ? 'Yes' : 'No'} />
                            <MetricCard label="Agency website" value={website.is_agency ? 'Yes' : 'No'} />
                            <MetricCard label="Skip CDN" value={website.skip_cdn ? 'Yes' : 'No'} />
                            <MetricCard label="Skip DNS" value={website.skip_dns ? 'Yes' : 'No'} />
                            <MetricCard label="Skip SSL" value={website.skip_ssl_issue ? 'Yes' : 'No'} />
                            <MetricCard label="Skip email" value={website.skip_email ? 'Yes' : 'No'} />
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
                            <CardDescription>Secret entries currently attached to the website record.</CardDescription>
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
                            <CardDescription>Latest lifecycle actions executed for this website.</CardDescription>
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

                <Card>
                    <CardHeader>
                        <CardTitle>Latest sync snapshot</CardTitle>
                        <CardDescription>Most recent normalized payload values collected from the website runtime.</CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {updates.length === 0 ? (
                            <p className="text-sm text-muted-foreground">No sync data available yet.</p>
                        ) : (
                            updates.map((update) => (
                                <MetricCard key={update.key} label={update.label} value={update.value} />
                            ))
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
