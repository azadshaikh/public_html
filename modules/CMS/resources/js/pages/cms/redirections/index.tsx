import { Link, router, usePage } from '@inertiajs/react';
import {
    ArrowRightLeftIcon,
    DownloadIcon,
    PencilIcon,
    PlusIcon,
    RefreshCwIcon,
    Trash2Icon,
    UploadIcon,
} from 'lucide-react';
import { Datagrid } from '@/components/datagrid/datagrid';
import type {
    DatagridAction,
    DatagridBulkAction,
    DatagridColumn,
    DatagridFilter,
    DatagridTab,
} from '@/components/datagrid/datagrid';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { AuthenticatedSharedData, BreadcrumbItem } from '@/types';
import { mapStatusTab } from '../../../lib/helpers';
import type {
    RedirectionIndexPageProps,
    RedirectionListItem,
} from '../../types/cms';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Redirections', href: route('cms.redirections.index') },
];

export default function RedirectionsIndex({
    config,
    rows,
    filters,
    statistics,
}: RedirectionIndexPageProps) {
    const page = usePage<AuthenticatedSharedData>();
    const canAddRedirections = page.props.auth.abilities.addRedirections;
    const canEditRedirections = page.props.auth.abilities.editRedirections;
    const canDeleteRedirections = page.props.auth.abilities.deleteRedirections;
    const canRestoreRedirections =
        page.props.auth.abilities.restoreRedirections;

    const handleBulkAction = (
        action: string,
        selected: RedirectionListItem[],
        clearSelection: () => void,
    ) => {
        if (selected.length === 0) return;
        router.post(
            route('cms.redirections.bulk-action'),
            { action, ids: selected.map((r) => r.id), status: filters.status },
            { preserveScroll: true, onSuccess: () => clearSelection() },
        );
    };

    const gridFilters: DatagridFilter[] = [
        {
            type: 'search',
            name: 'search',
            value: filters.search,
            placeholder: 'Search redirections...',
            className: 'lg:min-w-80',
        },
    ];

    const statusTabs: DatagridTab[] = config.statusTabs.map((tab) =>
        mapStatusTab(tab, statistics, filters.status),
    );

    const columns: DatagridColumn<RedirectionListItem>[] = [
        {
            key: 'source_url',
            header: 'From',
            sortable: true,
            cell: (r) => (
                <span
                    className="max-w-60 truncate font-mono text-sm"
                    title={r.source_url}
                >
                    {r.source_url}
                </span>
            ),
        },
        {
            key: 'target_url',
            header: 'To',
            sortable: true,
            cell: (r) => (
                <span
                    className="max-w-60 truncate font-mono text-sm text-muted-foreground"
                    title={r.target_url}
                >
                    {r.target_url}
                </span>
            ),
        },
        {
            key: 'redirect_type_label',
            header: 'HTTP',
            headerClassName: 'w-28 text-center',
            cellClassName: 'w-28 text-center',
            type: 'badge',
            sortable: true,
            sortKey: 'redirect_type',
        },
        {
            key: 'url_type_label',
            header: 'Target',
            headerClassName: 'w-28 text-center',
            cellClassName: 'w-28 text-center',
            type: 'badge',
            sortable: true,
            sortKey: 'url_type',
        },
        {
            key: 'match_type_label',
            header: 'Match',
            headerClassName: 'w-28 text-center',
            cellClassName: 'w-28 text-center',
            type: 'badge',
            sortable: true,
            sortKey: 'match_type',
        },
        {
            key: 'hits',
            header: 'Hits',
            headerClassName: 'w-20 text-right',
            cellClassName:
                'w-20 text-right tabular-nums text-sm text-muted-foreground',
            sortable: true,
        },
        {
            key: 'status_label',
            header: 'Status',
            headerClassName: 'w-28 text-center',
            cellClassName: 'w-28 text-center',
            type: 'badge',
            sortable: true,
            sortKey: 'status',
        },
        {
            key: 'created_at',
            header: 'Created',
            headerClassName: 'w-32',
            cellClassName: 'w-32 text-sm text-muted-foreground',
            sortable: true,
        },
    ];

    const rowActions = (r: RedirectionListItem): DatagridAction[] => {
        if (r.is_trashed) {
            return [
                ...(canRestoreRedirections
                    ? [
                          {
                              label: 'Restore',
                              icon: <RefreshCwIcon />,
                              href: route('cms.redirections.restore', r.id),
                              method: 'PATCH' as const,
                              confirm: 'Restore this redirection?',
                          },
                      ]
                    : []),
                ...(canDeleteRedirections
                    ? [
                          {
                              label: 'Delete Permanently',
                              icon: <Trash2Icon />,
                              href: route(
                                  'cms.redirections.force-delete',
                                  r.id,
                              ),
                              method: 'DELETE' as const,
                              confirm:
                                  '⚠️ Permanently delete this redirection?',
                              variant: 'destructive' as const,
                          },
                      ]
                    : []),
            ];
        }
        return [
            ...(canEditRedirections
                ? [
                      {
                          label: 'Edit',
                          href:
                              r.show_url ??
                              route('cms.redirections.edit', r.id),
                          icon: <PencilIcon />,
                      },
                  ]
                : []),
            ...(canDeleteRedirections
                ? [
                      {
                          label: 'Move to Trash',
                          href: route('cms.redirections.destroy', r.id),
                          method: 'DELETE' as const,
                          confirm: 'Move this redirection to trash?',
                          icon: <Trash2Icon />,
                          variant: 'destructive' as const,
                      },
                  ]
                : []),
        ];
    };

    const bulkActions: DatagridBulkAction<RedirectionListItem>[] = [
        ...(canDeleteRedirections
            ? [
                  {
                      key: 'bulk-delete',
                      label: 'Move to Trash',
                      icon: <Trash2Icon />,
                      variant: 'destructive' as const,
                      confirm: 'Move selected redirections to trash?',
                      onSelect: (
                          items: RedirectionListItem[],
                          clear: () => void,
                      ) => handleBulkAction('delete', items, clear),
                  },
              ]
            : []),
        ...(canRestoreRedirections
            ? [
                  {
                      key: 'bulk-restore',
                      label: 'Restore',
                      icon: <RefreshCwIcon />,
                      confirm: 'Restore selected redirections?',
                      onSelect: (
                          items: RedirectionListItem[],
                          clear: () => void,
                      ) => handleBulkAction('restore', items, clear),
                  },
              ]
            : []),
        ...(canDeleteRedirections
            ? [
                  {
                      key: 'bulk-force-delete',
                      label: 'Delete Permanently',
                      icon: <Trash2Icon />,
                      variant: 'destructive' as const,
                      confirm: '⚠️ Permanently delete selected redirections?',
                      onSelect: (
                          items: RedirectionListItem[],
                          clear: () => void,
                      ) => handleBulkAction('force_delete', items, clear),
                  },
              ]
            : []),
    ];

    const visibleBulkActions =
        filters.status === 'trash'
            ? bulkActions.filter((a) => a.key !== 'bulk-delete')
            : bulkActions.filter((a) => a.key === 'bulk-delete');

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Redirections"
            description="Manage URL redirections"
            headerActions={
                <div className="flex gap-2">
                    <Button variant="outline" asChild>
                        <a
                            href={route('cms.redirections.export', {
                                status: filters.status,
                            })}
                        >
                            <DownloadIcon data-icon="inline-start" />
                            Export
                        </a>
                    </Button>
                    <Button variant="outline" asChild>
                        <Link href={route('cms.redirections.import.form')}>
                            <UploadIcon data-icon="inline-start" />
                            Import
                        </Link>
                    </Button>
                    {canAddRedirections && (
                        <Button asChild>
                            <Link href={route('cms.redirections.create')}>
                                <PlusIcon data-icon="inline-start" />
                                Add Redirection
                            </Link>
                        </Button>
                    )}
                </div>
            }
        >
            <div className="flex flex-col gap-6">
                <Datagrid
                    action={route('cms.redirections.index')}
                    rows={rows}
                    columns={columns}
                    filters={gridFilters}
                    tabs={{ name: 'status', items: statusTabs }}
                    getRowKey={(r) => r.id}
                    rowActions={rowActions}
                    bulkActions={visibleBulkActions}
                    isRowSelectable={() => visibleBulkActions.length > 0}
                    sorting={{
                        sort: filters.sort,
                        direction: filters.direction,
                    }}
                    perPage={{
                        value: filters.per_page,
                        options: [10, 25, 50, 100],
                    }}
                    empty={{
                        icon: <ArrowRightLeftIcon />,
                        title: 'No redirections found',
                        description:
                            'Try a different filter or create the first redirection.',
                    }}
                />
            </div>
        </AppLayout>
    );
}
