import { Link, router, usePage } from '@inertiajs/react';
import {
    PencilIcon,
    PlusIcon,
    RefreshCwIcon,
    Trash2Icon,
    UserIcon,
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
    CustomerContactIndexPageProps,
    CustomerContactListItem,
} from '../../../types/customers';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Customers', href: route('app.customers.index') },
    { title: 'Contacts', href: route('app.customers.contacts.index') },
];

export default function CustomerContactsIndex({
    config,
    rows,
    filters,
    statistics,
}: CustomerContactIndexPageProps) {
    const page = usePage<AuthenticatedSharedData>();
    const canAdd = page.props.auth.abilities.addCustomerContacts;
    const canEdit = page.props.auth.abilities.editCustomerContacts;
    const canDelete = page.props.auth.abilities.deleteCustomerContacts;
    const canRestore = page.props.auth.abilities.restoreCustomerContacts;

    const { currentStatus, gridFilters, perPage, sorting, statusTabs } =
        buildScaffoldDatagridState(config, filters, statistics, {
            searchPlaceholder: 'Search contacts...',
        });

    const handleBulkAction = (
        action: string,
        selected: CustomerContactListItem[],
        clearSelection: () => void,
    ) => {
        if (selected.length === 0) return;
        router.post(
            route('app.customers.contacts.bulk-action'),
            {
                action,
                ids: selected.map((c) => c.id),
                status: currentStatus,
            },
            { preserveScroll: true, onSuccess: () => clearSelection() },
        );
    };

    const columns: DatagridColumn<CustomerContactListItem>[] = [
        {
            key: 'full_name',
            header: 'Name',
            sortable: true,
            sortKey: 'name',
            cell: (contact) => (
                <div className="flex items-center gap-2">
                    <Link
                        href={contact.show_url}
                        className="font-medium text-foreground hover:underline"
                    >
                        {contact.full_name}
                    </Link>
                    {contact.is_primary && (
                        <Badge variant="info">Primary</Badge>
                    )}
                </div>
            ),
        },
        {
            key: 'customer_name',
            header: 'Customer',
            headerClassName: 'w-[200px]',
            cellClassName: 'w-[200px]',
            cell: (contact) =>
                contact.customer_show_url ? (
                    <Link
                        href={contact.customer_show_url}
                        className="text-sm text-muted-foreground hover:underline"
                    >
                        {contact.customer_name}
                    </Link>
                ) : (
                    <span className="text-sm text-muted-foreground">
                        {contact.customer_name || '—'}
                    </span>
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
            key: 'status_label',
            header: 'Status',
            headerClassName: 'w-[120px] text-center',
            cellClassName: 'w-[120px] text-center',
            type: 'badge',
            badgeVariantKey: 'status_badge',
            sortable: true,
            sortKey: 'status',
        },
    ];

    const rowActions = (
        contact: CustomerContactListItem,
    ): DatagridAction[] => {
        if (contact.is_trashed) {
            return [
                ...(canRestore
                    ? [
                          {
                              label: 'Restore',
                              icon: <RefreshCwIcon />,
                              href: route(
                                  'app.customers.contacts.restore',
                                  contact.id,
                              ),
                              method: 'PATCH' as const,
                              confirm: `Restore "${contact.full_name}"?`,
                          },
                      ]
                    : []),
                ...(canDelete
                    ? [
                          {
                              label: 'Delete Permanently',
                              icon: <Trash2Icon />,
                              href: route(
                                  'app.customers.contacts.force-delete',
                                  contact.id,
                              ),
                              method: 'DELETE' as const,
                              confirm: `⚠️ Permanently delete "${contact.full_name}"? This cannot be undone!`,
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
                          href: contact.edit_url,
                          icon: <PencilIcon />,
                      },
                  ]
                : []),
            ...(canDelete
                ? [
                      {
                          label: 'Move to Trash',
                          href: route(
                              'app.customers.contacts.destroy',
                              contact.id,
                          ),
                          method: 'DELETE' as const,
                          confirm: `Move "${contact.full_name}" to trash?`,
                          icon: <Trash2Icon />,
                          variant: 'destructive' as const,
                      },
                  ]
                : []),
        ];
    };

    const bulkActions: DatagridBulkAction<CustomerContactListItem>[] = [
        ...(canDelete
            ? [
                  {
                      key: 'bulk-delete',
                      label: 'Move to Trash',
                      icon: <Trash2Icon />,
                      variant: 'destructive' as const,
                      confirm: 'Move selected contacts to trash?',
                      onSelect: (
                          items: CustomerContactListItem[],
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
                      confirm: 'Restore selected contacts?',
                      onSelect: (
                          items: CustomerContactListItem[],
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
                          '⚠️ Permanently delete selected contacts?',
                      onSelect: (
                          items: CustomerContactListItem[],
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
            title="Customer Contacts"
            description="Manage customer contacts"
            headerActions={
                canAdd ? (
                    <Button asChild>
                        <Link href={route('app.customers.contacts.create')}>
                            <PlusIcon data-icon="inline-start" />
                            Add Contact
                        </Link>
                    </Button>
                ) : undefined
            }
        >
            <div className="flex flex-col gap-6">
                <Datagrid
                    action={route('app.customers.contacts.index')}
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
                    renderCard={(contact) => (
                        <div className="flex flex-col gap-3">
                            <div className="flex items-center gap-2">
                                <Link
                                    href={contact.show_url}
                                    className="font-semibold text-foreground hover:underline"
                                >
                                    {contact.full_name}
                                </Link>
                                {contact.is_primary && (
                                    <Badge variant="info">Primary</Badge>
                                )}
                            </div>
                            <div className="space-y-1 text-sm text-muted-foreground">
                                <div>{contact.email}</div>
                                {contact.customer_name && (
                                    <div>{contact.customer_name}</div>
                                )}
                            </div>
                            <div className="mt-auto pt-2">
                                <Badge
                                    variant={
                                        contact.status_badge as Parameters<
                                            typeof Badge
                                        >[0]['variant']
                                    }
                                >
                                    {contact.status_label}
                                </Badge>
                            </div>
                        </div>
                    )}
                    empty={{
                        icon: <UserIcon />,
                        title: 'No contacts found',
                        description:
                            'Try a different filter or add your first contact.',
                    }}
                />
            </div>
        </AppLayout>
    );
}
