import { Link, router, usePage } from '@inertiajs/react';
import {
    CopyIcon,
    MenuIcon,
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
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { AuthenticatedSharedData, BreadcrumbItem } from '@/types';
import { buildDatagridState } from '../../../lib/helpers';
import type { MenuIndexPageProps, MenuListItem } from '../../types/cms';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Menus', href: route('cms.appearance.menus.index') },
];

export default function MenusIndex({
    config,
    rows,
    filters,
    statistics,
}: MenuIndexPageProps) {
    const page = usePage<AuthenticatedSharedData>();
    const canAddMenus = page.props.auth.abilities.addMenus;
    const canEditMenus = page.props.auth.abilities.editMenus;
    const canDeleteMenus = page.props.auth.abilities.deleteMenus;
    const canRestoreMenus = page.props.auth.abilities.restoreMenus;
    const { currentStatus, gridFilters, perPage, sorting, statusTabs } =
        buildDatagridState(config, filters, statistics, 'Search menus...');

    const handleBulkAction = (
        action: string,
        selected: MenuListItem[],
        clearSelection: () => void,
    ) => {
        if (selected.length === 0) return;
        router.post(
            route('cms.appearance.menus.bulk-action'),
            { action, ids: selected.map((m) => m.id), status: currentStatus },
            { preserveScroll: true, onSuccess: () => clearSelection() },
        );
    };

    const columns: DatagridColumn<MenuListItem>[] = [
        {
            key: 'name',
            header: 'Menu',
            sortable: true,
            cell: (menu) => (
                <Link
                    href={menu.edit_url}
                    className="flex min-w-0 flex-col gap-1 hover:opacity-80"
                >
                    <span className="font-medium text-foreground">
                        {menu.name}
                    </span>
                    {menu.description && (
                        <span className="text-xs text-muted-foreground">
                            {menu.description}
                        </span>
                    )}
                </Link>
            ),
        },
        {
            key: 'location',
            header: 'Location',
            sortable: true,
            cell: (menu) => (
                <span className="text-sm text-muted-foreground">
                    {menu.location_label ?? (menu.location || '—')}
                </span>
            ),
        },
        {
            key: 'items_count',
            header: 'Items',
            headerClassName: 'w-24 text-center',
            cellClassName: 'w-24 text-center',
            sortable: true,
            sortKey: 'all_items_count',
            cell: (menu) => (
                <Badge variant={menu.items_count > 0 ? 'secondary' : 'outline'}>
                    {menu.items_count}
                </Badge>
            ),
        },
        {
            key: 'is_active',
            header: 'Status',
            headerClassName: 'w-28 text-center',
            cellClassName: 'w-28 text-center',
            cell: (menu) => (
                <Badge variant={menu.is_active ? 'success' : 'secondary'}>
                    {menu.is_active_label}
                </Badge>
            ),
        },
        {
            key: 'updated_at',
            header: 'Updated',
            headerClassName: 'w-32',
            cellClassName: 'w-32 text-sm text-muted-foreground',
            sortable: true,
        },
    ];

    const rowActions = (menu: MenuListItem): DatagridAction[] => {
        if (menu.is_trashed) {
            return [
                ...(canRestoreMenus
                    ? [
                          {
                              label: 'Restore',
                              icon: <RefreshCwIcon />,
                              href: route(
                                  'cms.appearance.menus.restore',
                                  menu.id,
                              ),
                              method: 'PATCH' as const,
                              confirm: `Restore "${menu.name}"?`,
                          },
                      ]
                    : []),
                ...(canDeleteMenus
                    ? [
                          {
                              label: 'Delete Permanently',
                              icon: <Trash2Icon />,
                              href: route(
                                  'cms.appearance.menus.force-delete',
                                  menu.id,
                              ),
                              method: 'DELETE' as const,
                              confirm: `⚠️ Permanently delete "${menu.name}"?`,
                              variant: 'destructive' as const,
                          },
                      ]
                    : []),
            ];
        }
        return [
            ...(canEditMenus
                ? [{ label: 'Edit', href: menu.edit_url, icon: <PencilIcon /> }]
                : []),
            ...(canAddMenus
                ? [
                      {
                          label: 'Duplicate',
                          href: route(
                              'cms.appearance.menus.duplicate',
                              menu.id,
                          ),
                          method: 'POST' as const,
                          confirm: `Create a copy of "${menu.name}" (without location assignment)?`,
                          icon: <CopyIcon />,
                      },
                  ]
                : []),
            ...(canDeleteMenus
                ? [
                      {
                          label: 'Move to Trash',
                          href: route('cms.appearance.menus.destroy', menu.id),
                          method: 'DELETE' as const,
                          confirm: `Move "${menu.name}" to trash?`,
                          icon: <Trash2Icon />,
                          variant: 'destructive' as const,
                      },
                  ]
                : []),
        ];
    };

    const bulkActions: DatagridBulkAction<MenuListItem>[] = [
        ...(canDeleteMenus
            ? [
                  {
                      key: 'bulk-delete',
                      label: 'Move to Trash',
                      icon: <Trash2Icon />,
                      variant: 'destructive' as const,
                      confirm: 'Move selected menus to trash?',
                      onSelect: (items: MenuListItem[], clear: () => void) =>
                          handleBulkAction('delete', items, clear),
                  },
              ]
            : []),
        ...(canRestoreMenus
            ? [
                  {
                      key: 'bulk-restore',
                      label: 'Restore',
                      icon: <RefreshCwIcon />,
                      confirm: 'Restore selected menus?',
                      onSelect: (items: MenuListItem[], clear: () => void) =>
                          handleBulkAction('restore', items, clear),
                  },
              ]
            : []),
        ...(canDeleteMenus
            ? [
                  {
                      key: 'bulk-force-delete',
                      label: 'Delete Permanently',
                      icon: <Trash2Icon />,
                      variant: 'destructive' as const,
                      confirm: '⚠️ Permanently delete selected menus?',
                      onSelect: (items: MenuListItem[], clear: () => void) =>
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
            title="Menus"
            description="Manage navigation menus"
            headerActions={
                canAddMenus ? (
                    <Button asChild>
                        <Link href={route('cms.appearance.menus.create')}>
                            <PlusIcon data-icon="inline-start" />
                            Add Menu
                        </Link>
                    </Button>
                ) : undefined
            }
        >
            <div className="flex flex-col gap-6">
                <Datagrid
                    action={route('cms.appearance.menus.index')}
                    rows={rows}
                    columns={columns}
                    filters={gridFilters}
                    tabs={{ name: 'status', items: statusTabs }}
                    getRowKey={(menu) => menu.id}
                    rowActions={rowActions}
                    bulkActions={visibleBulkActions}
                    isRowSelectable={() => visibleBulkActions.length > 0}
                    sorting={sorting}
                    perPage={perPage}
                    empty={{
                        icon: <MenuIcon />,
                        title: 'No menus found',
                        description: 'Create your first navigation menu.',
                    }}
                />
            </div>
        </AppLayout>
    );
}
