import { Link, router, usePage } from '@inertiajs/react';
import {
    BuildingIcon,
    EyeIcon,
    ListIcon,
    MapPinIcon,
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
    DatagridFilter,
    DatagridTab,
} from '@/components/datagrid/datagrid';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { AuthenticatedSharedData, BreadcrumbItem } from '@/types';
import type { AddressIndexPageProps, AddressListItem } from '@/types/address';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Addresses', href: route('app.masters.addresses.index') },
];

export default function AddressesIndex({
    addresses,
    filters,
    statistics,
}: AddressIndexPageProps) {
    const page = usePage<AuthenticatedSharedData>();
    const canAddAddresses = page.props.auth.abilities.addAddresses;
    const canEditAddresses = page.props.auth.abilities.editAddresses;
    const canDeleteAddresses = page.props.auth.abilities.deleteAddresses;
    const canRestoreAddresses = page.props.auth.abilities.restoreAddresses;

    // ----- Bulk action helper -----

    const handleBulkAction = (
        action: string,
        selectedAddresses: AddressListItem[],
        clearSelection: () => void,
    ) => {
        if (selectedAddresses.length === 0) {
            return;
        }

        router.post(
            route('app.masters.addresses.bulk-action'),
            {
                action,
                ids: selectedAddresses.map((a) => a.id),
                status: filters.status,
            },
            {
                preserveScroll: true,
                onSuccess: () => clearSelection(),
            },
        );
    };

    // ----- Filters -----

    const gridFilters: DatagridFilter[] = [
        {
            type: 'search',
            name: 'search',
            value: filters.search,
            placeholder: 'Search addresses...',
            className: 'lg:min-w-80',
        },
        {
            type: 'select',
            name: 'type',
            value: filters.type,
            options: [
                { value: 'home', label: 'Home' },
                { value: 'work', label: 'Work' },
                { value: 'billing', label: 'Billing' },
                { value: 'shipping', label: 'Shipping' },
                { value: 'other', label: 'Other' },
            ],
        },
        {
            type: 'boolean',
            name: 'is_primary',
            value: filters.is_primary,
            label: 'Primary',
            trueLabel: 'Yes',
            falseLabel: 'No',
        },
        {
            type: 'date_range',
            name: 'created_at',
            value: filters.created_at,
            label: 'Created Date',
        },
    ];

    // ----- Status tabs -----

    const statusTabs: DatagridTab[] = [
        {
            label: 'All',
            value: 'all',
            count: statistics.total,
            active: filters.status === 'all',
            icon: <ListIcon />,
            countVariant: 'secondary',
        },
        {
            label: 'Trash',
            value: 'trash',
            count: statistics.trash,
            active: filters.status === 'trash',
            icon: <Trash2Icon />,
            countVariant: 'destructive',
        },
    ];

    // ----- Columns -----

    const columns: DatagridColumn<AddressListItem>[] = [
        {
            key: 'full_name',
            header: 'Name / Address',
            sortable: true,
            sortKey: 'first_name',
            cell: (address) => (
                <Link
                    href={address.show_url}
                    className="flex min-w-0 flex-col gap-1 hover:opacity-80"
                >
                    <span className="font-medium text-foreground">
                        {address.full_name ?? address.address1}
                    </span>
                    <span className="truncate text-xs text-muted-foreground">
                        {address.address1}
                        {address.city ? `, ${address.city}` : ''}
                    </span>
                </Link>
            ),
        },
        {
            key: 'type_label',
            header: 'Type',
            headerClassName: 'w-28 text-center',
            cellClassName: 'w-28 text-center',
            sortable: true,
            sortKey: 'type',
            cell: (address) => (
                <Badge
                    variant={
                        (address.type_class as React.ComponentProps<
                            typeof Badge
                        >['variant']) ?? 'outline'
                    }
                >
                    {address.type_label}
                </Badge>
            ),
        },
        {
            key: 'city',
            header: 'City',
            sortable: true,
            cell: (address) =>
                address.city || (
                    <span className="text-muted-foreground">—</span>
                ),
        },
        {
            key: 'country_code',
            header: 'Country',
            sortable: true,
            cell: (address) => (
                <span className="text-sm">
                    {address.country_name ?? address.country_code}
                </span>
            ),
        },
        {
            key: 'primary_label',
            header: 'Primary',
            headerClassName: 'w-24 text-center',
            cellClassName: 'w-24 text-center',
            sortable: true,
            sortKey: 'is_primary',
            cell: (address) => (
                <Badge
                    variant={
                        (address.primary_class as React.ComponentProps<
                            typeof Badge
                        >['variant']) ?? 'outline'
                    }
                >
                    {address.primary_label}
                </Badge>
            ),
        },
        {
            key: 'created_at',
            header: 'Created',
            type: 'date',
            sortable: true,
            cellClassName: 'text-muted-foreground',
        },
    ];

    // ----- Row actions -----

    const rowActions = (address: AddressListItem): DatagridAction[] => {
        if (address.deleted_at) {
            return [
                ...(canRestoreAddresses
                    ? [
                          {
                              label: 'Restore',
                              icon: <RefreshCwIcon />,
                              href: route(
                                  'app.masters.addresses.restore',
                                  address.id,
                              ),
                              method: 'PATCH' as const,
                              confirm: `Restore this address?`,
                          },
                      ]
                    : []),
                ...(canDeleteAddresses
                    ? [
                          {
                              label: 'Delete Permanently',
                              icon: <Trash2Icon />,
                              href: route(
                                  'app.masters.addresses.force-delete',
                                  address.id,
                              ),
                              method: 'DELETE' as const,
                              confirm: `⚠️ Permanently delete this address? This cannot be undone!`,
                              variant: 'destructive' as const,
                          },
                      ]
                    : []),
            ];
        }

        return [
            {
                label: 'View',
                href: address.show_url,
                icon: <EyeIcon />,
            },
            ...(canEditAddresses
                ? [
                      {
                          label: 'Edit',
                          href: address.edit_url,
                          icon: <PencilIcon />,
                      },
                  ]
                : []),
            ...(canDeleteAddresses
                ? [
                      {
                          label: 'Move to Trash',
                          href: route(
                              'app.masters.addresses.destroy',
                              address.id,
                          ),
                          method: 'DELETE' as const,
                          confirm: `Move this address to trash?`,
                          icon: <Trash2Icon />,
                          variant: 'destructive' as const,
                      },
                  ]
                : []),
        ];
    };

    // ----- Bulk actions -----

    const bulkActions: DatagridBulkAction<AddressListItem>[] = [
        ...(canDeleteAddresses
            ? [
                  {
                      key: 'bulk-delete',
                      label: 'Move to Trash',
                      icon: <Trash2Icon />,
                      variant: 'destructive' as const,
                      confirm: 'Move selected addresses to trash?',
                      onSelect: (rows: AddressListItem[], clear: () => void) =>
                          handleBulkAction('delete', rows, clear),
                  },
              ]
            : []),
        ...(canRestoreAddresses
            ? [
                  {
                      key: 'bulk-restore',
                      label: 'Restore',
                      icon: <RefreshCwIcon />,
                      confirm: 'Restore selected addresses from trash?',
                      onSelect: (rows: AddressListItem[], clear: () => void) =>
                          handleBulkAction('restore', rows, clear),
                  },
              ]
            : []),
        ...(canDeleteAddresses
            ? [
                  {
                      key: 'bulk-force-delete',
                      label: 'Delete Permanently',
                      icon: <Trash2Icon />,
                      variant: 'destructive' as const,
                      confirm:
                          '⚠️ Permanently delete selected addresses? This cannot be undone!',
                      onSelect: (rows: AddressListItem[], clear: () => void) =>
                          handleBulkAction('force_delete', rows, clear),
                  },
              ]
            : []),
    ];

    const visibleBulkActions =
        filters.status === 'trash'
            ? bulkActions.filter((a) => a.key !== 'bulk-delete')
            : bulkActions.filter((a) => a.key === 'bulk-delete');

    // ----- Render -----

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Addresses"
            description="Manage physical and postal addresses"
            headerActions={
                canAddAddresses ? (
                    <Button asChild>
                        <Link href={route('app.masters.addresses.create')}>
                            <PlusIcon data-icon="inline-start" />
                            Add Address
                        </Link>
                    </Button>
                ) : undefined
            }
        >
            <div className="flex flex-col gap-6">
                <Datagrid
                    action={route('app.masters.addresses.index')}
                    rows={addresses}
                    columns={columns}
                    filters={gridFilters}
                    tabs={{
                        name: 'status',
                        items: statusTabs,
                    }}
                    getRowKey={(address) => address.id}
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
                    view={{
                        value: filters.view,
                        storageKey: 'addresses-datagrid-view',
                    }}
                    renderCardHeader={(address) => (
                        <>
                            <div className="flex size-10 shrink-0 items-center justify-center rounded-full bg-muted text-muted-foreground">
                                <MapPinIcon className="size-5" />
                            </div>
                            <div className="min-w-0 flex-1">
                                <div className="truncate font-medium text-foreground">
                                    {address.full_name ?? address.address1}
                                </div>
                                <div className="truncate text-xs text-muted-foreground">
                                    {address.address1}
                                    {address.city ? `, ${address.city}` : ''}
                                </div>
                            </div>
                        </>
                    )}
                    renderCard={(address) => (
                        <div className="grid gap-3 sm:grid-cols-3">
                            <div className="rounded-lg border bg-muted/30 px-3 py-2">
                                <div className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                    Type
                                </div>
                                <div className="mt-1">
                                    <Badge
                                        variant={
                                            (address.type_class as React.ComponentProps<
                                                typeof Badge
                                            >['variant']) ?? 'outline'
                                        }
                                    >
                                        {address.type_label}
                                    </Badge>
                                </div>
                            </div>
                            <div className="rounded-lg border bg-muted/30 px-3 py-2">
                                <div className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                    Country
                                </div>
                                <div className="mt-1 flex items-center gap-1.5 text-sm font-medium text-foreground">
                                    <BuildingIcon className="size-4 text-muted-foreground" />
                                    {address.country_name ??
                                        address.country_code}
                                </div>
                            </div>
                            <div className="rounded-lg border bg-muted/30 px-3 py-2">
                                <div className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                    Primary
                                </div>
                                <div className="mt-1">
                                    <Badge
                                        variant={
                                            (address.primary_class as React.ComponentProps<
                                                typeof Badge
                                            >['variant']) ?? 'outline'
                                        }
                                    >
                                        {address.primary_label}
                                    </Badge>
                                </div>
                            </div>
                        </div>
                    )}
                    submitLabel="Filters"
                    submitButtonVariant="outline"
                    empty={{
                        icon: <MapPinIcon />,
                        title: 'No addresses found',
                        description:
                            'Try a different filter or create the first address.',
                    }}
                />
            </div>
        </AppLayout>
    );
}
