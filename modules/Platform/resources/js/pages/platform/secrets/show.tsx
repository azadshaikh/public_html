import { Link } from '@inertiajs/react';
import { ActivityIcon, ExternalLinkIcon, KeyRoundIcon, PencilIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { PlatformActivity, SecretShowData } from '../../../types/platform';

type SecretsShowPageProps = {
    secret: SecretShowData;
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

export default function SecretsShow({ secret, activities }: SecretsShowPageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Platform', href: route('platform.secrets.index', { status: 'all' }) },
        { title: 'Secrets', href: route('platform.secrets.index', { status: 'all' }) },
        { title: secret.key, href: route('platform.secrets.show', secret.id) },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={secret.key}
            description="Inspect the secret assignment, metadata, and expiration state for this encrypted record."
            headerActions={
                <div className="flex flex-wrap items-center gap-3">
                    {secret.secretable_href ? (
                        <Button variant="outline" asChild>
                            <Link href={secret.secretable_href}>
                                <ExternalLinkIcon data-icon="inline-start" />
                                Open entity
                            </Link>
                        </Button>
                    ) : null}
                    <Button variant="outline" asChild>
                        <Link href={route('platform.secrets.edit', secret.id)}>
                            <PencilIcon data-icon="inline-start" />
                            Edit secret
                        </Link>
                    </Button>
                </div>
            }
        >
            <div className="flex flex-col gap-6">
                <Card>
                    <CardHeader>
                        <div className="flex items-center gap-2">
                            <KeyRoundIcon className="size-4 text-muted-foreground" />
                            <CardTitle>Secret overview</CardTitle>
                        </div>
                        <CardDescription>Entity assignment, secret type, and activation details.</CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <MetricCard label="Key" value={secret.key} />
                        <MetricCard label="Username" value={secret.username} />
                        <MetricCard label="Type" value={secret.type_label} />
                        <MetricCard label="Entity type" value={secret.secretable_type_label} />
                        <MetricCard label="Entity" value={secret.secretable_name} />
                        <MetricCard label="Entity ID" value={secret.secretable_id} />
                        <MetricCard label="Status" value={secret.is_active_label} />
                        <MetricCard label="Expired" value={secret.is_expired ? 'Yes' : 'No'} />
                        <MetricCard label="Expires at" value={secret.expires_at} />
                        <MetricCard label="Created" value={secret.created_at} />
                        <MetricCard label="Updated" value={secret.updated_at} />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Metadata</CardTitle>
                        <CardDescription>Normalized key-value metadata stored alongside the encrypted payload.</CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {Object.keys(secret.metadata).length === 0 ? (
                            <p className="text-sm text-muted-foreground">No metadata recorded.</p>
                        ) : (
                            Object.entries(secret.metadata).map(([key, value]) => (
                                <MetricCard
                                    key={key}
                                    label={key.replace(/_/g, ' ')}
                                    value={typeof value === 'object' ? JSON.stringify(value) : String(value)}
                                />
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
                        <CardDescription>Latest lifecycle events for this secret record.</CardDescription>
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
