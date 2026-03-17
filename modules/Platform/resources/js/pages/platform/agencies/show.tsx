import { Link, router } from '@inertiajs/react';
import {
    ActivityIcon,
    Building2Icon,
    GlobeIcon,
    PencilIcon,
    RefreshCwIcon,
    ServerIcon,
    ShieldIcon,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type {
    AgencyRelationItem,
    AgencyShowData,
    PlatformActivity,
} from '../../../types/platform';

type AgenciesShowPageProps = {
    agency: AgencyShowData;
    websites: AgencyRelationItem[];
    servers: AgencyRelationItem[];
    dnsProviders: AgencyRelationItem[];
    cdnProviders: AgencyRelationItem[];
    activities: PlatformActivity[];
};

function OverviewItem({
    label,
    value,
}: {
    label: string;
    value: string | number | null | undefined;
}) {
    return (
        <div className="flex flex-col gap-1 rounded-lg border bg-muted/20 p-4">
            <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                {label}
            </span>
            <span className="text-sm font-medium text-foreground">
                {value || '—'}
            </span>
        </div>
    );
}

function RelationList({
    title,
    description,
    icon: Icon,
    items,
}: {
    title: string;
    description: string;
    icon: typeof GlobeIcon;
    items: AgencyRelationItem[];
}) {
    return (
        <Card>
            <CardHeader>
                <div className="flex items-center gap-2">
                    <Icon className="size-4 text-muted-foreground" />
                    <CardTitle>{title}</CardTitle>
                </div>
                <CardDescription>{description}</CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-3">
                {items.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No records yet.
                    </p>
                ) : (
                    items.map((item) => (
                        <div
                            key={item.id}
                            className="flex items-center justify-between gap-3 rounded-lg border p-3"
                        >
                            <div className="min-w-0">
                                {item.href ? (
                                    <Link
                                        href={item.href}
                                        className="font-medium text-foreground hover:text-primary"
                                    >
                                        {item.name}
                                    </Link>
                                ) : (
                                    <p className="font-medium text-foreground">
                                        {item.name}
                                    </p>
                                )}
                                {item.subtitle ? (
                                    <p className="truncate text-sm text-muted-foreground">
                                        {item.subtitle}
                                    </p>
                                ) : null}
                            </div>
                            {item.status ? (
                                <span className="text-xs text-muted-foreground">
                                    {item.status}
                                </span>
                            ) : null}
                        </div>
                    ))
                )}
            </CardContent>
        </Card>
    );
}

export default function AgenciesShow({
    agency,
    websites,
    servers,
    dnsProviders,
    cdnProviders,
    activities,
}: AgenciesShowPageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        {
            title: 'Platform',
            href: route('platform.agencies.index', { status: 'all' }),
        },
        {
            title: 'Agencies',
            href: route('platform.agencies.index', { status: 'all' }),
        },
        {
            title: agency.name,
            href: route('platform.agencies.show', agency.id),
        },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={agency.name}
            description="Review agency routing defaults, branding, infrastructure, and recent activity."
            headerActions={
                <div className="flex flex-wrap items-center gap-3">
                    <Button variant="outline" asChild>
                        <Link href={route('platform.agencies.edit', agency.id)}>
                            <PencilIcon data-icon="inline-start" />
                            Edit agency
                        </Link>
                    </Button>
                    <Button
                        variant="outline"
                        onClick={() =>
                            router.post(
                                route(
                                    'platform.agencies.regenerate-secret-key',
                                    agency.id,
                                ),
                                undefined,
                                {
                                    preserveScroll: true,
                                },
                            )
                        }
                    >
                        <RefreshCwIcon data-icon="inline-start" />
                        Regenerate secret key
                    </Button>
                </div>
            }
        >
            <div className="flex flex-col gap-6">
                <Card>
                    <CardHeader>
                        <div className="flex items-center gap-2">
                            <Building2Icon className="size-4 text-muted-foreground" />
                            <CardTitle>Agency overview</CardTitle>
                        </div>
                        <CardDescription>
                            Core profile, ownership, and routing defaults used
                            for new websites.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <OverviewItem label="UID" value={agency.uid} />
                        <OverviewItem label="Owner" value={agency.owner_name} />
                        <OverviewItem label="Type" value={agency.type} />
                        <OverviewItem label="Plan" value={agency.plan} />
                        <OverviewItem label="Status" value={agency.status} />
                        <OverviewItem
                            label="Website prefix"
                            value={agency.website_id_prefix}
                        />
                        <OverviewItem
                            label="Zero padding"
                            value={agency.website_id_zero_padding}
                        />
                        <OverviewItem
                            label="Webhook URL"
                            value={agency.webhook_url}
                        />
                    </CardContent>
                </Card>

                <div className="grid gap-6 xl:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Branding and contact</CardTitle>
                            <CardDescription>
                                Metadata delivered to agency-managed platform
                                websites.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-4 text-sm">
                            <div>
                                <p className="font-medium text-foreground">
                                    Primary email
                                </p>
                                <p className="text-muted-foreground">
                                    {agency.email || '—'}
                                </p>
                            </div>
                            <Separator />
                            <div>
                                <p className="font-medium text-foreground">
                                    Brand name
                                </p>
                                <p className="text-muted-foreground">
                                    {agency.branding.name || '—'}
                                </p>
                            </div>
                            <div>
                                <p className="font-medium text-foreground">
                                    Brand website
                                </p>
                                <p className="text-muted-foreground">
                                    {agency.branding.website || '—'}
                                </p>
                            </div>
                            <div>
                                <p className="font-medium text-foreground">
                                    Phone
                                </p>
                                <p className="text-muted-foreground">
                                    {[
                                        agency.address.phone_code,
                                        agency.address.phone,
                                    ]
                                        .filter(Boolean)
                                        .join(' ') || '—'}
                                </p>
                            </div>
                            <div>
                                <p className="font-medium text-foreground">
                                    Address
                                </p>
                                <p className="text-muted-foreground">
                                    {[
                                        agency.address.address1,
                                        agency.address.city,
                                        agency.address.state_code,
                                        agency.address.country_code,
                                        agency.address.zip,
                                    ]
                                        .filter(Boolean)
                                        .join(', ') || '—'}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <ActivityIcon className="size-4 text-muted-foreground" />
                                <CardTitle>Recent activity</CardTitle>
                            </div>
                            <CardDescription>
                                Latest operational events tied to this agency.
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
                                            {activity.created_at ||
                                                'Unknown time'}
                                        </p>
                                    </div>
                                ))
                            )}
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-6 xl:grid-cols-2">
                    <RelationList
                        title="Websites"
                        description="Agency-owned sites, including agency platform websites."
                        icon={GlobeIcon}
                        items={websites}
                    />
                    <RelationList
                        title="Servers"
                        description="Infrastructure currently attached to this agency."
                        icon={ServerIcon}
                        items={servers}
                    />
                </div>

                <div className="grid gap-6 xl:grid-cols-2">
                    <RelationList
                        title="DNS providers"
                        description="Providers available for managed DNS automation."
                        icon={ShieldIcon}
                        items={dnsProviders}
                    />
                    <RelationList
                        title="CDN providers"
                        description="Providers available for edge caching and delivery."
                        icon={ShieldIcon}
                        items={cdnProviders}
                    />
                </div>
            </div>
        </AppLayout>
    );
}
