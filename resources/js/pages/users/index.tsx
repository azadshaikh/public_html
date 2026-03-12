import { Link, router, usePage } from '@inertiajs/react';
import {
    CircleOffIcon,
    ListIcon,
    PlusIcon,
    PencilIcon,
    ShieldCheckIcon,
    Trash2Icon,
    UserCogIcon,
    UsersIcon,
} from 'lucide-react';
import UserController from '@/actions/App/Http/Controllers/UserController';
import { Datagrid } from '@/components/datagrid/datagrid';
import type {
    DatagridAction,
    DatagridBulkAction,
    DatagridColumn,
    DatagridFilter,
    DatagridTab,
} from '@/components/datagrid/datagrid';
import { ResourceFeedbackAlerts } from '@/components/resource/resource-feedback-alerts';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useInitials } from '@/hooks/use-initials';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes/index';
import type { AuthenticatedSharedData, BreadcrumbItem } from '@/types';
import type {
    ManagedUserListItem,
    UsersIndexPageProps,
} from '@/types/user-management';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
    },
    {
        title: 'Users',
        href: UserController.index(),
    },
];

export default function UsersIndex({
    users,
    filters,
    stats,
    roles,
    status,
    error,
}: UsersIndexPageProps) {
    const page = usePage<AuthenticatedSharedData>();
    const getInitials = useInitials();
    const canAddUsers = page.props.auth.abilities.addUsers;
    const canEditUsers = page.props.auth.abilities.editUsers;
    const canDeleteUsers = page.props.auth.abilities.deleteUsers;
    const currentUserId = page.props.auth.user.id;

    const renderUserIdentity = (user: ManagedUserListItem) => (
        <div className="flex min-w-0 items-center gap-3">
            <Avatar className="size-8 overflow-hidden rounded-full">
                <AvatarImage src={user.avatar} alt={user.name} />
                <AvatarFallback className="rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white">
                    {getInitials(user.name)}
                </AvatarFallback>
            </Avatar>

            <div className="flex min-w-0 flex-col gap-1">
                <span className="truncate font-medium text-foreground">
                    {user.name}
                </span>
                <span className="truncate text-sm text-muted-foreground">
                    {user.email}
                </span>
            </div>
        </div>
    );

    const handleDelete = (user: ManagedUserListItem) => {
        if (!canDeleteUsers) {
            return;
        }

        if (!window.confirm(`Delete ${user.name}?`)) {
            return;
        }

        router.delete(UserController.destroy(user.id).url, {
            preserveScroll: true,
        });
    };

    const handleBulkDelete = (
        selectedUsers: ManagedUserListItem[],
        clearSelection: () => void,
    ) => {
        if (!canDeleteUsers || selectedUsers.length === 0) {
            return;
        }

        const label =
            selectedUsers.length === 1
                ? selectedUsers[0].name
                : `${selectedUsers.length} users`;

        if (!window.confirm(`Delete ${label}?`)) {
            return;
        }

        router.post(
            UserController.bulkAction().url,
            {
                action: 'delete',
                ids: selectedUsers.map((user) => user.id),
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    clearSelection();
                },
            },
        );
    };

    const gridFilters: DatagridFilter[] = [
        {
            type: 'search',
            name: 'search',
            value: filters.search,
            placeholder: 'Search...',
            className: 'lg:min-w-80',
        },
        {
            type: 'select',
            name: 'role',
            value: filters.role,
            options: [
                { value: '', label: 'All roles' },
                ...roles.map((role) => ({
                    value: role.name,
                    label: role.display_name,
                })),
            ],
        },
        {
            type: 'select',
            name: 'verification',
            value: filters.verification,
            options: [
                { value: 'all', label: 'All verification' },
                { value: 'verified', label: 'Verified' },
                { value: 'unverified', label: 'Unverified' },
            ],
        },
    ];

    const statusTabs: DatagridTab[] = [
        {
            label: 'All',
            value: 'all',
            count: stats.total,
            active: filters.status === 'all',
            icon: <ListIcon />,
            countVariant: 'secondary',
        },
        {
            label: 'Active',
            value: 'active',
            count: stats.active,
            active: filters.status === 'active',
            icon: <ShieldCheckIcon />,
            countVariant: 'secondary',
        },
        {
            label: 'Inactive',
            value: 'inactive',
            count: stats.inactive,
            active: filters.status === 'inactive',
            icon: <CircleOffIcon />,
            countVariant: 'outline',
        },
    ];

    const columns: DatagridColumn<ManagedUserListItem>[] = [
        {
            key: 'name',
            header: 'User',
            sortable: true,
            sortKey: 'name',
            cell: renderUserIdentity,
        },
        {
            key: 'status',
            header: 'Status',
            headerClassName: 'w-28 text-center',
            cellClassName: 'w-28 text-center',
            sortable: true,
            sortKey: 'status',
            cell: (user) => (
                <Badge variant={user.active ? 'secondary' : 'outline'}>
                    {user.active ? 'Active' : 'Inactive'}
                </Badge>
            ),
        },
        {
            key: 'verification',
            header: 'Verification',
            headerClassName: 'w-36 text-center',
            cellClassName: 'w-36 text-center',
            sortable: true,
            sortKey: 'verification',
            cell: (user) => (
                <Badge
                    variant={user.email_verified_at ? 'secondary' : 'outline'}
                >
                    {user.email_verified_at ? 'Verified' : 'Unverified'}
                </Badge>
            ),
        },
        {
            key: 'roles',
            header: 'Roles',
            sortable: true,
            sortKey: 'roles',
            cell: (user) => (
                <div className="flex flex-wrap justify-center gap-2 lg:justify-start">
                    {user.roles.map((role) => (
                        <Badge key={role.id} variant="outline">
                            {role.display_name}
                        </Badge>
                    ))}
                </div>
            ),
        },
    ];

    const rowActions =
        canEditUsers || canDeleteUsers
            ? (user: ManagedUserListItem): DatagridAction[] => {
                  const actions: DatagridAction[] = [];

                  if (canEditUsers) {
                      actions.push({
                          label: 'Edit',
                          href: UserController.edit(user.id).url,
                          icon: <PencilIcon />,
                      });
                  }

                  if (canDeleteUsers) {
                      actions.push({
                          label: 'Delete',
                          onSelect: () => handleDelete(user),
                          icon: <Trash2Icon />,
                          variant: 'destructive',
                      });
                  }

                  return actions;
              }
            : undefined;

    const bulkActions: DatagridBulkAction<ManagedUserListItem>[] =
        canDeleteUsers
            ? [
                  {
                      key: 'bulk-delete',
                      label: 'Delete selected',
                      icon: <Trash2Icon />,
                      variant: 'destructive',
                      onSelect: handleBulkDelete,
                  },
              ]
            : [];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Users"
            description="Manage account status and role assignments so migrated features can rely on stable access control."
            headerActions={
                canAddUsers ? (
                    <Button asChild>
                        <Link href={UserController.create()}>
                            <PlusIcon data-icon="inline-start" />
                            New user
                        </Link>
                    </Button>
                ) : undefined
            }
        >
            <div className="flex flex-col gap-6">
                <ResourceFeedbackAlerts
                    status={status}
                    statusIcon={<ShieldCheckIcon />}
                    error={error}
                    errorIcon={<UserCogIcon />}
                />

                <Datagrid
                    action={UserController.index().url}
                    rows={users}
                    columns={columns}
                    filters={gridFilters}
                    tabs={{
                        name: 'status',
                        items: statusTabs,
                    }}
                    getRowKey={(user) => user.id}
                    rowActions={rowActions}
                    bulkActions={bulkActions}
                    isRowSelectable={(user) => user.id !== currentUserId}
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
                        storageKey: 'users-datagrid-view',
                    }}
                    renderCardHeader={renderUserIdentity}
                    renderCard={(user) => (
                        <div className="flex flex-col gap-4">
                            <div className="grid gap-3 sm:grid-cols-2">
                                <div className="rounded-lg border bg-muted/30 px-3 py-2">
                                    <div className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                        Status
                                    </div>
                                    <div className="mt-1">
                                        <Badge
                                            variant={
                                                user.active
                                                    ? 'secondary'
                                                    : 'outline'
                                            }
                                        >
                                            {user.active
                                                ? 'Active'
                                                : 'Inactive'}
                                        </Badge>
                                    </div>
                                </div>

                                <div className="rounded-lg border bg-muted/30 px-3 py-2">
                                    <div className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                        Verification
                                    </div>
                                    <div className="mt-1">
                                        <Badge
                                            variant={
                                                user.email_verified_at
                                                    ? 'secondary'
                                                    : 'outline'
                                            }
                                        >
                                            {user.email_verified_at
                                                ? 'Verified'
                                                : 'Unverified'}
                                        </Badge>
                                    </div>
                                </div>
                            </div>

                            <div className="flex flex-wrap gap-2">
                                {user.roles.map((role) => (
                                    <Badge key={role.id} variant="outline">
                                        {role.display_name}
                                    </Badge>
                                ))}
                            </div>
                        </div>
                    )}
                    submitLabel="Filters"
                    submitButtonVariant="outline"
                    empty={{
                        icon: <UsersIcon />,
                        title: 'No users found',
                        description:
                            'Try a different filter or create a matching account first.',
                    }}
                />
            </div>
        </AppLayout>
    );
}
