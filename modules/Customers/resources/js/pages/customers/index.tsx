import { Link, router, usePage } from '@inertiajs/react';
import {
    PencilIcon,
    PlusIcon,
    RefreshCwIcon,
    Trash2Icon,
    UsersIcon,
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
    CustomerIndexPageProps,
    CustomerListItem,
} from '../../types/customers';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Customers', href: route('app.customers.index') },
];

export default function CustomersIndex({
    config,
    rows,
    filters,
    statistics,
}: CustomerIndexPageProps) {
    const page = usePage<AuthenticatedSharedData>();
    const canAdd = page.props.auth.abilities.addCustomers;
    const canEdit = page.props.auth.abilities.editCustomers;
    const canDelete = page.props.auth.abilities.deleteCustomers;
    const canRestore = page.props.auth.abilities.restoreCustomers;

    const { currentStatus, gridFilters, perPage, sorting, statusTabs } =
        buildScaffoldDatagridState(config, filters, statistics, {
            searchPlaceholder: 'Search customers...',
        });

    const handleBulkAction = (
        action: string,
        selected: CustomerListItem[],
        clearSelection: () => void,
    ) => {
        if (selected.length === 0) return;
        router.post(
            route('app.customers.bulk-action'),
            {
                action,
                ids: selected.map((c) => c.id),
                status: currentStatus,
            },
            { preserveScroll: true, onSuccess: () => clearSelection() },
        );
    };

    const columns: DatagridColumn<CustomerListItem>[] = [
        {
            key: 'company_name_display',
            header: 'Name',
            sortable: true,
            sortKey: 'name',
            cell: (customer) => (
                <Link
                    href={customer.show_url}
                    className="font-medium text-foreground hover:underline"
                >
                    {customer.company_name_display}
                </Link>
            ),
        },
        {
            key: 'email',
            header: 'Email',
            headerClassName: 'w-[220px]',
            cellClassName: 'w-[220px] text-sm text-muted-foreground',
            sortable: true,
        },
        {
            key: 'phone',
            header: 'Phone',
            headerClassName: 'w-[160px]',
            cellClassName: 'w-[160px] text-sm text-muted-foreground',
        },
        {
            key: 'tier',
            header: 'Tier',
            headerClassName: 'w-[100px] text-center',
            cellClassName: 'w-[100px] text-center text-sm',
            sortable: true,
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

    const rowActions = (customer: CustomerListItem): DatagridAction[] => {
        if (customer.is_trashed) {
            return [
                ...(canRestore
                    ? [
                          {
                              label: 'Restore',
                              icon: <RefreshCwIcon />,
                              href: route(
                                  'app.customers.restore',
                                  customer.id,
                              ),
                              method: 'PATCH' as const,
                              confirm: `Restore "${customer.company_name_display}"?`,
                          },
                      ]
                    : []),
                ...(canDelete
                    ? [
                          {
                              label: 'Delete Permanently',
                              icon: <Trash2Icon />,
                              href: route(
                                  'app.customers.force-delete',
                                  customer.id,
                              ),
                              method: 'DELETE' as const,
                              confirm: `⚠️ Permanently delete "${customer.company_name_display}"? This cannot be undone!`,
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
                          href: customer.edit_url,
                          icon: <PencilIcon />,
                      },
                  ]
                : []),
            ...(canDelete
                ? [
                      {
                          label: 'Move to Trash',
                          href: route(
                              'app.customers.destroy',
                              customer.id,
                          ),
                          method: 'DELETE' as const,
                          confirm: `Move "${customer.company_name_display}" to trash?`,
                          icon: <Trash2Icon />,
                          variant: 'destructive' as const,
                      },
                  ]
                : []),
        ];
    };

    const bulkActions: DatagridBulkAction<CustomerListItem>[] = [
        ...(canDelete
            ? [
                  {
                      key: 'bulk-delete',
                      label: 'Move to Trash',
                      icon: <Trash2Icon />,
                      variant: 'destructive' as const,
                      confirm: 'Move selected customers to trash?',
                      onSelect: (
                          items: CustomerListItem[],
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
                      confirm: 'Restore selected customers?',
                      onSelect: (
                          items: CustomerListItem[],
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
                      confirm: '⚠️ Permanently delete selected customers?',
                      onSelect: (
                          items: CustomerListItem[],
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
            title="Customers"
            description="Manage your customers"
            headerActions={
                canAdd ? (
                    <Button asChild>
                        <Link href={route('app.customers.create')}>
                            <PlusIcon data-icon="inline-start" />
                            Add Customer
                        </Link>
                    </Button>
                ) : undefined
            }
        >
            <div className="flex flex-col gap-6">
                <Datagrid
                    action={route('app.customers.index')}
                    rows={rows}
                    columns={columns}
                    scaffoldColumns={config.columns}
                    filters={gridFilters}
                    tabs={{ name: 'status', items: statusTabs }}
                    getRowKey={(c) => c.id}
                    rowActions={rowActions}
                    bulkActions={visibleBulkActions}
                    isRowSelectable={() => visibleBulkActions.length > 0}
                    sorting={sorting}
                    perPage={perPage}
                    renderCard={(customer) => (
                        <div className="flex flex-col gap-3">
                            <Link
                                href={customer.show_url}
                                className="font-semibold text-foreground hover:underline"
                            >
                                {customer.company_name_display}
                            </Link>
                            <div className="space-y-1 text-sm text-muted-foreground">
                                <div>{customer.email}</div>
                                {customer.phone && (
                                    <div>{customer.phone}</div>
                                )}
                            </div>
                            <div className="mt-auto flex flex-wrap items-center gap-2 pt-2">
                                <Badge
                                    variant={
                                        customer.status_badge as Parameters<
                                            typeof Badge
                                        >[0]['variant']
                                    }
                                >
                                    {customer.status_label}
                                </Badge>
                                {customer.tier && (
                                    <Badge variant="outline">
                                        {customer.tier}
                                    </Badge>
                                )}
                            </div>
                        </div>
                    )}
                    empty={{
                        icon: <UsersIcon />,
                        title: 'No customers found',
                        description:
                            'Try a different filter or add your first customer.',
                    }}
                />
            </div>
        </AppLayout>
    );
}
