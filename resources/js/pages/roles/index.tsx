import { Link, router } from '@inertiajs/react';
import {
    CheckCircleIcon,
    EyeIcon,
    ListIcon,
    PencilIcon,
    PlusIcon,
    RefreshCwIcon,
    ShieldAlertIcon,
    ShieldCheckIcon,
    ShieldIcon,
    SlashIcon,
    Trash2Icon,
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
    { title: 'Dashboard', href: dashboard() },
    { title: 'Roles', href: RoleController.index() },
];

const STATUS_BADGE_VARIANT: Record<
    string,
    'default' | 'secondary' | 'outline' | 'destructive'
> = {
    active: 'default',
    inactive: 'secondary',
    trashed: 'destructive',
};

export default function RolesIndex({
    roles,
    filters,
    statistics,
    status,
    error,
}: RolesIndexPageProps) {
    // ----- Bulk action helper -----

    const handleBulkAction = (
        action: string,
        selectedRoles: RoleListItem[],
        clearSelection: () => void,
    ) => {
        if (selectedRoles.length === 0) {
            return;
        }

        router.post(
            RoleController.bulkAction().url,
            {
                action,
                ids: selectedRoles.map((role) => role.id),
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
            placeholder: 'Search roles...',
            className: 'lg:min-w-80',
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
            label: 'Active',
            value: 'active',
            count: statistics.active,
            active: filters.status === 'active',
            icon: <CheckCircleIcon />,
            countVariant: 'secondary',
        },
        {
            label: 'Inactive',
            value: 'inactive',
            count: statistics.inactive,
            active: filters.status === 'inactive',
            icon: <SlashIcon />,
            countVariant: 'outline',
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

    const columns: DatagridColumn<RoleListItem>[] = [
        {
            key: 'role',
            header: 'Role name',
            sortable: true,
            sortKey: 'display_name',
            cell: (role) => (
                <Link
                    href={role.show_url}
                    className="flex min-w-0 flex-col gap-1 hover:opacity-80"
                >
                    <span className="inline-flex items-center gap-2 font-medium text-foreground">
                        {role.display_name}
                        {role.is_system && (
                            <Badge
                                variant="secondary"
                                className="text-[0.65rem]"
                            >
                                <ShieldIcon className="mr-0.5 size-3" />
                                System
                            </Badge>
                        )}
                    </span>
                    <code className="mt-1 w-fit rounded bg-muted px-1.5 py-0.5 text-[0.7rem] text-muted-foreground">
                        {role.name}
                    </code>
                </Link>
            ),
        },
        {
            key: 'permissions',
            header: 'Permissions',
            headerClassName: 'w-28 text-center',
            cellClassName: 'w-28 text-center',
            sortable: true,
            sortKey: 'permissions_count',
            cell: (role) => role.permissions_count,
        },
        {
            key: 'users',
            header: 'Users',
            headerClassName: 'w-24 text-center',
            cellClassName: 'w-24 text-center',
            sortable: true,
            sortKey: 'users_count',
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
                <Badge
                    variant={
                        STATUS_BADGE_VARIANT[
                            role.is_trashed ? 'trashed' : role.status
                        ] ?? 'outline'
                    }
                >
                    {role.status_label}
                </Badge>
            ),
        },
    ];

    // ----- Row actions -----

    const rowActions = (role: RoleListItem): DatagridAction[] => {
        if (role.is_trashed) {
            return [
                {
                    label: 'Restore',
                    icon: <RefreshCwIcon />,
                    href: RoleController.restore(role.id).url,
                    method: 'PATCH',
                    confirm: `Restore "${role.display_name}"?`,
                },
                {
                    label: 'Delete Permanently',
                    icon: <Trash2Icon />,
                    href: RoleController.forceDelete(role.id).url,
                    method: 'DELETE',
                    confirm: `⚠️ Permanently delete "${role.display_name}"? This cannot be undone!`,
                    variant: 'destructive',
                    disabled: role.is_system,
                },
            ];
        }

        const deleteDisabled = role.is_system || role.users_count > 0;

        return [
            {
                label: 'View',
                href: role.show_url,
                icon: <EyeIcon />,
            },
            {
                label: 'Edit',
                href: RoleController.edit(role.id).url,
                icon: <PencilIcon />,
            },
            {
                label: 'Move to Trash',
                onSelect: () => {
                    if (
                        window.confirm(`Move "${role.display_name}" to trash?`)
                    ) {
                        router.delete(RoleController.destroy(role.id).url, {
                            preserveScroll: true,
                        });
                    }
                },
                icon: <Trash2Icon />,
                variant: 'destructive',
                disabled: deleteDisabled,
            },
        ];
    };

    // ----- Bulk actions -----

    const bulkActions: DatagridBulkAction<RoleListItem>[] = [
        {
            key: 'bulk-delete',
            label: 'Move to Trash',
            icon: <Trash2Icon />,
            variant: 'destructive' as const,
            confirm: 'Move selected roles to trash?',
            onSelect: (rows: RoleListItem[], clear: () => void) =>
                handleBulkAction('delete', rows, clear),
        },
        {
            key: 'bulk-restore',
            label: 'Restore',
            icon: <RefreshCwIcon />,
            confirm: 'Restore selected roles from trash?',
            onSelect: (rows: RoleListItem[], clear: () => void) =>
                handleBulkAction('restore', rows, clear),
        },
        {
            key: 'bulk-force-delete',
            label: 'Delete Permanently',
            icon: <Trash2Icon />,
            variant: 'destructive' as const,
            confirm:
                '⚠️ Permanently delete selected roles? This cannot be undone!',
            onSelect: (rows: RoleListItem[], clear: () => void) =>
                handleBulkAction('force_delete', rows, clear),
        },
    ];

    // Filter bulk actions based on current tab
    const visibleBulkActions =
        filters.status === 'trash'
            ? bulkActions.filter((a) => a.key !== 'bulk-delete')
            : bulkActions.filter((a) => a.key === 'bulk-delete');

    // ----- Render -----

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
                        name: 'status',
                        items: statusTabs,
                    }}
                    getRowKey={(role) => role.id}
                    rowActions={rowActions}
                    bulkActions={visibleBulkActions}
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
                            <Link
                                href={role.show_url}
                                className="flex flex-col gap-1 hover:opacity-80"
                            >
                                <span className="inline-flex items-center gap-2 font-medium text-foreground">
                                    {role.display_name}
                                    {role.is_system && (
                                        <Badge
                                            variant="secondary"
                                            className="text-[0.65rem]"
                                        >
                                            <ShieldIcon className="mr-0.5 size-3" />
                                            System
                                        </Badge>
                                    )}
                                </span>
                                <code className="mt-1 w-fit rounded bg-muted px-1.5 py-0.5 text-[0.7rem] text-muted-foreground">
                                    {role.name}
                                </code>
                            </Link>

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
                                        <UsersIcon className="size-4 text-muted-foreground" />
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
                                                STATUS_BADGE_VARIANT[
                                                    role.is_trashed
                                                        ? 'trashed'
                                                        : role.status
                                                ] ?? 'outline'
                                            }
                                        >
                                            {role.status_label}
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
