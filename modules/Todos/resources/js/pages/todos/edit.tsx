import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import TodoTaskForm from '../../components/todo-task-form';
import type { TodoTaskFormValues } from '../../components/todo-task-form';

type Option = { value: string; label: string };

type TodosEditPageProps = {
    module: {
        name: string;
        slug: string;
        version: string;
        description: string;
    };
    task: {
        id: number;
        title: string;
    };
    initialValues: TodoTaskFormValues;
    options: {
        statusOptions: Option[];
        priorityOptions: Option[];
    };
};

export default function TodosEdit({
    module,
    task,
    initialValues,
    options,
}: TodosEditPageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: module.name, href: '/todos' },
        { title: task.title, href: `/todos/${task.id}/edit` },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={`Edit ${task.title}`}
            description={module.description}
        >
            <TodoTaskForm
                mode="edit"
                module={module}
                task={task}
                initialValues={initialValues}
                options={options}
            />
        </AppLayout>
    );
}
