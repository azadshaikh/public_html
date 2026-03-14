import { Link } from '@inertiajs/react';
import {
    ArrowLeftIcon,
    LockKeyholeIcon,
    SaveIcon,
    UserCogIcon,
} from 'lucide-react';
import type { FormEvent } from 'react';
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
import { Spinner } from '@/components/ui/spinner';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useAppForm } from '@/hooks/use-app-form';
import { formValidators } from '@/lib/forms';
import type {
    ManagedUserEditingTarget,
    ManagedUserFormValues,
    ManagedUserRoleOption,
    ManagedUserStatusOption,
} from '@/types/user-management';

type ManagedUserFormProps = {
    mode: 'create' | 'edit';
    user?: ManagedUserEditingTarget;
    initialValues: ManagedUserFormValues;
    availableRoles: ManagedUserRoleOption[];
    statusOptions: ManagedUserStatusOption[];
};

function resolveStatusLabel(
    status: ManagedUserFormValues['status'],
    options: ManagedUserStatusOption[],
): string {
    return options.find((option) => option.value === status)?.label ?? status;
}

export default function ManagedUserForm({
    mode,
    user,
    initialValues,
    availableRoles,
    statusOptions,
}: ManagedUserFormProps) {
    const form = useAppForm<ManagedUserFormValues>({
        defaults: initialValues,
        rememberKey:
            mode === 'create' ? 'users.create.form' : `users.edit.${user?.id}`,
        dirtyGuard: { enabled: true },
        rules: {
            name: [formValidators.required('Name')],
            email: [
                formValidators.required('Email address'),
                formValidators.email(),
            ],
            status: [formValidators.required('Status')],
            roles: [
                (value) =>
                    Array.isArray(value) && value.length > 0
                        ? undefined
                        : 'Select at least one role.',
            ],
            password:
                mode === 'create'
                    ? [
                          formValidators.required('Password'),
                          formValidators.minLength('Password', 8),
                      ]
                    : [
                          (value) =>
                              typeof value === 'string' && value.length > 0
                                  ? formValidators.minLength<
                                        ManagedUserFormValues,
                                        'password'
                                    >('Password', 8)(value)
                                  : undefined,
                      ],
            password_confirmation: [
                (value, data) => {
                    const password = data.password.trim();
                    const confirmation =
                        typeof value === 'string' ? value.trim() : '';

                    if (password === '') {
                        return undefined;
                    }

                    if (confirmation === '') {
                        return 'Password confirmation is required.';
                    }

                    return password === confirmation
                        ? undefined
                        : 'Password confirmation does not match.';
                },
            ],
        },
    });
    const submitMethod = mode === 'create' ? 'post' : 'put';
    const submitUrl =
        mode === 'create'
            ? route('app.users.store')
            : route('app.users.update', user!.id);
    const submitLabel = mode === 'create' ? 'Create user' : 'Save user';
    const activeRoleCount = form.data.roles.length;
    const selectedStatusLabel = resolveStatusLabel(form.data.status, statusOptions);

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit(submitMethod, submitUrl, {
            preserveScroll: true,
            successToast: {
                title: mode === 'create' ? 'User created' : 'User updated',
                description:
                    mode === 'create'
                        ? 'The managed account has been created successfully.'
                        : 'The managed account has been updated successfully.',
            },
            setDefaultsOnSuccess: mode === 'edit',
        });
    };

    const toggleRole = (roleId: number, checked: boolean) => {
        if (checked) {
            if (form.data.roles.includes(roleId)) {
                return;
            }

            form.setField('roles', [...form.data.roles, roleId]);

            return;
        }

        form.setField(
            'roles',
            form.data.roles.filter(
                (currentRoleId: number) => currentRoleId !== roleId,
            ),
        );
    };

    return (
        <form noValidate className="flex flex-col gap-6" onSubmit={handleSubmit}>
            {form.dirtyGuardDialog}
            <FormErrorSummary errors={form.errors} minMessages={2} />

            <div className="grid gap-6 xl:grid-cols-[minmax(0,1.05fr)_minmax(0,1fr)]">
                <Card>
                    <CardHeader>
                        <div className="flex items-center gap-2">
                            <CardTitle>
                                {mode === 'create' ? 'Create user' : 'Edit user'}
                            </CardTitle>
                            {user ? (
                                <Badge
                                    variant={
                                        form.data.status === 'active'
                                            ? 'secondary'
                                            : 'outline'
                                    }
                                >
                                    {selectedStatusLabel}
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
                            <Field data-invalid={form.invalid('name') || undefined}>
                                <FieldLabel htmlFor="name">Name</FieldLabel>
                                <Input
                                    id="name"
                                    value={form.data.name}
                                    onChange={(event) =>
                                        form.setField('name', event.target.value)
                                    }
                                    onBlur={() => form.touch('name')}
                                    aria-invalid={form.invalid('name') || undefined}
                                    placeholder="Full name"
                                />
                                <FieldError>{form.error('name')}</FieldError>
                            </Field>

                            <Field data-invalid={form.invalid('email') || undefined}>
                                <FieldLabel htmlFor="email">Email</FieldLabel>
                                <Input
                                    id="email"
                                    type="email"
                                    value={form.data.email}
                                    onChange={(event) =>
                                        form.setField('email', event.target.value)
                                    }
                                    onBlur={() => form.touch('email')}
                                    aria-invalid={form.invalid('email') || undefined}
                                    placeholder="user@example.com"
                                />
                                <FieldDescription>
                                    Changing the email resets verification so the
                                    new address can be confirmed safely.
                                </FieldDescription>
                                <FieldError>{form.error('email')}</FieldError>
                            </Field>
                        </FieldGroup>

                        <Field data-invalid={form.invalid('status') || undefined}>
                            <FieldSet>
                                <FieldLegend>Status</FieldLegend>
                                <FieldDescription>
                                    Choose the current access state for this managed
                                    account.
                                </FieldDescription>
                                <ToggleGroup
                                    type="single"
                                    value={form.data.status}
                                    onValueChange={(value) => {
                                        if (value === '') {
                                            return;
                                        }

                                        form.setField(
                                            'status',
                                            value as ManagedUserFormValues['status'],
                                        );
                                    }}
                                    aria-invalid={form.invalid('status') || undefined}
                                    className="w-full flex-wrap"
                                    variant="outline"
                                >
                                    {statusOptions.map((option) => (
                                        <ToggleGroupItem
                                            key={option.value}
                                            value={option.value}
                                            className="min-w-[9rem] flex-1"
                                        >
                                            {option.label}
                                        </ToggleGroupItem>
                                    ))}
                                </ToggleGroup>
                            </FieldSet>
                            <FieldError>{form.error('status')}</FieldError>
                        </Field>

                        <FieldGroup>
                            <Field
                                data-invalid={form.invalid('password') || undefined}
                            >
                                <FieldLabel htmlFor="password">
                                    {mode === 'create' ? 'Password' : 'New password'}
                                </FieldLabel>
                                <Input
                                    id="password"
                                    type="password"
                                    value={form.data.password}
                                    onChange={(event) =>
                                        form.setField('password', event.target.value)
                                    }
                                    onBlur={() => form.touch('password')}
                                    aria-invalid={
                                        form.invalid('password') || undefined
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
                                <FieldError>{form.error('password')}</FieldError>
                            </Field>

                            <Field
                                data-invalid={
                                    form.invalid('password_confirmation') ||
                                    undefined
                                }
                            >
                                <FieldLabel htmlFor="password_confirmation">
                                    Confirm password
                                </FieldLabel>
                                <Input
                                    id="password_confirmation"
                                    type="password"
                                    value={form.data.password_confirmation}
                                    onChange={(event) =>
                                        form.setField(
                                            'password_confirmation',
                                            event.target.value,
                                        )
                                    }
                                    onBlur={() =>
                                        form.touch('password_confirmation')
                                    }
                                    aria-invalid={
                                        form.invalid('password_confirmation') ||
                                        undefined
                                    }
                                    autoComplete="new-password"
                                    placeholder="Repeat the password"
                                />
                                <FieldDescription>
                                    Required whenever a new password is being set.
                                </FieldDescription>
                                <FieldError>
                                    {form.error('password_confirmation')}
                                </FieldError>
                            </Field>
                        </FieldGroup>

                        {user ? (
                            <div className="rounded-xl border border-dashed bg-muted/20 p-4 text-sm text-muted-foreground">
                                <div className="flex items-center gap-2 font-medium text-foreground">
                                    <UserCogIcon />
                                    Account summary
                                </div>
                                <div className="mt-3 grid gap-2 sm:grid-cols-2">
                                    <div>
                                        <span className="text-xs tracking-[0.14em] uppercase">
                                            Verified email
                                        </span>
                                        <div className="mt-1 text-sm font-medium text-foreground">
                                            {user.email_verified_at ? 'Yes' : 'No'}
                                        </div>
                                    </div>
                                    <div>
                                        <span className="text-xs tracking-[0.14em] uppercase">
                                            Assigned roles
                                        </span>
                                        <div className="mt-1 text-sm font-medium text-foreground">
                                            {activeRoleCount}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        ) : (
                            <div className="rounded-xl border border-dashed bg-muted/20 p-4 text-sm text-muted-foreground">
                                <div className="flex items-center gap-2 font-medium text-foreground">
                                    <LockKeyholeIcon />
                                    Provisioning note
                                </div>
                                <p className="mt-3">
                                    New accounts start with an unverified email.
                                    Verification is triggered again automatically if
                                    the address is changed later.
                                </p>
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Role assignments</CardTitle>
                        <CardDescription>
                            Assign one or more roles. Permissions continue to flow
                            through role bundles.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Field data-invalid={form.invalid('roles') || undefined}>
                            <FieldSet>
                                <FieldLegend>Available roles</FieldLegend>
                                <FieldDescription>
                                    Select the roles that should be assigned to this
                                    account.
                                </FieldDescription>
                                <div className="grid gap-3">
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
                                                    aria-invalid={
                                                        form.invalid('roles') ||
                                                        undefined
                                                    }
                                                />
                                                <div className="min-w-0 flex-1">
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <span className="font-medium text-foreground">
                                                            {
                                                                roleOption.display_name
                                                            }
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
                                </div>
                            </FieldSet>
                            <FieldError>{form.error('roles')}</FieldError>
                        </Field>
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
                    {form.processing ? (
                        <Spinner />
                    ) : (
                        <SaveIcon data-icon="inline-start" />
                    )}
                    {form.processing ? 'Saving...' : submitLabel}
                </Button>
            </div>
        </form>
    );
}
