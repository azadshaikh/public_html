import { Link, useForm } from '@inertiajs/react';
import {
    ArrowLeftIcon,
    LockKeyholeIcon,
    SaveIcon,
    UserCogIcon,
} from 'lucide-react';
import type { FormEvent } from 'react';
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
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import type {
    ManagedUserEditingTarget,
    ManagedUserFormValues,
    ManagedUserRoleOption,
} from '@/types/user-management';

type ManagedUserFormProps = {
    mode: 'create' | 'edit';
    user?: ManagedUserEditingTarget;
    initialValues: ManagedUserFormValues;
    availableRoles: ManagedUserRoleOption[];
};

export default function ManagedUserForm({
    mode,
    user,
    initialValues,
    availableRoles,
}: ManagedUserFormProps) {
    const form = useForm<ManagedUserFormValues>(initialValues);
    const submitMethod = mode === 'create' ? 'post' : 'put';
    const submitUrl =
        mode === 'create'
            ? route('app.users.store')
            : route('app.users.update', user!.id);
    const submitLabel = mode === 'create' ? 'Create user' : 'Save user';

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit(submitMethod, submitUrl, {
            preserveScroll: true,
        });
    };

    const toggleRole = (roleId: number, checked: boolean) => {
        if (checked) {
            if (form.data.roles.includes(roleId)) {
                return;
            }

            form.setData('roles', [...form.data.roles, roleId]);

            return;
        }

        form.setData(
            'roles',
            form.data.roles.filter(
                (currentRoleId: number) => currentRoleId !== roleId,
            ),
        );
    };

    return (
        <form className="flex flex-col gap-6" onSubmit={handleSubmit}>
            <div className="grid gap-6 xl:grid-cols-[minmax(0,1.05fr)_minmax(0,1fr)]">
                <Card>
                    <CardHeader>
                        <div className="flex items-center gap-2">
                            <CardTitle>
                                {mode === 'create'
                                    ? 'Create user'
                                    : 'Edit user'}
                            </CardTitle>
                            {user ? (
                                <Badge
                                    variant={
                                        user.active ? 'secondary' : 'outline'
                                    }
                                >
                                    {user.active ? 'Active' : 'Inactive'}
                                </Badge>
                            ) : null}
                        </div>
                        <CardDescription>
                            {mode === 'create'
                                ? 'Add a managed account with its initial roles and sign-in credentials.'
                                : 'Update core account details and the role bundle used for access control.'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-6">
                        <FieldGroup>
                            <Field data-invalid={Boolean(form.errors.name)}>
                                <FieldLabel htmlFor="name">Name</FieldLabel>
                                <Input
                                    id="name"
                                    value={form.data.name}
                                    onChange={(event) =>
                                        form.setData('name', event.target.value)
                                    }
                                    aria-invalid={
                                        Boolean(form.errors.name) || undefined
                                    }
                                    placeholder="Full name"
                                />
                                <FieldError>{form.errors.name}</FieldError>
                            </Field>

                            <Field data-invalid={Boolean(form.errors.email)}>
                                <FieldLabel htmlFor="email">Email</FieldLabel>
                                <Input
                                    id="email"
                                    type="email"
                                    value={form.data.email}
                                    onChange={(event) =>
                                        form.setData(
                                            'email',
                                            event.target.value,
                                        )
                                    }
                                    aria-invalid={
                                        Boolean(form.errors.email) || undefined
                                    }
                                    placeholder="user@example.com"
                                />
                                <FieldDescription>
                                    Changing the email resets verification so
                                    the new address can be confirmed safely.
                                </FieldDescription>
                                <FieldError>{form.errors.email}</FieldError>
                            </Field>
                        </FieldGroup>

                        <Field data-invalid={Boolean(form.errors.active)}>
                            <div className="flex items-start justify-between gap-4 rounded-xl border p-4">
                                <div>
                                    <FieldLabel htmlFor="active">
                                        Account status
                                    </FieldLabel>
                                    <FieldDescription>
                                        Inactive users stay in the system but
                                        can be clearly identified for follow-up
                                        actions.
                                    </FieldDescription>
                                </div>
                                <Switch
                                    id="active"
                                    checked={form.data.active}
                                    onCheckedChange={(checked) =>
                                        form.setData('active', checked === true)
                                    }
                                />
                            </div>
                            <FieldError>{form.errors.active}</FieldError>
                        </Field>

                        <FieldGroup>
                            <Field data-invalid={Boolean(form.errors.password)}>
                                <FieldLabel htmlFor="password">
                                    {mode === 'create'
                                        ? 'Password'
                                        : 'New password'}
                                </FieldLabel>
                                <Input
                                    id="password"
                                    type="password"
                                    value={form.data.password}
                                    onChange={(event) =>
                                        form.setData(
                                            'password',
                                            event.target.value,
                                        )
                                    }
                                    aria-invalid={
                                        Boolean(form.errors.password) ||
                                        undefined
                                    }
                                    autoComplete="new-password"
                                    placeholder={
                                        mode === 'create'
                                            ? 'Create a secure password'
                                            : 'Leave blank to keep the current password'
                                    }
                                />
                                <FieldDescription>
                                    {mode === 'create'
                                        ? 'Set the initial sign-in password for this account.'
                                        : 'Leave this empty if the current password should stay unchanged.'}
                                </FieldDescription>
                                <FieldError>{form.errors.password}</FieldError>
                            </Field>

                            <Field
                                data-invalid={Boolean(
                                    form.errors.password_confirmation,
                                )}
                            >
                                <FieldLabel htmlFor="password_confirmation">
                                    Confirm password
                                </FieldLabel>
                                <Input
                                    id="password_confirmation"
                                    type="password"
                                    value={form.data.password_confirmation}
                                    onChange={(event) =>
                                        form.setData(
                                            'password_confirmation',
                                            event.target.value,
                                        )
                                    }
                                    aria-invalid={
                                        Boolean(
                                            form.errors.password_confirmation,
                                        ) || undefined
                                    }
                                    autoComplete="new-password"
                                    placeholder="Repeat the password"
                                />
                                <FieldDescription>
                                    Required whenever a new password is being
                                    set.
                                </FieldDescription>
                                <FieldError>
                                    {form.errors.password_confirmation}
                                </FieldError>
                            </Field>
                        </FieldGroup>

                        {user ? (
                            <div className="rounded-xl border border-dashed bg-muted/20 p-4 text-sm text-muted-foreground">
                                <div className="flex items-center gap-2 font-medium text-foreground">
                                    <UserCogIcon className="size-4" />
                                    Account summary
                                </div>
                                <div className="mt-3 grid gap-2 sm:grid-cols-2">
                                    <div>
                                        <span className="text-xs tracking-[0.14em] uppercase">
                                            Verified email
                                        </span>
                                        <div className="mt-1 text-sm font-medium text-foreground">
                                            {user.email_verified_at
                                                ? 'Yes'
                                                : 'No'}
                                        </div>
                                    </div>
                                    <div>
                                        <span className="text-xs tracking-[0.14em] uppercase">
                                            Assigned roles
                                        </span>
                                        <div className="mt-1 text-sm font-medium text-foreground">
                                            {form.data.roles.length}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        ) : (
                            <div className="rounded-xl border border-dashed bg-muted/20 p-4 text-sm text-muted-foreground">
                                <div className="flex items-center gap-2 font-medium text-foreground">
                                    <LockKeyholeIcon className="size-4" />
                                    Provisioning note
                                </div>
                                <p className="mt-3">
                                    New accounts start with an unverified email.
                                    Verification is triggered again
                                    automatically if the address is changed
                                    later.
                                </p>
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Role assignments</CardTitle>
                        <CardDescription>
                            Assign one or more roles. Permissions continue to
                            flow through role bundles.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-3">
                        {availableRoles.map((roleOption) => {
                            const checked = form.data.roles.includes(
                                roleOption.id,
                            );

                            return (
                                <label
                                    key={roleOption.id}
                                    className="flex gap-3 rounded-xl border p-4 transition-colors hover:bg-muted/30"
                                >
                                    <Checkbox
                                        checked={checked}
                                        onCheckedChange={(value) =>
                                            toggleRole(
                                                roleOption.id,
                                                value === true,
                                            )
                                        }
                                        className="mt-0.5"
                                    />
                                    <div className="min-w-0 flex-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="font-medium text-foreground">
                                                {roleOption.display_name}
                                            </span>
                                            {roleOption.is_system ? (
                                                <Badge variant="secondary">
                                                    System
                                                </Badge>
                                            ) : null}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {roleOption.name}
                                        </div>
                                    </div>
                                </label>
                            );
                        })}

                        <FieldError>
                            {form.errors.roles ?? form.errors['roles.0']}
                        </FieldError>

                        {form.data.roles.length === 0 ? (
                            <div className="rounded-xl border border-dashed p-4 text-sm text-destructive">
                                At least one role must remain assigned to this
                                user.
                            </div>
                        ) : null}
                    </CardContent>
                </Card>
            </div>

            <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border bg-card px-4 py-3">
                <Button asChild variant="outline">
                    <Link href={route('app.users.index')}>
                        <ArrowLeftIcon data-icon="inline-start" />
                        Back to users
                    </Link>
                </Button>

                <Button type="submit" disabled={form.processing}>
                    <SaveIcon data-icon="inline-start" />
                    {submitLabel}
                </Button>
            </div>
        </form>
    );
}
