import { Link, router, usePage } from '@inertiajs/react';
import {
    BanIcon,
    CheckCircleIcon,
    EyeIcon,
    ListIcon,
    PauseCircleIcon,
    PencilIcon,
    PlusIcon,
    RefreshCwIcon,
    ShieldCheckIcon,
    Trash2Icon,
    UserCogIcon,
    UsersIcon,
} from 'lucide-react';
import { Datagrid } from '@/components/datagrid/datagrid';
import type {
    DatagridAction,
    DatagridBulkAction,
    DatagridColumn,
    DatagridFilter,
    DatagridTab,
} from '@/components/datagrid/datagrid';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useInitials } from '@/hooks/use-initials';
import AppLayout from '@/layouts/app-layout';
import type { AuthenticatedSharedData, BreadcrumbItem } from '@/types';
import type {
    UserListItem,
    UserRowAction,
    UsersIndexPageProps,
} from '@/types/user-management';

// =========================================================================
// CONSTANTS
// =========================================================================

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Users', href: route('app.users.index') },
];

const ICON_MAP: Record<string, React.ReactNode> = {
    'ri-eye-line': <EyeIcon />,
    'ri-pencil-line': <PencilIcon />,
    'ri-user-settings-line': <UserCogIcon />,
    'ri-pause-circle-line': <PauseCircleIcon />,
    'ri-forbid-line': <BanIcon />,
    'ri-checkbox-circle-line': <CheckCircleIcon />,
    'ri-delete-bin-line': <Trash2Icon />,
    'ri-delete-bin-fill': <Trash2Icon />,
    'ri-refresh-line': <RefreshCwIcon />,
};

// =========================================================================
// HELPERS
// =========================================================================

function mapBackendAction(action: UserRowAction): DatagridAction {
    const icon = ICON_MAP[action.icon];
    const isDestructive = action.method === 'DELETE' || action.label === 'Ban';

    // Navigation actions (GET without side effects)
    if (action.method === 'GET') {
        return {
            label: action.label,
            icon,
            href: action.url,
        };
    }

    // Mutating actions — use Inertia router via href + method + confirm
    return {
        label: action.label,
        icon,
        href: action.url,
        method: action.method,
        confirm: action.confirm,
        variant: isDestructive ? 'destructive' : 'default',
    };
}

// =========================================================================
// COMPONENT
// =========================================================================

export default function UsersIndex({
    users,
    filters,
    statistics,
    roles,
    showPendingTab,
}: UsersIndexPageProps) {
    const page = usePage<AuthenticatedSharedData>();
    const getInitials = useInitials();

    const canAddUsers = page.props.auth.abilities.addUsers;
    const canEditUsers = page.props.auth.abilities.editUsers;
    const canDeleteUsers = page.props.auth.abilities.deleteUsers;
    const canRestoreUsers = page.props.auth.abilities.restoreUsers;
    const currentUserId = page.props.auth.user.id;

    // ----- Filters -----

    const gridFilters: DatagridFilter[] = [
        {
            type: 'search',
            name: 'search',
            value: filters.search,
            placeholder: 'Search users...',
            className: 'lg:min-w-80',
        },
        {
            type: 'select',
            name: 'role_id',
            value: filters.role_id,
            options: [
                { value: '', label: 'All roles' },
                ...Object.entries(roles).map(([id, name]) => ({
                    value: id,
                    label: name,
                })),
            ],
        },
        {
            type: 'select',
            name: 'email_verified',
            value: filters.email_verified,
            options: [
                { value: '', label: 'All verification' },
                { value: 'verified', label: 'Verified' },
                { value: 'unverified', label: 'Unverified' },
            ],
        },
        {
            type: 'select',
            name: 'gender',
            value: filters.gender,
            options: [
                { value: '', label: 'All genders' },
                { value: 'male', label: 'Male' },
                { value: 'female', label: 'Female' },
                { value: 'other', label: 'Other' },
            ],
        },
        {
            type: 'date_range',
            name: 'created_at',
            value: filters.created_at,
            label: 'Registration date',
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
            icon: <ShieldCheckIcon />,
            countVariant: 'success',
        },
        ...(showPendingTab
            ? [
                  {
                      label: 'Pending',
                      value: 'pending',
                      count: statistics.pending,
                      active: filters.status === 'pending',
                      icon: <PauseCircleIcon />,
                      countVariant: 'warning' as const,
                  },
              ]
            : []),
        {
            label: 'Suspended',
            value: 'suspended',
            count: statistics.suspended,
            active: filters.status === 'suspended',
            icon: <PauseCircleIcon />,
            countVariant: 'warning',
        },
        {
            label: 'Banned',
            value: 'banned',
            count: statistics.banned,
            active: filters.status === 'banned',
            icon: <BanIcon />,
            countVariant: 'danger',
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

    const renderUserIdentity = (user: UserListItem) => (
        <Link
            href={user.show_url}
            className="flex min-w-0 items-center gap-3 hover:opacity-80"
        >
            <Avatar className="size-8 overflow-hidden rounded-full">
                <AvatarImage
                    src={user.avatar_url ?? undefined}
                    alt={user.name}
                />
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
        </Link>
    );

    const columns: DatagridColumn<UserListItem>[] = [
        {
            key: 'name',
            header: 'User',
            sortable: true,
            sortKey: 'name',
            cell: renderUserIdentity,
        },
        {
            key: 'email_verified',
            header: 'Verified',
            headerClassName: 'w-28 text-center',
            cellClassName: 'w-28 text-center',
            sortable: true,
            sortKey: 'email_verified_at',
            cell: (user) => (
                <Badge variant={user.email_verified ? 'secondary' : 'outline'}>
                    {user.email_verified ? 'Verified' : 'Unverified'}
                </Badge>
            ),
        },
        {
            key: 'roles',
            header: 'Roles',
            cell: (user) => (
                <div className="flex flex-wrap gap-1">
                    {user.roles.map((role) => (
                        <Badge key={role} variant="outline">
                            {role}
                        </Badge>
                    ))}
                </div>
            ),
        },
        {
            key: 'status_label',
            header: 'Status',
            headerClassName: 'w-28 text-center',
            cellClassName: 'w-28 text-center',
            sortable: true,
            sortKey: 'status',
            type: 'badge',
            badgeVariantKey: 'status_badge',
        },
        {
            key: 'created_at',
            header: 'Registered',
            headerClassName: 'w-36',
            cellClassName: 'w-36',
            sortable: true,
            sortKey: 'created_at',
            cell: (user) => (
                <span
                    className="text-sm text-muted-foreground"
                    title={user.created_at}
                >
                    {user.created_at_human}
                </span>
            ),
        },
    ];

    // ----- Row actions (from backend per-row) -----

    const rowActions = (user: UserListItem): DatagridAction[] =>
        Object.values(user.actions).map(mapBackendAction);

    // ----- Bulk actions -----

    const handleBulkAction = (
        action: string,
        selectedUsers: UserListItem[],
        clearSelection: () => void,
    ) => {
        if (selectedUsers.length === 0) {
            return;
        }

        router.post(
            route('app.users.bulk-action'),
            {
                action,
                ids: selectedUsers.map((u) => u.id),
            },
            {
                preserveScroll: true,
                onSuccess: () => clearSelection(),
            },
        );
    };

    const bulkActions: DatagridBulkAction<UserListItem>[] = [
        ...(canDeleteUsers
            ? [
                  {
                      key: 'bulk-delete',
                      label: 'Move to Trash',
                      icon: <Trash2Icon />,
                      variant: 'destructive' as const,
                      confirm: 'Move selected users to trash?',
                      onSelect: (rows: UserListItem[], clear: () => void) =>
                          handleBulkAction('delete', rows, clear),
                  },
              ]
            : []),
        ...(canEditUsers
            ? [
                  {
                      key: 'bulk-suspend',
                      label: 'Suspend',
                      icon: <PauseCircleIcon />,
                      confirm:
                          'Suspend selected users? They will be unable to log in.',
                      onSelect: (rows: UserListItem[], clear: () => void) =>
                          handleBulkAction('suspend', rows, clear),
                      hidden: filters.status === 'trash',
                  },
                  {
                      key: 'bulk-ban',
                      label: 'Ban',
                      icon: <BanIcon />,
                      variant: 'destructive' as const,
                      confirm:
                          'Ban selected users? They will be permanently blocked from logging in.',
                      onSelect: (rows: UserListItem[], clear: () => void) =>
                          handleBulkAction('ban', rows, clear),
                      hidden: filters.status === 'trash',
                  },
                  {
                      key: 'bulk-unban',
                      label: 'Unban',
                      icon: <CheckCircleIcon />,
                      confirm: 'Unban selected users?',
                      onSelect: (rows: UserListItem[], clear: () => void) =>
                          handleBulkAction('unban', rows, clear),
                      hidden: filters.status !== 'banned',
                  },
              ]
            : []),
        ...(canRestoreUsers
            ? [
                  {
                      key: 'bulk-restore',
                      label: 'Restore',
                      icon: <RefreshCwIcon />,
                      confirm: 'Restore selected users from trash?',
                      onSelect: (rows: UserListItem[], clear: () => void) =>
                          handleBulkAction('restore', rows, clear),
                      hidden: filters.status !== 'trash',
                  },
              ]
            : []),
        ...(canDeleteUsers
            ? [
                  {
                      key: 'bulk-force-delete',
                      label: 'Delete Permanently',
                      icon: <Trash2Icon />,
                      variant: 'destructive' as const,
                      confirm:
                          '⚠️ Permanently delete selected users? This cannot be undone!',
                      onSelect: (rows: UserListItem[], clear: () => void) =>
                          handleBulkAction('force_delete', rows, clear),
                      hidden: filters.status !== 'trash',
                  },
              ]
            : []),
    ].filter((action) => !action.hidden);

    // ----- Render -----

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Users"
            description="Manage user accounts, roles, and access control."
            headerActions={
                canAddUsers ? (
                    <Button asChild>
                        <Link href={route('app.users.create')}>
                            <PlusIcon data-icon="inline-start" />
                            New user
                        </Link>
                    </Button>
                ) : undefined
            }
        >
            <div className="flex flex-col gap-6">
                <Datagrid
                    action={route('app.users.index')}
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
                    isRowSelectable={(user) =>
                        bulkActions.length > 0 && user.id !== currentUserId
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
                        storageKey: 'users-datagrid-view',
                    }}
                    renderCardHeader={renderUserIdentity}
                    renderCard={(user) => (
                        <div className="flex flex-col gap-4">
                            <div className="grid gap-3 sm:grid-cols-3">
                                <div className="rounded-lg border bg-muted/30 px-3 py-2">
                                    <div className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                        Status
                                    </div>
                                    <div className="mt-1">
                                        <Badge
                                            variant={
                                                user.status_badge ?? 'outline'
                                            }
                                        >
                                            {user.status_label}
                                        </Badge>
                                    </div>
                                </div>

                                <div className="rounded-lg border bg-muted/30 px-3 py-2">
                                    <div className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                        Verified
                                    </div>
                                    <div className="mt-1">
                                        <Badge
                                            variant={
                                                user.email_verified
                                                    ? 'secondary'
                                                    : 'outline'
                                            }
                                        >
                                            {user.email_verified
                                                ? 'Verified'
                                                : 'Unverified'}
                                        </Badge>
                                    </div>
                                </div>

                                <div className="rounded-lg border bg-muted/30 px-3 py-2">
                                    <div className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                        Registered
                                    </div>
                                    <div className="mt-1 text-sm text-muted-foreground">
                                        {user.created_at_human}
                                    </div>
                                </div>
                            </div>

                            <div className="flex flex-wrap gap-1">
                                {user.roles.map((role) => (
                                    <Badge key={role} variant="outline">
                                        {role}
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
                            'Try a different filter or create a new user account.',
                    }}
                />
            </div>
        </AppLayout>
    );
}
