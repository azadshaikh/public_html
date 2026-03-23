import type { FormEvent } from 'react';
import { FormErrorSummary } from '@/components/forms/form-error-summary';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Field,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useAppForm } from '@/hooks/use-app-form';
import { formValidators } from '@/lib/forms';
import type {
    DepartmentFormValues,
    DepartmentShowDetail,
    HelpdeskOption,
} from '../../types/helpdesk';

type DepartmentFormProps = {
    mode: 'create' | 'edit';
    department?: DepartmentShowDetail | { id: number; name: string };
    initialValues: DepartmentFormValues;
    headOptions: HelpdeskOption[];
    visibilityOptions: HelpdeskOption[];
    statusOptions: HelpdeskOption[];
};

export default function DepartmentForm({
    mode,
    department,
    initialValues,
    headOptions,
    visibilityOptions,
    statusOptions,
}: DepartmentFormProps) {
    const form = useAppForm<DepartmentFormValues>({
        defaults: initialValues,
        rememberKey:
            mode === 'create'
                ? 'helpdesk.departments.create'
                : `helpdesk.departments.edit.${department?.id}`,
        dirtyGuard: { enabled: true },
        rules: {
            name: [formValidators.required('Name')],
            status: [formValidators.required('Status')],
        },
    });

    const submitMethod = mode === 'create' ? 'post' : 'put';
    const submitUrl =
        mode === 'create'
            ? route('helpdesk.departments.store')
            : route('helpdesk.departments.update', department!.id);
    const submitLabel =
        mode === 'create' ? 'Create Department' : 'Update Department';

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit(submitMethod, submitUrl, {
            preserveScroll: true,
            setDefaultsOnSuccess: mode === 'edit',
            successToast: {
                title:
                    mode === 'create'
                        ? 'Department created'
                        : 'Department updated',
                description:
                    mode === 'create'
                        ? 'The department has been created successfully.'
                        : 'The department has been updated successfully.',
            },
        });
    };

    return (
        <form
            className="flex flex-col gap-6"
            onSubmit={handleSubmit}
            noValidate
        >
            {form.dirtyGuardDialog}
            <FormErrorSummary errors={form.errors} minMessages={2} />

            <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_300px]">
                {/* Main content */}
                <div className="flex flex-col gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Department Details</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <FieldGroup>
                                <Field
                                    data-invalid={
                                        form.invalid('name') || undefined
                                    }
                                >
                                    <FieldLabel htmlFor="name">
                                        Name{' '}
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
                                                e.target.value,
                                            )
                                        }
                                        onBlur={() => form.touch('name')}
                                        aria-invalid={
                                            form.invalid('name') || undefined
                                        }
                                        placeholder="Enter department name…"
                                    />
                                    <FieldError>
                                        {form.error('name')}
                                    </FieldError>
                                </Field>

                                <Field
                                    data-invalid={
                                        form.invalid('description') || undefined
                                    }
                                >
                                    <FieldLabel htmlFor="description">
                                        Description
                                    </FieldLabel>
                                    <Textarea
                                        id="description"
                                        rows={4}
                                        value={form.data.description}
                                        onChange={(e) =>
                                            form.setField(
                                                'description',
                                                e.target.value,
                                            )
                                        }
                                        onBlur={() => form.touch('description')}
                                        aria-invalid={
                                            form.invalid('description') ||
                                            undefined
                                        }
                                        placeholder="Describe this department…"
                                    />
                                    <FieldError>
                                        {form.error('description')}
                                    </FieldError>
                                </Field>

                                <Field
                                    data-invalid={
                                        form.invalid('department_head') ||
                                        undefined
                                    }
                                >
                                    <FieldLabel htmlFor="department_head">
                                        Department Head
                                    </FieldLabel>
                                    <NativeSelect
                                        id="department_head"
                                        value={form.data.department_head}
                                        onChange={(e) =>
                                            form.setField(
                                                'department_head',
                                                e.target.value,
                                            )
                                        }
                                        aria-invalid={
                                            form.invalid('department_head') ||
                                            undefined
                                        }
                                    >
                                        <NativeSelectOption value="">
                                            Select head…
                                        </NativeSelectOption>
                                        {headOptions.map((option) => (
                                            <NativeSelectOption
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </NativeSelectOption>
                                        ))}
                                    </NativeSelect>
                                    <FieldError>
                                        {form.error('department_head')}
                                    </FieldError>
                                </Field>
                            </FieldGroup>
                        </CardContent>
                    </Card>
                </div>

                {/* Sidebar */}
                <div className="flex flex-col gap-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Settings</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <FieldGroup>
                                <Field
                                    data-invalid={
                                        form.invalid('visibility') || undefined
                                    }
                                >
                                    <FieldLabel htmlFor="visibility">
                                        Visibility
                                    </FieldLabel>
                                    <NativeSelect
                                        id="visibility"
                                        value={form.data.visibility}
                                        onChange={(e) =>
                                            form.setField(
                                                'visibility',
                                                e.target.value,
                                            )
                                        }
                                        aria-invalid={
                                            form.invalid('visibility') ||
                                            undefined
                                        }
                                    >
                                        {visibilityOptions.map((option) => (
                                            <NativeSelectOption
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </NativeSelectOption>
                                        ))}
                                    </NativeSelect>
                                    <FieldError>
                                        {form.error('visibility')}
                                    </FieldError>
                                </Field>

                                <Field
                                    data-invalid={
                                        form.invalid('status') || undefined
                                    }
                                >
                                    <FieldLabel htmlFor="status">
                                        Status{' '}
                                        <span className="text-destructive">
                                            *
                                        </span>
                                    </FieldLabel>
                                    <NativeSelect
                                        id="status"
                                        value={form.data.status}
                                        onChange={(e) =>
                                            form.setField(
                                                'status',
                                                e.target.value,
                                            )
                                        }
                                        aria-invalid={
                                            form.invalid('status') || undefined
                                        }
                                    >
                                        {statusOptions.map((option) => (
                                            <NativeSelectOption
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </NativeSelectOption>
                                        ))}
                                    </NativeSelect>
                                    <FieldError>
                                        {form.error('status')}
                                    </FieldError>
                                </Field>
                            </FieldGroup>
                        </CardContent>
                    </Card>

                    {/* Submit */}
                    <Button
                        type="submit"
                        className="w-full"
                        disabled={form.processing}
                    >
                        {form.processing ? (
                            <Spinner className="mr-2" />
                        ) : null}
                        {submitLabel}
                    </Button>
                </div>
            </div>
        </form>
    );
}
