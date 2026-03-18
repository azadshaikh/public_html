import { Link, usePage } from '@inertiajs/react';
import { KeyRoundIcon, PlusIcon } from 'lucide-react';
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
import type { PlatformIndexPageProps, SecretListItem } from '../../../types/platform';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    {
        title: 'Platform',
        href: route('platform.secrets.index', { status: 'all' }),
    },
    {
        title: 'Secrets',
        href: route('platform.secrets.index', { status: 'all' }),
    },
];

export default function SecretsIndex({
    config,
    rows,
    filters,
    statistics,
    empty_state_config,
}: PlatformIndexPageProps<SecretListItem>) {
    const page = usePage<AuthenticatedSharedData>();
    const canAddSecrets = page.props.auth.abilities.addSecrets;

    const { currentStatus, gridFilters, perPage, sorting, statusTabs } = buildScaffoldDatagridState(config, filters, statistics, {
        searchPlaceholder: 'Search secrets...',
        perPageOptions: [15, 25, 50, 100],
    });
    const { rowActions, bulkActions } = buildScaffoldActionHandlers(config, {
        bulkActionUrl: route('platform.secrets.bulk-action'),
        currentStatus,
    });

    const columns: DatagridColumn<SecretListItem>[] = [
        {
            key: 'key',
            header: 'Secret',
            sortable: true,
            cell: (secret) => (
                <div className="flex flex-col gap-1">
                    <Link
                        href={route('platform.secrets.show', secret.id)}
                        className="font-medium text-foreground hover:text-primary"
                    >
                        {secret.key}
                    </Link>
                    {secret.username ? (
                        <span className="text-xs text-muted-foreground">
                            {secret.username}
                        </span>
                    ) : null}
                </div>
            ),
        },
        {
            key: 'type_label',
            header: 'Type',
            sortable: true,
            sortKey: 'type',
            cell: (secret) => (
                <Badge variant="secondary">{secret.type_label}</Badge>
            ),
        },
        {
            key: 'is_active_label',
            header: 'Active',
            sortable: true,
            sortKey: 'is_active',
            cell: (secret) => (
                <Badge
                    variant={
                        secret.is_active_label === 'Active'
                            ? 'success'
                            : 'outline'
                    }
                >
                    {secret.is_active_label}
                </Badge>
            ),
        },
        {
            key: 'expires_at',
            header: 'Expires',
            sortable: true,
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
            title="Secrets"
            description="Store and manage encrypted credentials attached to domains, websites, agencies, servers, and providers."
            headerActions={
                canAddSecrets ? (
                    <Button asChild>
                        <Link href={route('platform.secrets.create')}>
                            <PlusIcon data-icon="inline-start" />
                            Add secret
                        </Link>
                    </Button>
                ) : undefined
            }
        >
            <Datagrid
                action={route('platform.secrets.index', {
                    status: currentStatus,
                })}
                rows={rows}
                columns={columns}
                scaffoldColumns={config.columns}
                filters={gridFilters}
                tabs={{ name: 'status', items: statusTabs }}
                getRowKey={(secret) => secret.id}
                rowActions={rowActions}
                bulkActions={bulkActions}
                empty={buildScaffoldEmptyState(empty_state_config, {
                    icon: <KeyRoundIcon className="size-5" />,
                    fallbackTitle: 'No secrets found',
                    fallbackDescription:
                        'Create encrypted secret records for platform credentials, tokens, and certificates.',
                })}
                sorting={sorting}
                perPage={perPage}
                title="Encrypted secret records"
                description="Filter secrets by entity type, secret kind, and current activation status."
            />
        </AppLayout>
    );
}
