import { Link, useForm } from '@inertiajs/react';
import { ArrowLeftIcon, SaveIcon, ShieldCheckIcon } from 'lucide-react';
import type { FormEvent } from 'react';
import RoleController from '@/actions/App/Http/Controllers/RoleController';
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
import { Textarea } from '@/components/ui/textarea';
import type {
  PermissionGroup,
  RoleEditingTarget,
  RoleFormValues,
} from '@/types/role';

type RoleFormProps = {
  mode: 'create' | 'edit';
  role?: RoleEditingTarget;
  initialValues: RoleFormValues;
  permissionGroups: PermissionGroup[];
};

export default function RoleForm({
  mode,
  role,
  initialValues,
  permissionGroups,
}: RoleFormProps) {
  const form = useForm<RoleFormValues>(initialValues);
  const submitAction = role
    ? RoleController.update(role.id)
    : RoleController.store();
  const submitLabel = mode === 'create' ? 'Create role' : 'Save changes';

  const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    form.submit(submitAction, {
      preserveScroll: true,
    });
  };

  const handleDisplayNameChange = (value: string) => {
    const derivedCurrentName = deriveRoleName(form.data.display_name);
    const canAutoUpdateName =
      !role?.is_system &&
      (form.data.name === '' || form.data.name === derivedCurrentName);

    form.setData('display_name', value);

    if (canAutoUpdateName) {
      form.setData('name', deriveRoleName(value));
    }
  };

  const togglePermission = (permissionId: number, checked: boolean) => {
    if (checked) {
      if (form.data.permissions.includes(permissionId)) {
        return;
      }

      form.setData('permissions', [...form.data.permissions, permissionId]);

      return;
    }

    form.setData(
      'permissions',
      form.data.permissions.filter(
        (currentId: number) => currentId !== permissionId,
      ),
    );
  };

  return (
    <form className="flex flex-col gap-6" onSubmit={handleSubmit}>
      <div className="grid gap-6 xl:grid-cols-[minmax(0,1.15fr)_minmax(0,1fr)]">
        <Card>
          <CardHeader>
            <div className="flex items-center gap-2">
              <CardTitle>
                {mode === 'create' ? 'Create role' : 'Edit role'}
              </CardTitle>
              {role?.is_system ? (
                <Badge variant="secondary">System</Badge>
              ) : null}
            </div>
            <CardDescription>
              Define a role label, machine name, and the permission bundle it
              should grant.
            </CardDescription>
          </CardHeader>
          <CardContent className="grid gap-6">
            <FieldGroup>
              <Field data-invalid={Boolean(form.errors.display_name)}>
                <FieldLabel htmlFor="display_name">Display name</FieldLabel>
                <Input
                  id="display_name"
                  value={form.data.display_name}
                  onChange={(event) =>
                    handleDisplayNameChange(event.target.value)
                  }
                  aria-invalid={Boolean(form.errors.display_name) || undefined}
                  placeholder="Content Manager"
                />
                <FieldDescription>
                  This is the human-friendly label shown in the admin UI.
                </FieldDescription>
                <FieldError>{form.errors.display_name}</FieldError>
              </Field>

              <Field data-invalid={Boolean(form.errors.name)}>
                <FieldLabel htmlFor="name">Role key</FieldLabel>
                <Input
                  id="name"
                  value={form.data.name}
                  onChange={(event) =>
                    form.setData('name', deriveRoleName(event.target.value))
                  }
                  aria-invalid={Boolean(form.errors.name) || undefined}
                  placeholder="content_manager"
                  disabled={role?.is_system}
                />
                <FieldDescription>
                  Used by middleware and code. Lowercase letters, numbers, and
                  underscores only.
                </FieldDescription>
                {role?.is_system ? (
                  <FieldDescription>
                    System role keys are locked to keep permission checks
                    stable.
                  </FieldDescription>
                ) : null}
                <FieldError>{form.errors.name}</FieldError>
              </Field>
            </FieldGroup>

            <Field data-invalid={Boolean(form.errors.description)}>
              <FieldLabel htmlFor="description">Description</FieldLabel>
              <Textarea
                id="description"
                value={form.data.description}
                onChange={(event) =>
                  form.setData('description', event.target.value)
                }
                aria-invalid={Boolean(form.errors.description) || undefined}
                placeholder="Explain who should receive this role and how it should be used."
                rows={5}
              />
              <FieldDescription>
                Keep descriptions short and operational so future module work
                can reuse roles confidently.
              </FieldDescription>
              <FieldError>{form.errors.description}</FieldError>
            </Field>

            {role ? (
              <div className="rounded-xl border border-dashed bg-muted/20 p-4 text-sm text-muted-foreground">
                <div className="flex items-center gap-2 font-medium text-foreground">
                  <ShieldCheckIcon className="size-4" />
                  Role summary
                </div>
                <div className="mt-3 grid gap-2 sm:grid-cols-2">
                  <div>
                    <span className="text-xs tracking-[0.14em] uppercase">
                      Assigned users
                    </span>
                    <div className="mt-1 text-xl font-semibold text-foreground">
                      {role.users_count}
                    </div>
                  </div>
                  <div>
                    <span className="text-xs tracking-[0.14em] uppercase">
                      Permissions
                    </span>
                    <div className="mt-1 text-xl font-semibold text-foreground">
                      {form.data.permissions.length}
                    </div>
                  </div>
                </div>
              </div>
            ) : null}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Permission bundle</CardTitle>
            <CardDescription>
              Select the capabilities this role should unlock across the app.
            </CardDescription>
          </CardHeader>
          <CardContent className="grid gap-4">
            {permissionGroups.length === 0 ? (
              <div className="rounded-xl border border-dashed p-4 text-sm text-muted-foreground">
                No permissions are available yet.
              </div>
            ) : (
              permissionGroups.map((group) => (
                <div key={group.group} className="rounded-xl border p-4">
                  <div className="mb-3 flex items-center justify-between gap-2">
                    <div>
                      <h3 className="font-medium text-foreground">
                        {group.label}
                      </h3>
                      <p className="text-sm text-muted-foreground">
                        {group.permissions.length} permissions
                      </p>
                    </div>
                    <Badge variant="outline">{group.group}</Badge>
                  </div>

                  <div className="grid gap-3 md:grid-cols-2">
                    {group.permissions.map(
                      (permission: PermissionGroup['permissions'][number]) => {
                        const checked = form.data.permissions.includes(
                          permission.id,
                        );

                        return (
                          <label
                            key={permission.id}
                            className="flex gap-3 rounded-lg border p-3 transition-colors hover:bg-muted/30"
                          >
                            <Checkbox
                              checked={checked}
                              onCheckedChange={(value) =>
                                togglePermission(permission.id, value === true)
                              }
                              className="mt-0.5"
                            />
                            <div className="min-w-0 flex-1">
                              <div className="font-medium text-foreground">
                                {permission.display_name}
                              </div>
                              <div className="text-xs text-muted-foreground">
                                {permission.name}
                              </div>
                              {permission.description ? (
                                <p className="mt-1 text-sm text-muted-foreground">
                                  {permission.description}
                                </p>
                              ) : null}
                            </div>
                          </label>
                        );
                      },
                    )}
                  </div>
                </div>
              ))
            )}

            <FieldError>
              {form.errors.permissions ?? form.errors['permissions.0']}
            </FieldError>
          </CardContent>
        </Card>
      </div>

      <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border bg-card px-4 py-3">
        <Button asChild variant="outline">
          <Link href={RoleController.index()}>
            <ArrowLeftIcon data-icon="inline-start" />
            Back to roles
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

function deriveRoleName(value: string): string {
  return value
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '_')
    .replace(/^_+|_+$/g, '')
    .replace(/_{2,}/g, '_');
}
