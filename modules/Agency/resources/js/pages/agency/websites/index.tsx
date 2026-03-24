import { Link } from '@inertiajs/react';
import { GlobeIcon, PlusIcon } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { NativeSelect, NativeSelectOption } from '@/components/ui/native-select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type WebsiteRow = {
    id: number;
    name: string;
    domain: string;
    domain_url: string;
    status: string;
    status_label: string;
    status_badge: string;
    plan: string | null;
    type_label: string;
    manage_url: string;
    created_at: string | null;
};

type PaginationData = {
    current_page: number;
    last_page: number;
    total: number;
};

type AgencyWebsitesIndexPageProps = {
    websites: WebsiteRow[];
    pagination: PaginationData;
    statistics: Record<string, number>;
    filters: {
        search: string;
        status: string;
    };
    canCreateWebsite: boolean;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Websites', href: route('agency.websites.index') },
];

function formatDate(value: string | null): string {
    if (!value) {
        return 'N/A';
    }

    return new Intl.DateTimeFormat('en', {
        month: 'short',
        day: '2-digit',
        year: 'numeric',
    }).format(new Date(value));
}

function statusVariant(status: string): 'success' | 'warning' | 'danger' | 'secondary' {
    switch (status) {
        case 'active':
            return 'success';
        case 'waiting_for_dns':
        case 'provisioning':
            return 'warning';
        case 'failed':
        case 'expired':
            return 'danger';
        default:
            return 'secondary';
    }
}

export default function AgencyWebsitesIndex({
    websites,
    pagination,
    statistics,
    filters,
    canCreateWebsite,
}: AgencyWebsitesIndexPageProps) {
    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Sites"
            description="Review website status, plans, and provisioning progress for your account."
            headerActions={
                canCreateWebsite ? (
                    <Button asChild>
                        <Link href={route('agency.websites.create')}>
                            <PlusIcon data-icon="inline-start" />
                            Create New Site
                        </Link>
                    </Button>
                ) : null
            }
        >
            <div className="space-y-6">
                <div className="grid gap-4 md:grid-cols-4">
                    {[
                        ['Total', statistics.all ?? pagination.total],
                        ['Active', statistics.active ?? 0],
                        ['Provisioning', statistics.provisioning ?? 0],
                        ['Failed', statistics.failed ?? 0],
                    ].map(([label, value]) => (
                        <Card key={label}>
                            <CardHeader className="pb-2">
                                <CardDescription>{label}</CardDescription>
                                <CardTitle className="text-3xl">{value}</CardTitle>
                            </CardHeader>
                        </Card>
                    ))}
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Your Websites</CardTitle>
                        <CardDescription>
                            Search by website name or domain and jump straight to the manage view.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        <form
                            method="get"
                            action={route('agency.websites.index')}
                            className="grid gap-3 md:grid-cols-[minmax(0,1fr)_180px_auto]"
                        >
                            <Input
                                name="search"
                                defaultValue={filters.search}
                                placeholder="Search websites..."
                            />
                            <NativeSelect name="status" defaultValue={filters.status}>
                                <NativeSelectOption value="all">All statuses</NativeSelectOption>
                                <NativeSelectOption value="active">Active</NativeSelectOption>
                                <NativeSelectOption value="provisioning">Provisioning</NativeSelectOption>
                                <NativeSelectOption value="waiting_for_dns">Waiting for DNS</NativeSelectOption>
                                <NativeSelectOption value="failed">Failed</NativeSelectOption>
                            </NativeSelect>
                            <Button type="submit" variant="outline">
                                Apply
                            </Button>
                        </form>

                        {websites.length === 0 ? (
                            <div className="flex flex-col items-center justify-center rounded-xl border border-dashed py-12 text-center">
                                <GlobeIcon className="mb-3 size-10 text-muted-foreground" />
                                <p className="font-medium">No websites yet</p>
                                <p className="text-sm text-muted-foreground">
                                    Start onboarding to provision your first site.
                                </p>
                            </div>
                        ) : (
                            <div className="grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
                                {websites.map((website) => (
                                    <Card key={website.id} className="h-full">
                                        <CardHeader>
                                            <div className="flex items-start justify-between gap-3">
                                                <div className="space-y-1">
                                                    <CardTitle className="text-lg">
                                                        <Link
                                                            href={website.manage_url}
                                                            className="hover:text-primary"
                                                        >
                                                            {website.name}
                                                        </Link>
                                                    </CardTitle>
                                                    <CardDescription>
                                                        {website.domain}
                                                    </CardDescription>
                                                </div>
                                                <Badge variant={statusVariant(website.status)}>
                                                    {website.status_label}
                                                </Badge>
                                            </div>
                                        </CardHeader>
                                        <CardContent className="space-y-4">
                                            <div className="grid gap-3 text-sm">
                                                <div className="flex items-center justify-between">
                                                    <span className="text-muted-foreground">Plan</span>
                                                    <span>{website.plan ?? 'N/A'}</span>
                                                </div>
                                                <div className="flex items-center justify-between">
                                                    <span className="text-muted-foreground">Type</span>
                                                    <span>{website.type_label}</span>
                                                </div>
                                                <div className="flex items-center justify-between">
                                                    <span className="text-muted-foreground">Created</span>
                                                    <span>{formatDate(website.created_at)}</span>
                                                </div>
                                            </div>
                                            <div className="flex gap-2">
                                                <Button asChild className="flex-1">
                                                    <Link href={website.manage_url}>Manage</Link>
                                                </Button>
                                                <Button asChild variant="outline" className="flex-1">
                                                    <a href={website.domain_url} target="_blank" rel="noreferrer">
                                                        Visit
                                                    </a>
                                                </Button>
                                            </div>
                                        </CardContent>
                                    </Card>
                                ))}
                            </div>
                        )}

                        <div className="flex items-center justify-between text-sm text-muted-foreground">
                            <span>
                                Page {pagination.current_page} of {pagination.last_page}
                            </span>
                            <span>{pagination.total} total websites</span>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
