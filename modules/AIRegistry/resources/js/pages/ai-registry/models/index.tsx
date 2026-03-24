import { Link, usePage } from '@inertiajs/react';
import { PlusIcon, SparklesIcon } from 'lucide-react';
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
    AiModelListItem,
} from '../../../types/ai-registry';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'AI Registry', href: route('ai-registry.models.index', { status: 'all' }) },
    { title: 'Models', href: route('ai-registry.models.index', { status: 'all' }) },
];

export default function ModelsIndex({
    config,
    rows,
    filters,
    statistics,
    empty_state_config,
}: AIRegistryIndexPageProps<AiModelListItem>) {
    const page = usePage<AuthenticatedSharedData>();
    const canAddModels = Boolean(page.props.auth.abilities.addAiModels);
    const { currentStatus, gridFilters, perPage, sorting, statusTabs } = buildScaffoldDatagridState(
        config,
        filters,
        statistics,
        { searchPlaceholder: 'Search models...' },
    );
    const { rowActions, bulkActions } = buildScaffoldActionHandlers(config, {
        bulkActionUrl: route('ai-registry.models.bulk-action'),
        currentStatus,
    });

    const columns: DatagridColumn<AiModelListItem>[] = [
        {
            key: 'name',
            header: 'Model',
            sortable: true,
            cell: (model) => (
                <div className="flex flex-col gap-1">
                    <Link href={model.edit_url} className="font-medium text-foreground hover:text-primary">
                        {model.name}
                    </Link>
                    <span className="text-xs text-muted-foreground">{model.slug}</span>
                </div>
            ),
        },
        {
            key: 'provider_name',
            header: 'Provider',
            cell: (model) => <Badge variant="outline">{model.provider_name}</Badge>,
        },
        {
            key: 'context_window_formatted',
            header: 'Context',
            sortable: true,
            sortKey: 'context_window',
        },
        {
            key: 'input_cost_per_1m',
            header: 'Input $ / 1M',
            sortable: true,
        },
        {
            key: 'output_cost_per_1m',
            header: 'Output $ / 1M',
            sortable: true,
        },
        {
            key: 'is_active_label',
            header: 'Status',
            sortable: true,
            sortKey: 'is_active',
            cell: (model) => (
                <Badge variant={model.is_active ? 'success' : 'secondary'}>
                    {model.is_active_label}
                </Badge>
            ),
        },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="AI Models"
            description="Manage normalized model metadata, pricing, modality support, and provider assignment."
            headerActions={
                canAddModels ? (
                    <Button asChild>
                        <Link href={route('ai-registry.models.create')}>
                            <PlusIcon data-icon="inline-start" />
                            Add model
                        </Link>
                    </Button>
                ) : undefined
            }
        >
            <Datagrid
                action={route('ai-registry.models.index', { status: currentStatus })}
                rows={rows}
                columns={columns}
                scaffoldColumns={config.columns}
                filters={gridFilters}
                tabs={{ name: 'status', items: statusTabs }}
                getRowKey={(model) => model.id}
                rowActions={rowActions}
                bulkActions={bulkActions}
                empty={buildScaffoldEmptyState(empty_state_config, {
                    icon: <SparklesIcon className="size-5" />,
                    fallbackTitle: 'No AI models found',
                    fallbackDescription:
                        'Create model records so provider catalogs, pricing metadata, and capability filters have a working source of truth.',
                })}
                sorting={sorting}
                perPage={perPage}
            />
        </AppLayout>
    );
}
