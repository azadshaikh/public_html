import { Link, usePage } from '@inertiajs/react';
import { Globe2Icon, PlusIcon } from 'lucide-react';
import { Datagrid } from '@/components/datagrid/datagrid';
import type { DatagridColumn, DatagridTab } from '@/components/datagrid/datagrid';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { AuthenticatedSharedData, BreadcrumbItem } from '@/types';
import { buildBulkActions, mapFilters, mapRowActions, mapStatusTab } from '../../../lib/helpers';
import type { PlatformIndexPageProps, TldListItem } from '../../../types/platform';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Platform', href: route('platform.tlds.index', { status: 'all' }) },
    { title: 'TLDs', href: route('platform.tlds.index', { status: 'all' }) },
];

export default function TldsIndex({ config, rows, filters, statistics }: PlatformIndexPageProps<TldListItem>) {
    const page = usePage<AuthenticatedSharedData>();
    const canAddTlds = page.props.auth.abilities.addTlds;

    const gridFilters = mapFilters(config.filters, filters, 'Search TLDs...');
    const statusTabs: DatagridTab[] = config.statusTabs.map((tab) =>
        mapStatusTab(tab, statistics, String(filters.status ?? 'all')),
    );

    const columns: DatagridColumn<TldListItem>[] = [
        {
            key: 'tld',
            header: 'TLD',
            sortable: true,
            sortKey: 'tld_order',
            cell: (item) => (
                <Link href={route('platform.tlds.show', item.id)} className="font-medium text-foreground hover:text-primary">
                    {item.tld}
                </Link>
            ),
        },
        {
            key: 'whois_server',
            header: 'WHOIS server',
            sortable: true,
        },
        {
            key: 'price',
            header: 'Price',
            sortable: true,
        },
        {
            key: 'sale_price',
            header: 'Sale price',
            sortable: true,
        },
        {
            key: 'is_suggested_label',
            header: 'Suggested',
            cell: (item) => <Badge variant={item.is_suggested_label === 'Yes' ? 'success' : 'outline'}>{item.is_suggested_label}</Badge>,
        },
        {
            key: 'status_label',
            header: 'Status',
            sortable: true,
            sortKey: 'status',
            cell: (item) => <Badge variant={item.is_trashed ? 'destructive' : 'secondary'}>{item.status_label}</Badge>,
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
            title="TLDs"
            description="Maintain searchable TLD metadata, pricing, and merchandising flags."
            headerActions={
                canAddTlds ? (
                    <Button asChild>
                        <Link href={route('platform.tlds.create')}>
                            <PlusIcon data-icon="inline-start" />
                            Add TLD
                        </Link>
                    </Button>
                ) : undefined
            }
        >
            <Datagrid
                action={route('platform.tlds.index', { status: filters.status ?? 'all' })}
                rows={rows}
                columns={columns}
                filters={gridFilters}
                tabs={{ name: 'status', items: statusTabs }}
                getRowKey={(item) => item.id}
                rowActions={(item) => mapRowActions(item.actions)}
                bulkActions={buildBulkActions(config.actions, config.settings.routePrefix, String(filters.status ?? 'all'))}
                empty={{
                    icon: <Globe2Icon className="size-5" />,
                    title: 'No TLDs found',
                    description: 'Create TLD records to power domain search, pricing, and affiliate routing.',
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
                title="Managed TLDs"
                description="Review pricing, WHOIS coverage, and suggested extension flags across your catalog."
            />
        </AppLayout>
    );
}
