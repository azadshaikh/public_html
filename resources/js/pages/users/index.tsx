import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import {
    PlusIcon,
    PencilIcon,
    SearchIcon,
    ShieldCheckIcon,
    Trash2Icon,
    UserCogIcon,
    UsersIcon,
} from 'lucide-react';
import ManagedUserController from '@/actions/App/Http/Controllers/ManagedUserController';
import { ResourceFeedbackAlerts } from '@/components/resource/resource-feedback-alerts';
import { ResourceSectionCard } from '@/components/resource/resource-section-card';
import { ResourceStatCard } from '@/components/resource/resource-stat-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import {
    InputGroup,
    InputGroupAddon,
    InputGroupInput,
} from '@/components/ui/input-group';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
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
        href: ManagedUserController.index(),
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
    const canAddUsers = page.props.auth.abilities.addUsers;
    const canDeleteUsers = page.props.auth.abilities.deleteUsers;

    const handleDelete = (user: ManagedUserListItem) => {
        if (!canDeleteUsers) {
            return;
        }

        if (!window.confirm(`Delete ${user.name}?`)) {
            return;
        }

        router.delete(ManagedUserController.destroy(user.id).url, {
            preserveScroll: true,
        });
    };

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Users"
            description="Manage account status and role assignments so migrated features can rely on stable access control."
            headerActions={
                canAddUsers ? (
                    <Button asChild>
                        <Link href={ManagedUserController.create()}>
                            <PlusIcon data-icon="inline-start" />
                            New user
                        </Link>
                    </Button>
                ) : undefined
            }
        >
            <Head title="Users" />

            <div className="flex flex-col gap-6">
                <section className="grid gap-4 md:grid-cols-3">
                    <ResourceStatCard
                        title="Total users"
                        value={stats.total}
                        description="All accounts currently in the application."
                    />
                    <ResourceStatCard
                        title="Active users"
                        value={stats.active}
                        description="Accounts currently marked as active."
                    />
                    <ResourceStatCard
                        title="Inactive users"
                        value={stats.inactive}
                        description="Accounts that remain stored but are flagged inactive."
                    />
                </section>

                <ResourceSectionCard
                    title="Filter users"
                    description="Narrow the registry by name, email, status, or assigned role."
                >
                    <Form
                        {...ManagedUserController.index.form()}
                        method="get"
                        options={{ preserveScroll: true }}
                        className="grid gap-4 xl:grid-cols-[minmax(0,2fr)_minmax(200px,1fr)_minmax(200px,1fr)_auto]"
                    >
                        <InputGroup className="w-full">
                            <InputGroupAddon>
                                <SearchIcon />
                            </InputGroupAddon>
                            <InputGroupInput
                                name="search"
                                defaultValue={filters.search}
                                placeholder="Search users by name or email"
                            />
                        </InputGroup>

                        <NativeSelect
                            name="status"
                            className="w-full"
                            defaultValue={filters.status}
                        >
                            <NativeSelectOption value="all">
                                All statuses
                            </NativeSelectOption>
                            <NativeSelectOption value="active">
                                Active
                            </NativeSelectOption>
                            <NativeSelectOption value="inactive">
                                Inactive
                            </NativeSelectOption>
                        </NativeSelect>

                        <NativeSelect
                            name="role"
                            className="w-full"
                            defaultValue={filters.role}
                        >
                            <NativeSelectOption value="">
                                All roles
                            </NativeSelectOption>
                            {roles.map((role) => (
                                <NativeSelectOption
                                    key={role.id}
                                    value={role.name}
                                >
                                    {role.display_name}
                                </NativeSelectOption>
                            ))}
                        </NativeSelect>

                        <Button type="submit">Apply</Button>
                    </Form>
                </ResourceSectionCard>

                <ResourceFeedbackAlerts
                    status={status}
                    statusIcon={<ShieldCheckIcon />}
                    error={error}
                    errorIcon={<UserCogIcon />}
                />

                <ResourceSectionCard
                    title="User registry"
                    description="Each account can hold multiple roles while permissions continue to flow through role bundles."
                >
                    {users.length === 0 ? (
                        <Empty>
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <UsersIcon />
                                </EmptyMedia>
                                <EmptyTitle>No users found</EmptyTitle>
                                <EmptyDescription>
                                    Adjust the filters or create a matching
                                    account first.
                                </EmptyDescription>
                            </EmptyHeader>
                        </Empty>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>User</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Email verification</TableHead>
                                    <TableHead>Roles</TableHead>
                                    <TableHead className="text-right">
                                        Actions
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {users.map((user: ManagedUserListItem) => (
                                    <TableRow key={user.id}>
                                        <TableCell className="align-top">
                                            <div className="flex flex-col gap-1">
                                                <span className="font-medium text-foreground">
                                                    {user.name}
                                                </span>
                                                <span className="text-sm text-muted-foreground">
                                                    {user.email}
                                                </span>
                                            </div>
                                        </TableCell>
                                        <TableCell>
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
                                        </TableCell>
                                        <TableCell>
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
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex flex-wrap gap-2">
                                                {user.roles.map((role) => (
                                                    <Badge
                                                        key={role.id}
                                                        variant="outline"
                                                    >
                                                        {role.display_name}
                                                    </Badge>
                                                ))}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex justify-end gap-2">
                                                <Button
                                                    asChild
                                                    variant="outline"
                                                    size="sm"
                                                >
                                                    <Link
                                                        href={ManagedUserController.edit(
                                                            user.id,
                                                        )}
                                                    >
                                                        <PencilIcon data-icon="inline-start" />
                                                        Edit
                                                    </Link>
                                                </Button>

                                                {canDeleteUsers ? (
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() =>
                                                            handleDelete(user)
                                                        }
                                                    >
                                                        <Trash2Icon data-icon="inline-start" />
                                                        Delete
                                                    </Button>
                                                ) : null}
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </ResourceSectionCard>
            </div>
        </AppLayout>
    );
}
