import { Link, router, usePage } from '@inertiajs/react';
import {
    ArrowLeftIcon,
    CalendarIcon,
    CheckCircleIcon,
    ClipboardListIcon,
    PencilIcon,
    RefreshCwIcon,
    StarIcon,
    TagIcon,
    Trash2Icon,
    UserIcon,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { AuthenticatedSharedData, BreadcrumbItem } from '@/types';
import type { TodoShowPageProps } from '../../types/todo';

// =========================================================================
// HELPER COMPONENTS
// =========================================================================

function DetailRow({
    label,
    value,
    icon,
}: {
    label: string;
    value: ReactNode;
    icon?: ReactNode;
}) {
    if (value === null || value === undefined || value === '') return null;

    return (
        <div className="flex items-start gap-3 py-2">
            {icon && (
                <span className="mt-0.5 text-muted-foreground">{icon}</span>
            )}
            <div className="flex min-w-0 flex-col gap-0.5">
                <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    {label}
                </span>
                <span className="text-sm text-foreground">{value}</span>
            </div>
        </div>
    );
}

// =========================================================================
// COMPONENT
// =========================================================================

export default function TodosShow({ todo }: TodoShowPageProps) {
    const page = usePage<AuthenticatedSharedData>();
    const canEditTodos = page.props.auth.abilities.editTodos;
    const canDeleteTodos = page.props.auth.abilities.deleteTodos;
    const canRestoreTodos = page.props.auth.abilities.restoreTodos;

    const isTrashed = !!todo.deleted_at;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Todos', href: route('app.todos.index') },
        { title: todo.title, href: route('app.todos.show', todo.id) },
    ];

    const handleRestore = () => {
        if (!window.confirm(`Restore "${todo.title}"?`)) return;

        router.patch(
            route('app.todos.restore', todo.id),
            {},
            { preserveScroll: true },
        );
    };

    const handleDelete = () => {
        if (!window.confirm(`Move "${todo.title}" to trash?`)) return;

        router.delete(route('app.todos.destroy', todo.id), {
            preserveScroll: true,
        });
    };

    const handleForceDelete = () => {
        if (
            !window.confirm(
                `⚠️ Permanently delete "${todo.title}"? This cannot be undone!`,
            )
        )
            return;

        router.delete(route('app.todos.force-delete', todo.id), {
            preserveScroll: true,
        });
    };

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={todo.title}
            description="View todo details"
            headerActions={
                <div className="flex items-center gap-2">
                    <Button variant="outline" asChild>
                        <Link href={route('app.todos.index')}>
                            <ArrowLeftIcon data-icon="inline-start" />
                            Back
                        </Link>
                    </Button>

                    {!isTrashed && canEditTodos && (
                        <Button asChild>
                            <Link href={route('app.todos.edit', todo.id)}>
                                <PencilIcon data-icon="inline-start" />
                                Edit
                            </Link>
                        </Button>
                    )}
                </div>
            }
        >
            <div className="flex flex-col gap-6">
                {/* Header card */}
                <Card>
                    <CardContent className="pt-6">
                        <div className="flex flex-col gap-4">
                            <div className="flex flex-wrap items-center gap-3">
                                <h2 className="text-xl font-semibold text-foreground">
                                    {todo.title}
                                    {todo.is_starred && (
                                        <StarIcon className="ml-2 inline size-5 fill-yellow-400 text-yellow-400" />
                                    )}
                                </h2>
                                <Badge variant="outline">
                                    {todo.status_label}
                                </Badge>
                                <Badge variant="outline">
                                    {todo.priority_label}
                                </Badge>
                                {todo.is_overdue && (
                                    <Badge variant="destructive">Overdue</Badge>
                                )}
                                {isTrashed && (
                                    <Badge variant="destructive">
                                        In Trash
                                    </Badge>
                                )}
                            </div>

                            {todo.description && (
                                <p className="max-w-2xl text-sm text-muted-foreground">
                                    {todo.description}
                                </p>
                            )}
                        </div>
                    </CardContent>
                </Card>

                <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_300px]">
                    {/* Main details */}
                    <div className="flex flex-col gap-4">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <ClipboardListIcon className="size-4 text-muted-foreground" />
                                    Details
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="divide-y">
                                <DetailRow
                                    label="Status"
                                    value={todo.status_label}
                                />
                                <DetailRow
                                    label="Priority"
                                    value={todo.priority_label}
                                />
                                <DetailRow
                                    label="Visibility"
                                    value={
                                        todo.visibility
                                            .charAt(0)
                                            .toUpperCase() +
                                        todo.visibility.slice(1)
                                    }
                                />
                                <DetailRow
                                    label="Assigned To"
                                    icon={<UserIcon className="size-4" />}
                                    value={todo.assigned_to_name}
                                />
                                <DetailRow
                                    label="Owner"
                                    icon={<UserIcon className="size-4" />}
                                    value={todo.owner_name}
                                />
                            </CardContent>
                        </Card>

                        {todo.labels_list.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <TagIcon className="size-4 text-muted-foreground" />
                                        Labels
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="flex flex-wrap gap-2">
                                        {todo.labels_list.map((label) => (
                                            <Badge
                                                key={label}
                                                variant="secondary"
                                            >
                                                {label.trim()}
                                            </Badge>
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>
                        )}
                    </div>

                    {/* Sidebar */}
                    <div className="flex flex-col gap-4">
                        {/* Dates card */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <CalendarIcon className="size-4 text-muted-foreground" />
                                    Dates
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="divide-y">
                                <DetailRow
                                    label="Start Date"
                                    value={todo.start_date_formatted}
                                />
                                <DetailRow
                                    label="Due Date"
                                    value={todo.due_date_formatted}
                                />
                                <DetailRow
                                    label="Completed At"
                                    value={todo.completed_at_formatted}
                                />
                                <DetailRow
                                    label="Created"
                                    value={todo.created_at_formatted}
                                />
                                <DetailRow
                                    label="Updated"
                                    value={todo.updated_at_formatted}
                                />
                            </CardContent>
                        </Card>

                        {/* Completion indicator */}
                        {todo.status === 'completed' && (
                            <Card className="border-green-200 bg-green-50 dark:border-green-900 dark:bg-green-950">
                                <CardContent className="flex items-center gap-3 pt-4">
                                    <CheckCircleIcon className="size-5 text-green-600" />
                                    <span className="text-sm font-medium text-green-700 dark:text-green-400">
                                        Completed
                                        {todo.completed_at_formatted
                                            ? ` on ${todo.completed_at_formatted}`
                                            : ''}
                                    </span>
                                </CardContent>
                            </Card>
                        )}

                        {/* Actions card */}
                        {(canDeleteTodos || canRestoreTodos) && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Actions</CardTitle>
                                </CardHeader>
                                <CardContent className="flex flex-col gap-2">
                                    {isTrashed ? (
                                        <>
                                            {canRestoreTodos && (
                                                <Button
                                                    variant="outline"
                                                    className="w-full justify-start"
                                                    onClick={handleRestore}
                                                >
                                                    <RefreshCwIcon className="mr-2 size-4" />
                                                    Restore
                                                </Button>
                                            )}
                                            {canDeleteTodos && (
                                                <Button
                                                    variant="destructive"
                                                    className="w-full justify-start"
                                                    onClick={handleForceDelete}
                                                >
                                                    <Trash2Icon className="mr-2 size-4" />
                                                    Delete Permanently
                                                </Button>
                                            )}
                                        </>
                                    ) : (
                                        canDeleteTodos && (
                                            <Button
                                                variant="destructive"
                                                className="w-full justify-start"
                                                onClick={handleDelete}
                                            >
                                                <Trash2Icon className="mr-2 size-4" />
                                                Move to Trash
                                            </Button>
                                        )
                                    )}
                                </CardContent>
                            </Card>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
