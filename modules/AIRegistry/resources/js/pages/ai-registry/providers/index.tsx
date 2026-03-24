import { Link, usePage } from '@inertiajs/react';
import { BotIcon, PlusIcon } from 'lucide-react';
import { Datagrid } from '@/components/datagrid/datagrid';
import type { DatagridColumn } from '@/components/datagrid/datagrid';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import {
    buildScaffoldActionHandlers,
    buildScaffoldDatagridState,
    buildScaffoldEmptyState,
} from '@/lib/scaffold-datagrid';
import type { AuthenticatedSharedData, BreadcrumbItem } from '@/types';
import type {
    AIRegistryIndexPageProps,
    AiProviderListItem,
} from '../../../types/ai-registry';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'AI Registry', href: route('ai-registry.providers.index', { status: 'all' }) },
    { title: 'Providers', href: route('ai-registry.providers.index', { status: 'all' }) },
];

export default function ProvidersIndex({
    config,
    rows,
    filters,
    statistics,
    empty_state_config,
}: AIRegistryIndexPageProps<AiProviderListItem>) {
    const page = usePage<AuthenticatedSharedData>();
    const canAddProviders = Boolean(page.props.auth.abilities.addAiProviders);
    const { currentStatus, gridFilters, perPage, sorting, statusTabs } = buildScaffoldDatagridState(
        config,
        filters,
        statistics,
        { searchPlaceholder: 'Search providers...' },
    );
    const { rowActions, bulkActions } = buildScaffoldActionHandlers(config, {
        bulkActionUrl: route('ai-registry.providers.bulk-action'),
        currentStatus,
    });

    const columns: DatagridColumn<AiProviderListItem>[] = [
        {
            key: 'name',
            header: 'Provider',
            sortable: true,
            cell: (provider) => (
                <div className="flex flex-col gap-1">
                    <Link href={provider.edit_url} className="font-medium text-foreground hover:text-primary">
                        {provider.name}
                    </Link>
                    <span className="text-xs text-muted-foreground">{provider.slug}</span>
                </div>
            ),
        },
        {
            key: 'capabilities',
            header: 'Capabilities',
            cell: (provider) => (
                <div className="flex flex-wrap gap-1.5">
                    {provider.capabilities.length > 0 ? (
                        provider.capabilities.slice(0, 3).map((capability) => (
                            <Badge key={capability.value} variant="secondary">
                                {capability.label}
                            </Badge>
                        ))
                    ) : (
                        <span className="text-sm text-muted-foreground">None</span>
                    )}
                    {provider.capabilities.length > 3 ? (
                        <Badge variant="outline">+{provider.capabilities.length - 3}</Badge>
                    ) : null}
                </div>
            ),
        },
        {
            key: 'models_count',
            header: 'Models',
            sortable: true,
        },
        {
            key: 'is_active_label',
            header: 'Status',
            sortable: true,
            sortKey: 'is_active',
            cell: (provider) => (
                <Badge variant={provider.is_active ? 'success' : 'secondary'}>
                    {provider.is_active_label}
                </Badge>
            ),
        },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="AI Providers"
            description="Manage the upstream AI vendors and the capabilities they expose to the registry."
            headerActions={
                canAddProviders ? (
                    <Button asChild>
                        <Link href={route('ai-registry.providers.create')}>
                            <PlusIcon data-icon="inline-start" />
                            Add provider
                        </Link>
                    </Button>
                ) : undefined
            }
        >
            <Datagrid
                action={route('ai-registry.providers.index', { status: currentStatus })}
                rows={rows}
                columns={columns}
                scaffoldColumns={config.columns}
                filters={gridFilters}
                tabs={{ name: 'status', items: statusTabs }}
                getRowKey={(provider) => provider.id}
                rowActions={rowActions}
                bulkActions={bulkActions}
                empty={buildScaffoldEmptyState(empty_state_config, {
                    icon: <BotIcon className="size-5" />,
                    fallbackTitle: 'No AI providers found',
                    fallbackDescription:
                        'Add provider records so model sync and manual registry entries have a vendor source of truth.',
                })}
                sorting={sorting}
                perPage={perPage}
            />
        </AppLayout>
    );
}
