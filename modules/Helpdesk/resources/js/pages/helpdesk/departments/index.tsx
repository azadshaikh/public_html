import { Link, router, usePage } from '@inertiajs/react';
import {
    BuildingIcon,
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
import { buildScaffoldDatagridState } from '@/lib/scaffold-datagrid';
import type { AuthenticatedSharedData, BreadcrumbItem } from '@/types';
import type {
    DepartmentIndexPageProps,
    DepartmentListItem,
} from '../../../types/helpdesk';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Departments', href: route('helpdesk.departments.index') },
];

export default function DepartmentsIndex({
    config,
    rows,
    filters,
    statistics,
}: DepartmentIndexPageProps) {
    const page = usePage<AuthenticatedSharedData>();
    const canAdd =
        page.props.auth.abilities.addHelpdeskDepartments;
    const canEdit =
        page.props.auth.abilities.editHelpdeskDepartments;
    const canDelete =
        page.props.auth.abilities.deleteHelpdeskDepartments;
    const canRestore =
        page.props.auth.abilities.restoreHelpdeskDepartments;

    const { currentStatus, gridFilters, perPage, sorting, statusTabs } =
        buildScaffoldDatagridState(config, filters, statistics, {
            searchPlaceholder: 'Search departments...',
        });

    const handleBulkAction = (
        action: string,
        selected: DepartmentListItem[],
        clearSelection: () => void,
    ) => {
        if (selected.length === 0) return;
        router.post(
            route('helpdesk.departments.bulk-action'),
            {
                action,
                ids: selected.map((d) => d.id),
                status: currentStatus,
            },
            { preserveScroll: true, onSuccess: () => clearSelection() },
        );
    };

    const columns: DatagridColumn<DepartmentListItem>[] = [
        {
            key: 'name',
            header: 'Name',
            sortable: true,
            cell: (dept) => (
                <Link
                    href={dept.show_url}
                    className="font-medium text-foreground hover:underline"
                >
                    {dept.name}
                </Link>
            ),
        },
        {
            key: 'department_head_name',
            header: 'Department Head',
            headerClassName: 'w-[200px]',
            cellClassName: 'w-[200px] text-sm text-muted-foreground',
            sortable: true,
            sortKey: 'department_head',
        },
        {
            key: 'visibility_label',
            header: 'Visibility',
            headerClassName: 'w-[120px] text-center',
            cellClassName: 'w-[120px] text-center',
            type: 'badge',
            badgeVariantKey: 'visibility_badge',
            sortable: true,
            sortKey: 'visibility',
        },
        {
            key: 'status_label',
            header: 'Status',
            headerClassName: 'w-[120px] text-center',
            cellClassName: 'w-[120px] text-center',
            type: 'badge',
            badgeVariantKey: 'status_badge',
            sortable: true,
            sortKey: 'status',
        },
        {
            key: 'created_at_formatted',
            header: 'Created',
            headerClassName: 'w-[160px]',
            cellClassName: 'w-[160px] text-sm text-muted-foreground',
            sortable: true,
            sortKey: 'created_at',
        },
    ];

    const rowActions = (dept: DepartmentListItem): DatagridAction[] => {
        if (dept.is_trashed) {
            return [
                ...(canRestore
                    ? [
                          {
                              label: 'Restore',
                              icon: <RefreshCwIcon />,
                              href: route(
                                  'helpdesk.departments.restore',
                                  dept.id,
                              ),
                              method: 'PATCH' as const,
                              confirm: `Restore "${dept.name}"?`,
                          },
                      ]
                    : []),
                ...(canDelete
                    ? [
                          {
                              label: 'Delete Permanently',
                              icon: <Trash2Icon />,
                              href: route(
                                  'helpdesk.departments.force-delete',
                                  dept.id,
                              ),
                              method: 'DELETE' as const,
                              confirm: `⚠️ Permanently delete "${dept.name}"? This cannot be undone!`,
                              variant: 'destructive' as const,
                          },
                      ]
                    : []),
            ];
        }
        return [
            ...(canEdit
                ? [
                      {
                          label: 'Edit',
                          href: dept.edit_url,
                          icon: <PencilIcon />,
                      },
                  ]
                : []),
            ...(canDelete
                ? [
                      {
                          label: 'Move to Trash',
                          href: route(
                              'helpdesk.departments.destroy',
                              dept.id,
                          ),
                          method: 'DELETE' as const,
                          confirm: `Move "${dept.name}" to trash?`,
                          icon: <Trash2Icon />,
                          variant: 'destructive' as const,
                      },
                  ]
                : []),
        ];
    };

    const bulkActions: DatagridBulkAction<DepartmentListItem>[] = [
        ...(canDelete
            ? [
                  {
                      key: 'bulk-delete',
                      label: 'Move to Trash',
                      icon: <Trash2Icon />,
                      variant: 'destructive' as const,
                      confirm: 'Move selected departments to trash?',
                      onSelect: (
                          items: DepartmentListItem[],
                          clear: () => void,
                      ) => handleBulkAction('delete', items, clear),
                  },
              ]
            : []),
        ...(canRestore
            ? [
                  {
                      key: 'bulk-restore',
                      label: 'Restore',
                      icon: <RefreshCwIcon />,
                      confirm: 'Restore selected departments?',
                      onSelect: (
                          items: DepartmentListItem[],
                          clear: () => void,
                      ) => handleBulkAction('restore', items, clear),
                  },
              ]
            : []),
        ...(canDelete
            ? [
                  {
                      key: 'bulk-force-delete',
                      label: 'Delete Permanently',
                      icon: <Trash2Icon />,
                      variant: 'destructive' as const,
                      confirm:
                          '⚠️ Permanently delete selected departments?',
                      onSelect: (
                          items: DepartmentListItem[],
                          clear: () => void,
                      ) => handleBulkAction('force_delete', items, clear),
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
            title="Departments"
            description="Manage helpdesk departments"
            headerActions={
                canAdd ? (
                    <Button asChild>
                        <Link href={route('helpdesk.departments.create')}>
                            <PlusIcon data-icon="inline-start" />
                            Add Department
                        </Link>
                    </Button>
                ) : undefined
            }
        >
            <div className="flex flex-col gap-6">
                <Datagrid
                    action={route('helpdesk.departments.index')}
                    rows={rows}
                    columns={columns}
                    scaffoldColumns={config.columns}
                    filters={gridFilters}
                    tabs={{ name: 'status', items: statusTabs }}
                    getRowKey={(dept) => dept.id}
                    rowActions={rowActions}
                    bulkActions={visibleBulkActions}
                    isRowSelectable={() => visibleBulkActions.length > 0}
                    sorting={sorting}
                    perPage={perPage}
                    renderCard={(dept) => (
                        <div className="flex flex-col gap-3">
                            <Link
                                href={dept.show_url}
                                className="font-semibold text-foreground hover:underline"
                            >
                                {dept.name}
                            </Link>
                            <div className="text-sm text-muted-foreground">
                                Head: {dept.department_head_name}
                            </div>
                            <div className="mt-auto flex flex-wrap items-center gap-2 pt-2">
                                <Badge
                                    variant={
                                        dept.visibility_badge as Parameters<
                                            typeof Badge
                                        >[0]['variant']
                                    }
                                >
                                    {dept.visibility_label}
                                </Badge>
                                <Badge
                                    variant={
                                        dept.status_badge as Parameters<
                                            typeof Badge
                                        >[0]['variant']
                                    }
                                >
                                    {dept.status_label}
                                </Badge>
                            </div>
                        </div>
                    )}
                    empty={{
                        icon: <BuildingIcon />,
                        title: 'No departments found',
                        description:
                            'Try a different filter or create the first department.',
                    }}
                />
            </div>
        </AppLayout>
    );
}
