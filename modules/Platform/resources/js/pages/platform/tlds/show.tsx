import { Link } from '@inertiajs/react';
import { ActivityIcon, ExternalLinkIcon, PencilIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { PlatformActivity, TldShowData } from '../../../types/platform';

type TldsShowPageProps = {
    tld: TldShowData;
    activities: PlatformActivity[];
};

function MetricCard({
    label,
    value,
}: {
    label: string;
    value: string | number | null | undefined;
}) {
    return (
        <div className="rounded-lg border bg-muted/20 p-4">
            <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                {label}
            </p>
            <p className="mt-1 text-sm font-medium text-foreground">
                {value || '—'}
            </p>
        </div>
    );
}

export default function TldsShow({ tld, activities }: TldsShowPageProps) {
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
        { title: tld.tld, href: route('platform.tlds.show', tld.id) },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={tld.tld}
            description="Inspect pricing, merchandising, and registration metadata for this top-level domain."
            headerActions={
                <div className="flex flex-wrap items-center gap-3">
                    {tld.affiliate_link ? (
                        <Button variant="outline" asChild>
                            <a
                                href={tld.affiliate_link}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <ExternalLinkIcon data-icon="inline-start" />
                                Open affiliate link
                            </a>
                        </Button>
                    ) : null}
                    <Button variant="outline" asChild>
                        <Link href={route('platform.tlds.edit', tld.id)}>
                            <PencilIcon data-icon="inline-start" />
                            Edit TLD
                        </Link>
                    </Button>
                </div>
            }
        >
            <div className="flex flex-col gap-6">
                <Card>
                    <CardHeader>
                        <CardTitle>TLD overview</CardTitle>
                        <CardDescription>
                            Registration, pricing, and display settings stored
                            for this extension.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <MetricCard label="Extension" value={tld.tld} />
                        <MetricCard
                            label="WHOIS server"
                            value={tld.whois_server}
                        />
                        <MetricCard label="Pattern" value={tld.pattern} />
                        <MetricCard label="Status" value={tld.status_label} />
                        <MetricCard label="Price" value={tld.price} />
                        <MetricCard label="Sale price" value={tld.sale_price} />
                        <MetricCard
                            label="Primary"
                            value={tld.is_main ? 'Yes' : 'No'}
                        />
                        <MetricCard
                            label="Suggested"
                            value={tld.is_suggested ? 'Yes' : 'No'}
                        />
                        <MetricCard
                            label="Display order"
                            value={tld.tld_order}
                        />
                        <MetricCard
                            label="Affiliate link"
                            value={tld.affiliate_link}
                        />
                        <MetricCard label="Created" value={tld.created_at} />
                        <MetricCard label="Updated" value={tld.updated_at} />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <div className="flex items-center gap-2">
                            <ActivityIcon className="size-4 text-muted-foreground" />
                            <CardTitle>Recent activity</CardTitle>
                        </div>
                        <CardDescription>
                            Latest changes applied to this TLD configuration.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-3">
                        {activities.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No recent activity.
                            </p>
                        ) : (
                            activities.map((activity) => (
                                <div
                                    key={activity.id}
                                    className="rounded-lg border p-3"
                                >
                                    <p className="text-sm font-medium text-foreground">
                                        {activity.description}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {activity.causer_name
                                            ? `${activity.causer_name} · `
                                            : ''}
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
