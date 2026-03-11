import { Form, Link, router } from '@inertiajs/react';
import {
    PlusIcon,
    SearchIcon,
    ShieldAlertIcon,
    ShieldCheckIcon,
    Trash2Icon,
    PencilIcon,
    UsersIcon,
} from 'lucide-react';
import RoleController from '@/actions/App/Http/Controllers/RoleController';
import { ResourceFeedbackAlerts } from '@/components/resource/resource-feedback-alerts';
import { ResourceSectionCard } from '@/components/resource/resource-section-card';
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
                <ResourceSectionCard
                    title="Filter roles"
                    description="Search by label, key, or description and narrow the result set."
                >
                    <Form
                        {...RoleController.index.form()}
                        method="get"
                        options={{ preserveScroll: true }}
                        className="grid gap-4 md:grid-cols-[minmax(0,2fr)_minmax(200px,1fr)_auto]"
                    >
                        <InputGroup className="w-full">
                            <InputGroupAddon>
                                <SearchIcon />
                            </InputGroupAddon>
                            <InputGroupInput
                                name="search"
                                defaultValue={filters.search}
                                placeholder="Search roles"
                            />
                        </InputGroup>

                        <NativeSelect
                            name="scope"
                            className="w-full"
                            defaultValue={filters.scope}
                        >
                            <NativeSelectOption value="all">
                                All roles
                            </NativeSelectOption>
                            <NativeSelectOption value="system">
                                System roles
                            </NativeSelectOption>
                            <NativeSelectOption value="custom">
                                Custom roles
                            </NativeSelectOption>
                        </NativeSelect>

                        <Button type="submit">Apply</Button>
                    </Form>
                </ResourceSectionCard>

                <ResourceFeedbackAlerts
                    status={status}
                    statusIcon={<ShieldCheckIcon />}
                    error={error}
                    errorIcon={<ShieldAlertIcon />}
                />

                <ResourceSectionCard
                    title="Role registry"
                    description="Roles are sorted with platform roles first so migration-critical access stays obvious."
                >
                    {roles.length === 0 ? (
                        <Empty>
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <ShieldCheckIcon />
                                </EmptyMedia>
                                <EmptyTitle>No roles found</EmptyTitle>
                                <EmptyDescription>
                                    Try a different filter or create the first
                                    custom role.
                                </EmptyDescription>
                            </EmptyHeader>
                        </Empty>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Role</TableHead>
                                    <TableHead>Key</TableHead>
                                    <TableHead>Permissions</TableHead>
                                    <TableHead>Users</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">
                                        Actions
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {roles.map((role: RoleListItem) => {
                                    const deleteDisabled =
                                        role.is_system || role.users_count > 0;

                                    return (
                                        <TableRow key={role.id}>
                                            <TableCell className="align-top">
                                                <div className="flex flex-col gap-1">
                                                    <span className="font-medium text-foreground">
                                                        {role.display_name}
                                                    </span>
                                                    <span className="max-w-lg text-sm text-muted-foreground">
                                                        {role.description ??
                                                            'No description provided.'}
                                                    </span>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <code className="rounded bg-muted px-2 py-1 text-xs">
                                                    {role.name}
                                                </code>
                                            </TableCell>
                                            <TableCell>
                                                {role.permissions_count}
                                            </TableCell>
                                            <TableCell>
                                                <span className="inline-flex items-center gap-1.5">
                                                    <UsersIcon className="size-4 text-muted-foreground" />
                                                    {role.users_count}
                                                </span>
                                            </TableCell>
                                            <TableCell>
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
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex justify-end gap-2">
                                                    <Button
                                                        asChild
                                                        variant="outline"
                                                        size="sm"
                                                    >
                                                        <Link
                                                            href={RoleController.edit(
                                                                role.id,
                                                            )}
                                                        >
                                                            <PencilIcon data-icon="inline-start" />
                                                            Edit
                                                        </Link>
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        disabled={
                                                            deleteDisabled
                                                        }
                                                        onClick={() =>
                                                            handleDelete(role)
                                                        }
                                                    >
                                                        <Trash2Icon data-icon="inline-start" />
                                                        Delete
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    );
                                })}
                            </TableBody>
                        </Table>
                    )}
                </ResourceSectionCard>
            </div>
        </AppLayout>
    );
}
