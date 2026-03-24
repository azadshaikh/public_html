import { Link } from '@inertiajs/react';
import { ExternalLinkIcon, GlobeIcon, PlusIcon } from 'lucide-react';
import { Datagrid } from '@/components/datagrid/datagrid';
import type { DatagridColumn } from '@/components/datagrid/datagrid';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { buildScaffoldDatagridState } from '@/lib/scaffold-datagrid';
import type { BreadcrumbItem } from '@/types';
import type { ScaffoldIndexPageProps } from '@/types/scaffold';

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

type AgencyWebsitesIndexPageProps = ScaffoldIndexPageProps<WebsiteRow> & {
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
    config,
    rows,
    statistics,
    filters,
    canCreateWebsite,
}: AgencyWebsitesIndexPageProps) {
    const { gridFilters, perPage, sorting } = buildScaffoldDatagridState(
        config,
        filters,
        statistics,
        {
            searchPlaceholder: 'Search websites...',
            perPageOptions: [10, 25, 50],
        },
    );

    const columns: DatagridColumn<WebsiteRow>[] = [
        {
            key: 'name',
            header: 'Website',
            sortable: true,
            cardLabel: 'Website',
            cell: (website) => (
                <div className="flex min-w-0 flex-col gap-1">
                    <Link
                        href={website.manage_url}
                        className="font-medium text-foreground hover:text-primary"
                    >
                        {website.name}
                    </Link>
                    <span className="truncate text-xs text-muted-foreground">
                        {website.domain}
                    </span>
                </div>
            ),
        },
        {
            key: 'plan',
            header: 'Plan',
            cardLabel: 'Plan',
            cell: (website) => website.plan ?? 'N/A',
        },
        {
            key: 'type_label',
            header: 'Type',
            cardLabel: 'Type',
            cell: (website) => <Badge variant="outline">{website.type_label}</Badge>,
        },
        {
            key: 'status_label',
            header: 'Status',
            sortable: true,
            sortKey: 'status',
            cardLabel: 'Status',
            cell: (website) => (
                <Badge variant={statusVariant(website.status)}>
                    {website.status_label}
                </Badge>
            ),
        },
        {
            key: 'created_at',
            header: 'Created',
            sortable: true,
            cardLabel: 'Created',
            cell: (website) => formatDate(website.created_at),
        },
        {
            key: 'domain_url',
            header: 'Visit',
            cell: (website) => (
                <a
                    href={website.domain_url}
                    target="_blank"
                    rel="noreferrer"
                    className="inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline"
                >
                    Visit
                    <ExternalLinkIcon className="size-3.5" />
                </a>
            ),
        },
    ];

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
            <Datagrid
                action={route('agency.websites.index')}
                rows={rows}
                columns={columns}
                scaffoldColumns={config.columns}
                filters={gridFilters}
                getRowKey={(website) => website.id}
                sorting={sorting}
                perPage={perPage}
                view={{
                    value: filters.view === 'table' ? 'table' : 'cards',
                    storageKey: 'agency-websites-datagrid-view',
                }}
                renderCardHeader={(website) => (
                    <div className="flex min-w-0 items-start justify-between gap-3">
                        <div className="min-w-0 space-y-1">
                            <Link
                                href={website.manage_url}
                                className="block truncate font-semibold text-foreground hover:text-primary"
                            >
                                {website.name}
                            </Link>
                            <p className="truncate text-sm text-muted-foreground">
                                {website.domain}
                            </p>
                        </div>
                        <Badge variant={statusVariant(website.status)}>
                            {website.status_label}
                        </Badge>
                    </div>
                )}
                renderCard={(website) => (
                    <div className="flex flex-col gap-4">
                        <div className="grid gap-3 sm:grid-cols-2">
                            <div className="space-y-1">
                                <p className="text-[0.68rem] font-semibold tracking-[0.18em] text-muted-foreground uppercase">
                                    Plan
                                </p>
                                <p className="text-sm font-medium text-foreground">
                                    {website.plan ?? 'N/A'}
                                </p>
                            </div>
                            <div className="space-y-1">
                                <p className="text-[0.68rem] font-semibold tracking-[0.18em] text-muted-foreground uppercase">
                                    Type
                                </p>
                                <div>
                                    <Badge variant="outline">{website.type_label}</Badge>
                                </div>
                            </div>
                            <div className="space-y-1">
                                <p className="text-[0.68rem] font-semibold tracking-[0.18em] text-muted-foreground uppercase">
                                    Created
                                </p>
                                <p className="text-sm text-foreground">
                                    {formatDate(website.created_at)}
                                </p>
                            </div>
                            <div className="space-y-1">
                                <p className="text-[0.68rem] font-semibold tracking-[0.18em] text-muted-foreground uppercase">
                                    Open Site
                                </p>
                                <a
                                    href={website.domain_url}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline"
                                >
                                    Visit
                                    <ExternalLinkIcon className="size-3.5" />
                                </a>
                            </div>
                        </div>

                        <div className="flex items-center justify-end border-t border-border/60 pt-4">
                            <Button asChild>
                                <Link href={website.manage_url}>Manage</Link>
                            </Button>
                        </div>
                    </div>
                )}
                cardGridClassName="grid-cols-1 xl:grid-cols-2"
                empty={{
                    icon: <GlobeIcon className="size-5" />,
                    title: 'No websites yet',
                    description:
                        'Start onboarding to provision your first site and manage it from this list.',
                }}
            />
        </AppLayout>
    );
}
