import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import TodoForm from '../../components/todo-form';
import type { TodoEditPageProps } from '../../types/todo';

export default function TodosEdit({
    todo,
    initialValues,
    statusOptions,
    priorityOptions,
    visibilityOptions,
    assigneeOptions,
}: TodoEditPageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Todos', href: route('app.todos.index') },
        {
            title: todo.title,
            href: route('app.todos.edit', todo.id),
        },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={`Edit: ${todo.title}`}
            description="Update the todo details."
            headerActions={
                <Button variant="outline" asChild>
                    <Link href={route('app.todos.index')}>← Back</Link>
                </Button>
            }
        >
            <TodoForm
                mode="edit"
                todo={todo}
                initialValues={initialValues}
                statusOptions={statusOptions}
                priorityOptions={priorityOptions}
                visibilityOptions={visibilityOptions}
                assigneeOptions={assigneeOptions}
            />
        </AppLayout>
    );
}
