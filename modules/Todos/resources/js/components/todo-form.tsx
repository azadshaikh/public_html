import { Link } from '@inertiajs/react';
import { CalendarIcon, SaveIcon, StarIcon, TagIcon } from 'lucide-react';
import type { FormEvent } from 'react';
import { FormErrorSummary } from '@/components/forms/form-error-summary';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
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
    TodoFormValues,
    TodoOption,
    TodoShowDetail,
} from '../types/todo';

type TodoFormProps = {
    mode: 'create' | 'edit';
    todo?: TodoShowDetail;
    initialValues: TodoFormValues;
    statusOptions: TodoOption[];
    priorityOptions: TodoOption[];
    visibilityOptions: TodoOption[];
    assigneeOptions: TodoOption[];
};

const emptyValues: TodoFormValues = {
    title: '',
    description: '',
    status: 'pending',
    priority: 'medium',
    visibility: 'private',
    start_date: '',
    due_date: '',
    is_starred: false,
    assigned_to: '',
    labels: '',
};

export default function TodoForm({
    mode,
    todo,
    initialValues,
    statusOptions,
    priorityOptions,
    visibilityOptions,
    assigneeOptions,
}: TodoFormProps) {
    const form = useAppForm<TodoFormValues>({
        defaults: initialValues || emptyValues,
        rememberKey:
            mode === 'create' ? 'todos.create.form' : `todos.edit.${todo?.id}`,
        dirtyGuard: { enabled: true },
        rules: {
            title: [formValidators.required('Title')],
            status: [formValidators.required('Status')],
            priority: [formValidators.required('Priority')],
        },
    });

    const submitMethod = mode === 'create' ? 'post' : 'put';
    const submitUrl =
        mode === 'create'
            ? route('app.todos.store')
            : route('app.todos.update', todo!.id);
    const submitLabel = mode === 'create' ? 'Create Todo' : 'Update Todo';

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit(submitMethod, submitUrl, {
            preserveScroll: true,
            setDefaultsOnSuccess: mode === 'edit',
            successToast: {
                title: mode === 'create' ? 'Todo created' : 'Todo updated',
                description:
                    mode === 'create'
                        ? 'The todo has been created successfully.'
                        : 'The todo has been updated successfully.',
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
                {/* ── Main content ──────────────────────────────────── */}
                <div className="flex flex-col gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Todo Details</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <FieldGroup>
                                {/* Title */}
                                <Field
                                    data-invalid={
                                        form.invalid('title') || undefined
                                    }
                                >
                                    <FieldLabel htmlFor="title">
                                        Title{' '}
                                        <span className="text-destructive">
                                            *
                                        </span>
                                    </FieldLabel>
                                    <Input
                                        id="title"
                                        value={form.data.title}
                                        onChange={(e) =>
                                            form.setField(
                                                'title',
                                                e.target.value,
                                            )
                                        }
                                        onBlur={() => form.touch('title')}
                                        aria-invalid={
                                            form.invalid('title') || undefined
                                        }
                                        placeholder="Enter todo title…"
                                    />
                                    <FieldError>{form.error('title')}</FieldError>
                                </Field>

                                {/* Description */}
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
                                        rows={5}
                                        value={form.data.description}
                                        onChange={(e) =>
                                            form.setField(
                                                'description',
                                                e.target.value,
                                            )
                                        }
                                        onBlur={() =>
                                            form.touch('description')
                                        }
                                        aria-invalid={
                                            form.invalid('description') ||
                                            undefined
                                        }
                                        placeholder="Describe this todo…"
                                    />
                                    <FieldError>
                                        {form.error('description')}
                                    </FieldError>
                                </Field>
                            </FieldGroup>
                        </CardContent>
                    </Card>

                    {/* ── Dates ──────────────────────────────────────── */}
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <CalendarIcon className="size-4 text-muted-foreground" />
                                <CardTitle>Dates</CardTitle>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <FieldGroup>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <Field
                                        data-invalid={
                                            form.invalid('start_date') ||
                                            undefined
                                        }
                                    >
                                        <FieldLabel htmlFor="start_date">
                                            Start Date
                                        </FieldLabel>
                                        <Input
                                            id="start_date"
                                            type="date"
                                            value={form.data.start_date}
                                            onChange={(e) =>
                                                form.setField(
                                                    'start_date',
                                                    e.target.value,
                                                )
                                            }
                                            onBlur={() =>
                                                form.touch('start_date')
                                            }
                                            aria-invalid={
                                                form.invalid('start_date') ||
                                                undefined
                                            }
                                        />
                                        <FieldError>
                                            {form.error('start_date')}
                                        </FieldError>
                                    </Field>

                                    <Field
                                        data-invalid={
                                            form.invalid('due_date') ||
                                            undefined
                                        }
                                    >
                                        <FieldLabel htmlFor="due_date">
                                            Due Date
                                        </FieldLabel>
                                        <Input
                                            id="due_date"
                                            type="date"
                                            value={form.data.due_date}
                                            onChange={(e) =>
                                                form.setField(
                                                    'due_date',
                                                    e.target.value,
                                                )
                                            }
                                            onBlur={() =>
                                                form.touch('due_date')
                                            }
                                            aria-invalid={
                                                form.invalid('due_date') ||
                                                undefined
                                            }
                                        />
                                        <FieldError>
                                            {form.error('due_date')}
                                        </FieldError>
                                    </Field>
                                </div>
                            </FieldGroup>
                        </CardContent>
                    </Card>

                    {/* ── Labels ─────────────────────────────────────── */}
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <TagIcon className="size-4 text-muted-foreground" />
                                <CardTitle>Labels</CardTitle>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <FieldGroup>
                                <Field
                                    data-invalid={
                                        form.invalid('labels') || undefined
                                    }
                                >
                                    <FieldLabel htmlFor="labels">
                                        Labels
                                    </FieldLabel>
                                    <Input
                                        id="labels"
                                        value={form.data.labels}
                                        onChange={(e) =>
                                            form.setField(
                                                'labels',
                                                e.target.value,
                                            )
                                        }
                                        onBlur={() => form.touch('labels')}
                                        aria-invalid={
                                            form.invalid('labels') || undefined
                                        }
                                        placeholder="bug, feature, urgent (comma-separated)"
                                    />
                                    <FieldError>
                                        {form.error('labels')}
                                    </FieldError>
                                </Field>
                            </FieldGroup>
                        </CardContent>
                    </Card>
                </div>

                {/* ── Right sidebar ─────────────────────────────────── */}
                <div className="flex flex-col gap-4">
                    {/* Status & Priority */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Status &amp; Priority</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <FieldGroup>
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

                                <Field
                                    data-invalid={
                                        form.invalid('priority') || undefined
                                    }
                                >
                                    <FieldLabel htmlFor="priority">
                                        Priority{' '}
                                        <span className="text-destructive">
                                            *
                                        </span>
                                    </FieldLabel>
                                    <NativeSelect
                                        id="priority"
                                        value={form.data.priority}
                                        onChange={(e) =>
                                            form.setField(
                                                'priority',
                                                e.target.value,
                                            )
                                        }
                                        aria-invalid={
                                            form.invalid('priority') ||
                                            undefined
                                        }
                                    >
                                        {priorityOptions.map((option) => (
                                            <NativeSelectOption
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </NativeSelectOption>
                                        ))}
                                    </NativeSelect>
                                    <FieldError>
                                        {form.error('priority')}
                                    </FieldError>
                                </Field>
                            </FieldGroup>
                        </CardContent>
                    </Card>

                    {/* Assignee & Visibility */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Assignment</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <FieldGroup>
                                <Field
                                    data-invalid={
                                        form.invalid('assigned_to') || undefined
                                    }
                                >
                                    <FieldLabel htmlFor="assigned_to">
                                        Assigned To
                                    </FieldLabel>
                                    <NativeSelect
                                        id="assigned_to"
                                        value={form.data.assigned_to}
                                        onChange={(e) =>
                                            form.setField(
                                                'assigned_to',
                                                e.target.value,
                                            )
                                        }
                                        aria-invalid={
                                            form.invalid('assigned_to') ||
                                            undefined
                                        }
                                    >
                                        {assigneeOptions.map((option) => (
                                            <NativeSelectOption
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </NativeSelectOption>
                                        ))}
                                    </NativeSelect>
                                    <FieldError>
                                        {form.error('assigned_to')}
                                    </FieldError>
                                </Field>

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
                            </FieldGroup>
                        </CardContent>
                    </Card>

                    {/* Starred */}
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <StarIcon className="size-4 text-muted-foreground" />
                                <CardTitle>Options</CardTitle>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <Field>
                                <div className="flex items-center gap-3">
                                    <Checkbox
                                        id="is_starred"
                                        checked={form.data.is_starred}
                                        onCheckedChange={(checked) =>
                                            form.setField(
                                                'is_starred',
                                                checked === true,
                                            )
                                        }
                                        aria-invalid={
                                            form.invalid('is_starred') ||
                                            undefined
                                        }
                                    />
                                    <FieldLabel htmlFor="is_starred">
                                        Star this todo
                                    </FieldLabel>
                                </div>
                                <FieldError>
                                    {form.error('is_starred')}
                                </FieldError>
                            </Field>
                        </CardContent>
                    </Card>
                </div>
            </div>

            {/* ── Actions ──────────────────────────────────────────── */}
            <div className="flex items-center justify-between">
                <Button variant="outline" asChild>
                    <Link href={route('app.todos.index')}>← Back</Link>
                </Button>

                <Button type="submit" disabled={form.processing}>
                    {form.processing ? (
                        <Spinner className="mr-2 size-4" />
                    ) : (
                        <SaveIcon className="mr-2 size-4" />
                    )}
                    {submitLabel}
                </Button>
            </div>
        </form>
    );
}
