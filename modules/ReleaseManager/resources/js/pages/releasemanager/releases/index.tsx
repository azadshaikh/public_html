import { Link, router, usePage } from '@inertiajs/react';
import {
    FileIcon,
    PencilIcon,
    PlusIcon,
    RefreshCwIcon,
    Trash2Icon,
} from 'lucide-react';
import { Datagrid } from '@/components/datagrid/datagrid';
import type {
    DatagridAction,
    DatagridBulkAction,
    DatagridColumn,
} from '@/components/datagrid/datagrid';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { AuthenticatedSharedData, BreadcrumbItem } from '@/types';
import { buildScaffoldDatagridState } from '@/lib/scaffold-datagrid';
import { releaseRouteParams } from '../../../lib/helpers';

export default function ReleasesIndex({
    config,
    rows,
    filters,
    statistics,
    type,
}: any) {
    const page = usePage<AuthenticatedSharedData>();
    const routeNamespace =
        type === 'module'
            ? 'releasemanager.module'
            : 'releasemanager.application';
    const abilities = page.props.auth.abilities || {};
    // Ensure fallback abilities in case they aren't matching exact strings
    const canAddReleases = abilities.addReleases ?? true;
    const canEditReleases = abilities.editReleases ?? true;
    const canDeleteReleases = abilities.deleteReleases ?? true;
    const canRestoreReleases = abilities.restoreReleases ?? true;
    const { currentStatus, gridFilters, perPage, sorting, statusTabs } = buildScaffoldDatagridState(config, filters, statistics, {
        searchPlaceholder: 'Search releases...',
    });
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Releases', href: route('releasemanager.releases.index', releaseRouteParams(type, { status: 'all' })) },
    ];

    const handleBulkAction = (
        action: string,
        selected: any[],
        clearSelection: () => void,
    ) => {
        if (selected.length === 0) return;
        router.post(
            route(`${routeNamespace}.bulk-action`),
            {
                action,
                ids: selected.map((p: any) => p.id),
                status: currentStatus,
            },
            { preserveScroll: true, onSuccess: () => clearSelection() },
        );
    };

    const columns: DatagridColumn<any>[] = [
        {
            key: 'version',
            header: 'Version',
            sortable: true,
            cell: (item) => (
                <Link
                    href={route(`${routeNamespace}.edit`, { release: item.id })}
                    className="flex min-w-0 flex-col gap-1 hover:opacity-80"
                >
                    <span className="font-medium text-foreground">
                        {item.version}
                    </span>
                </Link>
            ),
        },
        { key: 'package_identifier', header: 'Package', sortable: true },
        {
            key: 'version_type_label',
            header: 'Type',
            type: 'badge',
            sortable: true,
            sortKey: 'version_type',
            badgeVariantKey: 'version_type_badge',
        },
        {
            key: 'status_label',
            header: 'Status',
            type: 'badge',
            sortable: true,
            sortKey: 'status',
            badgeVariantKey: 'status_badge',
        },
        {
            key: 'release_at',
            header: 'Release Date',
            type: 'date',
            sortable: true,
        },
        { key: 'created_at', header: 'Created', type: 'date', sortable: true },
    ];

    const rowActions = (item: any): DatagridAction[] => {
        if (item.is_trashed) {
            return [
                ...(canRestoreReleases
                    ? [
                          {
                              label: 'Restore',
                              icon: <RefreshCwIcon />,
                              href: route(`${routeNamespace}.restore`, {
                                  release: item.id,
                              }),
                              method: 'PATCH' as const,
                              confirm: `Restore "${item.version}"?`,
                          },
                      ]
                    : []),
                ...(canDeleteReleases
                    ? [
                          {
                              label: 'Delete Permanently',
                              icon: <Trash2Icon />,
                              href: route(`${routeNamespace}.force-delete`, {
                                  release: item.id,
                              }),
                              method: 'DELETE' as const,
                              confirm: `⚠️ Permanently delete "${item.version}"? This cannot be undone!`,
                              variant: 'destructive' as const,
                          },
                      ]
                    : []),
            ];
        }
        return [
            ...(canEditReleases
                ? [
                      {
                          label: 'Edit',
                          href: route(`${routeNamespace}.edit`, {
                              release: item.id,
                          }),
                          icon: <PencilIcon />,
                      },
                  ]
                : []),
            ...(canDeleteReleases
                ? [
                      {
                          label: 'Move to Trash',
                          href: route(`${routeNamespace}.destroy`, {
                              release: item.id,
                          }),
                          method: 'DELETE' as const,
                          confirm: `Move "${item.version}" to trash?`,
                          icon: <Trash2Icon />,
                          variant: 'destructive' as const,
                      },
                  ]
                : []),
        ];
    };

    const bulkActions: DatagridBulkAction<any>[] = [
        ...(canDeleteReleases
            ? [
                  {
                      key: 'bulk-delete',
                      label: 'Move to Trash',
                      icon: <Trash2Icon />,
                      variant: 'destructive' as const,
                      confirm: 'Move selected releases to trash?',
                      onSelect: (items: any[], clear: () => void) =>
                          handleBulkAction('delete', items, clear),
                  },
              ]
            : []),
        ...(canRestoreReleases
            ? [
                  {
                      key: 'bulk-restore',
                      label: 'Restore',
                      icon: <RefreshCwIcon />,
                      confirm: 'Restore selected releases from trash?',
                      onSelect: (items: any[], clear: () => void) =>
                          handleBulkAction('restore', items, clear),
                  },
              ]
            : []),
        ...(canDeleteReleases
            ? [
                  {
                      key: 'bulk-force-delete',
                      label: 'Delete Permanently',
                      icon: <Trash2Icon />,
                      variant: 'destructive' as const,
                      confirm:
                          '⚠️ Permanently delete selected releases? This cannot be undone!',
                      onSelect: (items: any[], clear: () => void) =>
                          handleBulkAction('force_delete', items, clear),
                  },
              ]
            : []),
    ];

    const visibleBulkActions =
        currentStatus === 'trash'
            ? bulkActions.filter((a) => a.key !== 'bulk-delete')
            : bulkActions.filter((a) => a.key === 'bulk-delete');

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={
                type === 'application'
                    ? 'Application Releases'
                    : 'Module Releases'
            }
            description="Manage releases and versions"
            headerActions={
                canAddReleases ? (
                    <Button asChild>
                        <Link href={route(`${routeNamespace}.create`)}>
                            <PlusIcon data-icon="inline-start" />
                            Add Release
                        </Link>
                    </Button>
                ) : undefined
            }
        >
            <div className="flex flex-col gap-6">
                <Datagrid
                    action={route('releasemanager.releases.index', releaseRouteParams(type))}
                    rows={rows}
                    columns={columns}
                    filters={gridFilters}
                    tabs={{ name: 'status', items: statusTabs }}
                    getRowKey={(item) => item.id}
                    rowActions={rowActions}
                    bulkActions={visibleBulkActions}
                    isRowSelectable={() => visibleBulkActions.length > 0}
                    sorting={sorting}
                    perPage={perPage}
                    view={{
                        value: (filters.view as 'table' | 'cards') ?? 'table',
                        storageKey: 'rm-releases-datagrid-view',
                    }}
                    empty={{
                        icon: <FileIcon />,
                        title: 'No releases found',
                        description:
                            'Try a different filter or create the first release.',
                    }}
                />
            </div>
        </AppLayout>
    );
}
