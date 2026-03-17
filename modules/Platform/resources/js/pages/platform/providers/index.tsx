import { Link, usePage } from '@inertiajs/react';
import { PlusIcon, ServerCogIcon } from 'lucide-react';
import { Datagrid } from '@/components/datagrid/datagrid';
import type { DatagridColumn, DatagridTab } from '@/components/datagrid/datagrid';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { AuthenticatedSharedData, BreadcrumbItem } from '@/types';
import { buildBulkActions, mapFilters, mapRowActions, mapStatusTab } from '../../../lib/helpers';
import type { PlatformIndexPageProps, ProviderListItem } from '../../../types/platform';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Platform', href: route('platform.providers.index', { status: 'all' }) },
    { title: 'Providers', href: route('platform.providers.index', { status: 'all' }) },
];

export default function ProvidersIndex({ config, rows, filters, statistics }: PlatformIndexPageProps<ProviderListItem>) {
    const page = usePage<AuthenticatedSharedData>();
    const canAddProviders = page.props.auth.abilities.addProviders;

    const gridFilters = mapFilters(config.filters, filters, 'Search providers...');
    const statusTabs: DatagridTab[] = config.statusTabs.map((tab) =>
        mapStatusTab(tab, statistics, String(filters.status ?? 'all')),
    );

    const columns: DatagridColumn<ProviderListItem>[] = [
        {
            key: 'name',
            header: 'Provider',
            sortable: true,
            cell: (provider) => (
                <div className="flex flex-col gap-1">
                    <Link href={route('platform.providers.show', provider.id)} className="font-medium text-foreground hover:text-primary">
                        {provider.name}
                    </Link>
                    {provider.email ? <span className="text-xs text-muted-foreground">{provider.email}</span> : null}
                </div>
            ),
        },
        {
            key: 'type_label',
            header: 'Type',
            sortable: true,
            sortKey: 'type',
            cell: (provider) => <Badge variant="secondary">{provider.type_label}</Badge>,
        },
        {
            key: 'vendor_label',
            header: 'Vendor',
            sortable: true,
            sortKey: 'vendor',
            cell: (provider) => <Badge variant="outline">{provider.vendor_label}</Badge>,
        },
        {
            key: 'email',
            header: 'Email',
        },
        {
            key: 'status_label',
            header: 'Status',
            sortable: true,
            sortKey: 'status',
            cell: (provider) => <Badge variant={provider.is_trashed ? 'destructive' : 'secondary'}>{provider.status_label}</Badge>,
        },
        {
            key: 'created_at',
            header: 'Created',
            sortable: true,
        },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Providers"
            description="Track DNS, CDN, registrar, and infrastructure providers used across the platform."
            headerActions={
                canAddProviders ? (
                    <Button asChild>
                        <Link href={route('platform.providers.create')}>
                            <PlusIcon data-icon="inline-start" />
                            Add provider
                        </Link>
                    </Button>
                ) : undefined
            }
        >
            <Datagrid
                action={route('platform.providers.index', { status: filters.status ?? 'all' })}
                rows={rows}
                columns={columns}
                filters={gridFilters}
                tabs={{ name: 'status', items: statusTabs }}
                getRowKey={(provider) => provider.id}
                rowActions={(provider) => mapRowActions(provider.actions)}
                bulkActions={buildBulkActions(config.actions, config.settings.routePrefix, String(filters.status ?? 'all'))}
                empty={{
                    icon: <ServerCogIcon className="size-5" />,
                    title: 'No providers found',
                    description: 'Create provider accounts for DNS, CDN, registrar, or cloud infrastructure integrations.',
                }}
                sorting={{
                    sort: String(filters.sort ?? config.settings.defaultSort ?? 'created_at'),
                    direction:
                        String(filters.direction ?? config.settings.defaultDirection ?? 'desc') === 'asc'
                            ? 'asc'
                            : 'desc',
                }}
                perPage={{
                    value: Number(filters.per_page ?? config.settings.perPage ?? rows.per_page),
                    options: [15, 25, 50, 100],
                    paramName: 'per_page',
                }}
                title="Connected providers"
                description="Monitor upstream account coverage, vendor mix, and account status for every integration."
            />
        </AppLayout>
    );
}
