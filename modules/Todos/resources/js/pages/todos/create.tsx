import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import TodoForm from '../../components/todo-form';
import type { TodoCreatePageProps } from '../../types/todo';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Todos', href: route('app.todos.index') },
    { title: 'New todo', href: route('app.todos.create') },
];

export default function TodosCreate({
    initialValues,
    statusOptions,
    priorityOptions,
    visibilityOptions,
    assigneeOptions,
}: TodoCreatePageProps) {
    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Create Todo"
            description="Add a new task to your todo list."
            headerActions={
                <Button variant="outline" asChild>
                    <Link href={route('app.todos.index')}>← Back</Link>
                </Button>
            }
        >
            <TodoForm
                mode="create"
                initialValues={initialValues}
                statusOptions={statusOptions}
                priorityOptions={priorityOptions}
                visibilityOptions={visibilityOptions}
                assigneeOptions={assigneeOptions}
            />
        </AppLayout>
    );
}
