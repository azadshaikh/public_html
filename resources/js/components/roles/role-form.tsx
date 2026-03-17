import { Link } from '@inertiajs/react';
import {
    ChevronDownIcon,
    ChevronRightIcon,
    ChevronsDownUpIcon,
    ChevronsUpDownIcon,
    SaveIcon,
    SearchIcon,
    ShieldCheckIcon,
} from 'lucide-react';
import type { FormEvent } from 'react';
import { useMemo, useState } from 'react';
import { FormErrorSummary } from '@/components/forms/form-error-summary';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
    FieldLegend,
    FieldSet,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    InputGroup,
    InputGroupAddon,
    InputGroupInput,
} from '@/components/ui/input-group';
import { Progress } from '@/components/ui/progress';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useAppForm } from '@/hooks/use-app-form';
import { formValidators } from '@/lib/forms';
import type {
    PermissionGroup,
    RoleEditingTarget,
    RoleFormValues,
    RoleStatusOption,
} from '@/types/role';

type RoleFormProps = {
    mode: 'create' | 'edit';
    role?: RoleEditingTarget;
    initialValues: RoleFormValues;
    statusOptions: RoleStatusOption[];
    permissionGroups: PermissionGroup[];
};

export default function RoleForm({
    mode,
    role,
    initialValues,
    statusOptions,
    permissionGroups,
}: RoleFormProps) {
    const [searchQuery, setSearchQuery] = useState('');
    const [collapsedGroups, setCollapsedGroups] = useState<Set<string>>(
        new Set(),
    );

    const form = useAppForm<RoleFormValues>({
        defaults: initialValues,
        rememberKey:
            mode === 'create' ? 'roles.create.form' : `roles.edit.${role?.id}`,
        dirtyGuard: { enabled: true },
        rules: {
            display_name: [formValidators.required('Display name')],
            name: [formValidators.required('Role key')],
            status: [formValidators.required('Status')],
        },
    });

    const submitMethod = role ? 'put' : 'post';
    const submitUrl = role
        ? route('app.roles.update', role.id)
        : route('app.roles.store');
    const submitLabel = mode === 'create' ? 'Create Role' : 'Update Role';

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit(submitMethod, submitUrl, {
            preserveScroll: true,
            successToast: {
                title: mode === 'create' ? 'Role created' : 'Role updated',
                description:
                    mode === 'create'
                        ? 'The role has been created successfully.'
                        : 'The role has been updated successfully.',
            },
            setDefaultsOnSuccess: mode === 'edit',
        });
    };

    const handleDisplayNameChange = (value: string) => {
        const derivedCurrentName = deriveRoleName(form.data.display_name);
        const canAutoUpdateName =
            !role?.is_system &&
            (form.data.name === '' || form.data.name === derivedCurrentName);

        form.setField('display_name', value);

        if (canAutoUpdateName) {
            form.setField('name', deriveRoleName(value));
        }
    };

    const togglePermission = (permissionId: number, checked: boolean) => {
        if (checked) {
            if (form.data.permissions.includes(permissionId)) return;

            form.setField('permissions', [
                ...form.data.permissions,
                permissionId,
            ]);
        } else {
            form.setField(
                'permissions',
                form.data.permissions.filter((id) => id !== permissionId),
            );
        }
    };

    const toggleGroupPermissions = (group: PermissionGroup) => {
        const groupIds = group.permissions.map((p) => p.id);
        const allChecked = groupIds.every((id) =>
            form.data.permissions.includes(id),
        );

        if (allChecked) {
            form.setField(
                'permissions',
                form.data.permissions.filter((id) => !groupIds.includes(id)),
            );
        } else {
            const newIds = groupIds.filter(
                (id) => !form.data.permissions.includes(id),
            );
            form.setField('permissions', [...form.data.permissions, ...newIds]);
        }
    };

    const toggleGroupCollapse = (groupKey: string) => {
        setCollapsedGroups((prev) => {
            const next = new Set(prev);
            if (next.has(groupKey)) {
                next.delete(groupKey);
            } else {
                next.add(groupKey);
            }
            return next;
        });
    };

    const collapseAll = () => {
        setCollapsedGroups(new Set(permissionGroups.map((g) => g.group)));
    };

    const expandAll = () => {
        setCollapsedGroups(new Set());
    };

    const filteredGroups = useMemo(() => {
        if (!searchQuery.trim()) return permissionGroups;

        const q = searchQuery.toLowerCase();

        return permissionGroups
            .map((group) => ({
                ...group,
                permissions: group.permissions.filter(
                    (p) =>
                        p.display_name.toLowerCase().includes(q) ||
                        p.name.toLowerCase().includes(q),
                ),
            }))
            .filter((group) => group.permissions.length > 0);
    }, [permissionGroups, searchQuery]);

    const totalPermissions = permissionGroups.reduce(
        (sum, g) => sum + g.permissions.length,
        0,
    );
    const selectedCount = form.data.permissions.length;
    const selectedPercent =
        totalPermissions > 0
            ? Math.round((selectedCount / totalPermissions) * 100)
            : 0;

    const allCollapsed =
        permissionGroups.length > 0 &&
        permissionGroups.every((g) => collapsedGroups.has(g.group));

    return (
        <form
            noValidate
            className="flex flex-col gap-6"
            onSubmit={handleSubmit}
        >
            {form.dirtyGuardDialog}
            <FormErrorSummary errors={form.errors} minMessages={2} />

            <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
                {/* ── Main column ── */}
                <div className="flex flex-col gap-6">
                    {/* Role Information */}
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <CardTitle>Role Information</CardTitle>
                                {role?.is_system && (
                                    <Badge variant="secondary">System</Badge>
                                )}
                            </div>
                            <CardDescription>
                                Display name, machine key, and whether this role
                                can be assigned in the application.
                            </CardDescription>
                        </CardHeader>

                        <CardContent>
                            <FieldGroup>
                                <Field
                                    data-invalid={
                                        form.invalid('display_name') ||
                                        undefined
                                    }
                                >
                                    <FieldLabel htmlFor="display_name">
                                        Display Name{' '}
                                        <span className="text-destructive">
                                            *
                                        </span>
                                    </FieldLabel>
                                    <Input
                                        id="display_name"
                                        value={form.data.display_name}
                                        onChange={(e) =>
                                            handleDisplayNameChange(
                                                e.target.value,
                                            )
                                        }
                                        onBlur={() =>
                                            form.touch('display_name')
                                        }
                                        aria-invalid={
                                            form.invalid('display_name') ||
                                            undefined
                                        }
                                        placeholder="Content Manager"
                                    />
                                    <FieldDescription>
                                        Human-friendly label shown in the admin
                                        UI.
                                    </FieldDescription>
                                    <FieldError>
                                        {form.error('display_name')}
                                    </FieldError>
                                </Field>

                                <Field
                                    data-invalid={
                                        form.invalid('name') || undefined
                                    }
                                >
                                    <FieldLabel htmlFor="name">
                                        Role Key{' '}
                                        <span className="text-destructive">
                                            *
                                        </span>
                                    </FieldLabel>
                                    <Input
                                        id="name"
                                        value={form.data.name}
                                        onChange={(e) =>
                                            form.setField(
                                                'name',
                                                deriveRoleName(e.target.value),
                                            )
                                        }
                                        onBlur={() => form.touch('name')}
                                        aria-invalid={
                                            form.invalid('name') || undefined
                                        }
                                        placeholder="content_manager"
                                        disabled={role?.is_system}
                                    />
                                    <FieldDescription>
                                        Used by middleware and code. Lowercase
                                        letters, numbers, and underscores only.
                                        {role?.is_system && (
                                            <>
                                                {' '}
                                                System role keys are locked to
                                                keep permission checks stable.
                                            </>
                                        )}
                                    </FieldDescription>
                                    <FieldError>
                                        {form.error('name')}
                                    </FieldError>
                                </Field>

                                <Field
                                    data-invalid={
                                        form.invalid('status') || undefined
                                    }
                                >
                                    <FieldSet>
                                        <FieldLegend>
                                            Status{' '}
                                            <span className="text-destructive">
                                                *
                                            </span>
                                        </FieldLegend>
                                        <FieldDescription>
                                            Choose whether this role should be
                                            assignable in the application.
                                        </FieldDescription>
                                        <ToggleGroup
                                            type="single"
                                            value={form.data.status}
                                            onValueChange={(value) => {
                                                if (value === '') return;
                                                form.setField(
                                                    'status',
                                                    value as RoleFormValues['status'],
                                                );
                                            }}
                                            aria-invalid={
                                                form.invalid('status') ||
                                                undefined
                                            }
                                            variant="outline"
                                        >
                                            {statusOptions.map((option) => (
                                                <ToggleGroupItem
                                                    key={option.value}
                                                    value={option.value}
                                                    className="min-w-[8rem]"
                                                >
                                                    {option.label}
                                                </ToggleGroupItem>
                                            ))}
                                        </ToggleGroup>
                                    </FieldSet>
                                    <FieldError>
                                        {form.error('status')}
                                    </FieldError>
                                </Field>
                            </FieldGroup>
                        </CardContent>
                    </Card>

                    {/* Permissions */}
                    <Card>
                        <CardHeader>
                            <div className="flex items-start justify-between gap-3">
                                <div>
                                    <CardTitle>Permissions</CardTitle>
                                    <CardDescription>
                                        Select the capabilities this role should
                                        unlock across the application.
                                    </CardDescription>
                                </div>

                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    className="shrink-0"
                                    onClick={
                                        allCollapsed ? expandAll : collapseAll
                                    }
                                >
                                    {allCollapsed ? (
                                        <>
                                            <ChevronsUpDownIcon data-icon="inline-start" />
                                            Expand All
                                        </>
                                    ) : (
                                        <>
                                            <ChevronsDownUpIcon data-icon="inline-start" />
                                            Collapse All
                                        </>
                                    )}
                                </Button>
                            </div>

                            {/* Search */}
                            <InputGroup className="mt-1">
                                <InputGroupAddon>
                                    <SearchIcon />
                                </InputGroupAddon>
                                <InputGroupInput
                                    placeholder="Search permissions..."
                                    value={searchQuery}
                                    onChange={(e) =>
                                        setSearchQuery(e.target.value)
                                    }
                                />
                            </InputGroup>
                        </CardHeader>

                        <CardContent>
                            {permissionGroups.length === 0 ? (
                                <div className="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground">
                                    No permissions are available yet.
                                </div>
                            ) : filteredGroups.length === 0 ? (
                                <div className="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground">
                                    No permissions match &ldquo;{searchQuery}
                                    &rdquo;.
                                </div>
                            ) : (
                                <div className="flex flex-col gap-1">
                                    {filteredGroups.map((group, index) => {
                                        const isCollapsed = collapsedGroups.has(
                                            group.group,
                                        );
                                        const groupIds = group.permissions.map(
                                            (p) => p.id,
                                        );
                                        const checkedCount = groupIds.filter(
                                            (id) =>
                                                form.data.permissions.includes(
                                                    id,
                                                ),
                                        ).length;
                                        const allChecked =
                                            checkedCount === groupIds.length;
                                        const someChecked =
                                            checkedCount > 0 && !allChecked;

                                        return (
                                            <div key={group.group}>
                                                {index > 0 && (
                                                    <Separator className="mb-1" />
                                                )}

                                                {/* Group header */}
                                                <div className="flex items-center gap-2 py-2">
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            toggleGroupCollapse(
                                                                group.group,
                                                            )
                                                        }
                                                        className="text-muted-foreground transition-colors hover:text-foreground"
                                                        aria-label={
                                                            isCollapsed
                                                                ? 'Expand group'
                                                                : 'Collapse group'
                                                        }
                                                    >
                                                        {isCollapsed ? (
                                                            <ChevronRightIcon className="size-4" />
                                                        ) : (
                                                            <ChevronDownIcon className="size-4" />
                                                        )}
                                                    </button>

                                                    <Checkbox
                                                        checked={
                                                            someChecked
                                                                ? 'indeterminate'
                                                                : allChecked
                                                        }
                                                        onCheckedChange={() =>
                                                            toggleGroupPermissions(
                                                                group,
                                                            )
                                                        }
                                                        aria-label={`Select all in ${group.label}`}
                                                    />

                                                    <button
                                                        type="button"
                                                        className="flex-1 text-left text-sm font-medium text-foreground"
                                                        onClick={() =>
                                                            toggleGroupCollapse(
                                                                group.group,
                                                            )
                                                        }
                                                    >
                                                        {group.label}
                                                    </button>

                                                    <span className="text-xs text-muted-foreground">
                                                        {checkedCount}/
                                                        {groupIds.length}
                                                    </span>
                                                </div>

                                                {/* Permissions grid */}
                                                {!isCollapsed && (
                                                    <div className="mb-2 grid gap-2 pl-10 md:grid-cols-2">
                                                        {group.permissions.map(
                                                            (permission) => {
                                                                const checked =
                                                                    form.data.permissions.includes(
                                                                        permission.id,
                                                                    );

                                                                return (
                                                                    <label
                                                                        key={
                                                                            permission.id
                                                                        }
                                                                        className="flex cursor-pointer items-center gap-3 rounded-lg border px-3 py-2.5 transition-colors hover:bg-muted/40"
                                                                    >
                                                                        <Checkbox
                                                                            checked={
                                                                                checked
                                                                            }
                                                                            onCheckedChange={(
                                                                                val,
                                                                            ) =>
                                                                                togglePermission(
                                                                                    permission.id,
                                                                                    val ===
                                                                                        true,
                                                                                )
                                                                            }
                                                                        />
                                                                        <div className="min-w-0 flex-1">
                                                                            <div className="flex items-center gap-1.5 text-sm font-medium text-foreground">
                                                                                <ShieldCheckIcon className="size-3.5 shrink-0 text-muted-foreground" />
                                                                                {
                                                                                    permission.display_name
                                                                                }
                                                                            </div>
                                                                            <div className="mt-0.5 text-xs text-muted-foreground">
                                                                                {
                                                                                    permission.name
                                                                                }
                                                                            </div>
                                                                        </div>
                                                                    </label>
                                                                );
                                                            },
                                                        )}
                                                    </div>
                                                )}
                                            </div>
                                        );
                                    })}
                                </div>
                            )}

                            <FieldError>
                                {form.errors.permissions ??
                                    form.errors['permissions.0']}
                            </FieldError>
                        </CardContent>
                    </Card>
                </div>

                {/* ── Sidebar ── */}
                <div className="flex flex-col gap-6">
                    {/* Role Statistics (edit only) */}
                    {role && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Role Statistics</CardTitle>
                            </CardHeader>
                            <CardContent className="grid grid-cols-2 gap-4">
                                <div className="flex flex-col items-center gap-1">
                                    <span className="text-2xl font-bold text-foreground">
                                        {role.users_count}
                                    </span>
                                    <span className="text-xs text-muted-foreground">
                                        Users
                                    </span>
                                </div>
                                <div className="flex flex-col items-center gap-1">
                                    <span className="text-2xl font-bold text-foreground">
                                        {selectedCount}
                                    </span>
                                    <span className="text-xs text-muted-foreground">
                                        Permissions
                                    </span>
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {/* Permission Summary */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Permission Summary</CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-3">
                            <div className="flex items-center justify-between text-sm">
                                <span className="text-muted-foreground">
                                    Selected Permissions:
                                </span>
                                <span className="font-medium">
                                    {selectedCount}
                                </span>
                            </div>
                            <div className="flex items-center justify-between text-sm">
                                <span className="text-muted-foreground">
                                    Total Permissions:
                                </span>
                                <span className="font-medium">
                                    {totalPermissions}
                                </span>
                            </div>
                            <Progress value={selectedPercent} className="h-2" />
                            <p className="text-center text-xs text-muted-foreground">
                                {selectedPercent}% selected
                            </p>
                        </CardContent>
                    </Card>

                    {/* Save Actions */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Save Actions</CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-2">
                            <Button
                                type="submit"
                                className="w-full"
                                disabled={form.processing}
                            >
                                {form.processing ? (
                                    <Spinner />
                                ) : (
                                    <SaveIcon data-icon="inline-start" />
                                )}
                                {form.processing ? 'Saving...' : submitLabel}
                            </Button>

                            <Button
                                type="button"
                                variant="outline"
                                className="w-full"
                                asChild
                            >
                                <Link href={route('app.roles.index')}>
                                    ← Cancel
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </form>
    );
}

function deriveRoleName(value: string): string {
    return value
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '_')
        .replace(/^_+|_+$/g, '')
        .replace(/_{2,}/g, '_');
}
