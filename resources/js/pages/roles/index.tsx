import { Link, router } from '@inertiajs/react';
import {
    CircleIcon,
    ListIcon,
    PencilIcon,
    PlusIcon,
    ShieldAlertIcon,
    ShieldCheckIcon,
    Trash2Icon,
    UserRoundCheckIcon,
    UsersIcon,
} from 'lucide-react';
import RoleController from '@/actions/App/Http/Controllers/RoleController';
import { Datagrid } from '@/components/datagrid/datagrid';
import type {
    DatagridAction,
    DatagridBulkAction,
    DatagridColumn,
    DatagridFilter,
    DatagridTab,
} from '@/components/datagrid/datagrid';
import { ResourceFeedbackAlerts } from '@/components/resource/resource-feedback-alerts';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes/index';
import type { BreadcrumbItem } from '@/types';
import type { RoleListItem, RolesIndexPageProps } from '@/types/role';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
    },
    {
        title: 'Roles',
        href: RoleController.index(),
    },
];

export default function RolesIndex({
    roles,
    filters,
    stats,
    status,
    error,
}: RolesIndexPageProps) {
    const handleDelete = (role: RoleListItem) => {
        if (role.is_system || role.users_count > 0) {
            return;
        }

        if (!window.confirm(`Delete ${role.display_name}?`)) {
            return;
        }

        router.delete(RoleController.destroy(role.id).url, {
            preserveScroll: true,
        });
    };

    const handleBulkDelete = (
        selectedRoles: RoleListItem[],
        clearSelection: () => void,
    ) => {
        if (selectedRoles.length === 0) {
            return;
        }

        const label =
            selectedRoles.length === 1
                ? selectedRoles[0].display_name
                : `${selectedRoles.length} roles`;

        if (!window.confirm(`Delete ${label}?`)) {
            return;
        }

        router.delete(RoleController.bulkDestroy().url, {
            data: {
                role_ids: selectedRoles.map((role) => role.id),
            },
            preserveScroll: true,
            onSuccess: () => {
                clearSelection();
            },
        });
    };

    const gridFilters: DatagridFilter[] = [
        {
            type: 'search',
            name: 'search',
            value: filters.search,
            placeholder: 'Search...',
            className: 'lg:min-w-80',
        },
    ];

    const scopeTabs: DatagridTab[] = [
        {
            label: 'All',
            value: 'all',
            count: stats.total,
            active: filters.scope === 'all',
            icon: <ListIcon />,
            countVariant: 'secondary',
        },
        {
            label: 'System',
            value: 'system',
            count: stats.system,
            active: filters.scope === 'system',
            icon: <ShieldCheckIcon />,
            countVariant: 'secondary',
        },
        {
            label: 'Custom',
            value: 'custom',
            count: stats.custom,
            active: filters.scope === 'custom',
            icon: <CircleIcon />,
            countVariant: 'outline',
        },
    ];

    const columns: DatagridColumn<RoleListItem>[] = [
        {
            key: 'role',
            header: 'Role name',
            sortable: true,
            sortKey: 'role',
            cell: (role) => (
                <div className="flex min-w-0 flex-col gap-1">
                    <span className="font-medium text-foreground">
                        {role.display_name}
                    </span>
                    <span className="max-w-xl text-sm text-muted-foreground">
                        {role.description ?? 'No description provided.'}
                    </span>
                    <code className="mt-1 w-fit rounded bg-muted px-1.5 py-0.5 text-[0.7rem] text-muted-foreground">
                        {role.name}
                    </code>
                </div>
            ),
        },
        {
            key: 'permissions',
            header: 'Permissions',
            headerClassName: 'w-28 text-center',
            cellClassName: 'w-28 text-center',
            sortable: true,
            sortKey: 'permissions',
            cell: (role) => role.permissions_count,
        },
        {
            key: 'users',
            header: 'Users',
            headerClassName: 'w-24 text-center',
            cellClassName: 'w-24 text-center',
            sortable: true,
            sortKey: 'users',
            cell: (role) => (
                <span className="inline-flex items-center justify-center gap-1.5">
                    <UsersIcon className="size-4 text-muted-foreground" />
                    {role.users_count}
                </span>
            ),
        },
        {
            key: 'status',
            header: 'Status',
            headerClassName: 'w-28 text-center',
            cellClassName: 'w-28 text-center',
            sortable: true,
            sortKey: 'status',
            cell: (role) => (
                <Badge variant={role.is_system ? 'secondary' : 'outline'}>
                    {role.is_system ? 'System' : 'Custom'}
                </Badge>
            ),
        },
    ];

    const rowActions = (role: RoleListItem): DatagridAction[] => {
        const deleteDisabled = role.is_system || role.users_count > 0;

        return [
            {
                label: 'Edit',
                href: RoleController.edit(role.id).url,
                icon: <PencilIcon />,
            },
            {
                label: 'Delete',
                onSelect: () => handleDelete(role),
                icon: <Trash2Icon />,
                variant: 'destructive',
                disabled: deleteDisabled,
            },
        ];
    };

    const bulkActions: DatagridBulkAction<RoleListItem>[] = [
        {
            key: 'bulk-delete',
            label: 'Delete selected',
            icon: <Trash2Icon />,
            variant: 'destructive',
            onSelect: handleBulkDelete,
        },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Roles"
            description="Manage user roles and permissions"
            headerActions={
                <Button asChild>
                    <Link href={RoleController.create()}>
                        <PlusIcon data-icon="inline-start" />
                        Add Role
                    </Link>
                </Button>
            }
        >
            <div className="flex flex-col gap-6">
                <ResourceFeedbackAlerts
                    status={status}
                    statusIcon={<ShieldCheckIcon />}
                    error={error}
                    errorIcon={<ShieldAlertIcon />}
                />

                <Datagrid
                    action={RoleController.index().url}
                    rows={roles}
                    columns={columns}
                    filters={gridFilters}
                    tabs={{
                        name: 'scope',
                        items: scopeTabs,
                    }}
                    getRowKey={(role) => role.id}
                    rowActions={rowActions}
                    bulkActions={bulkActions}
                    isRowSelectable={(role) =>
                        !role.is_system && role.users_count === 0
                    }
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
                        storageKey: 'roles-datagrid-view',
                    }}
                    renderCard={(role) => (
                        <div className="flex flex-col gap-4">
                            <div className="flex flex-col gap-1">
                                <span className="font-medium text-foreground">
                                    {role.display_name}
                                </span>
                                <span className="text-sm text-muted-foreground">
                                    {role.description ??
                                        'No description provided.'}
                                </span>
                                <code className="mt-1 w-fit rounded bg-muted px-1.5 py-0.5 text-[0.7rem] text-muted-foreground">
                                    {role.name}
                                </code>
                            </div>

                            <div className="grid gap-3 sm:grid-cols-3">
                                <div className="rounded-lg border bg-muted/30 px-3 py-2">
                                    <div className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                        Permissions
                                    </div>
                                    <div className="mt-1 text-sm font-medium text-foreground">
                                        {role.permissions_count}
                                    </div>
                                </div>
                                <div className="rounded-lg border bg-muted/30 px-3 py-2">
                                    <div className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                        Users
                                    </div>
                                    <div className="mt-1 inline-flex items-center gap-1.5 text-sm font-medium text-foreground">
                                        <UserRoundCheckIcon className="size-4 text-muted-foreground" />
                                        {role.users_count}
                                    </div>
                                </div>
                                <div className="rounded-lg border bg-muted/30 px-3 py-2">
                                    <div className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                        Status
                                    </div>
                                    <div className="mt-1">
                                        <Badge
                                            variant={
                                                role.is_system
                                                    ? 'secondary'
                                                    : 'outline'
                                            }
                                        >
                                            {role.is_system
                                                ? 'System'
                                                : 'Custom'}
                                        </Badge>
                                    </div>
                                </div>
                            </div>
                        </div>
                    )}
                    submitLabel="Filters"
                    submitButtonVariant="outline"
                    empty={{
                        icon: <ShieldCheckIcon />,
                        title: 'No roles found',
                        description:
                            'Try a different filter or create the first custom role.',
                    }}
                />
            </div>
        </AppLayout>
    );
}
