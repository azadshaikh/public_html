import { Link, router, usePage } from '@inertiajs/react';
import {
    AlertCircleIcon,
    CheckCircleIcon,
    ClipboardListIcon,
    EyeIcon,
    ListIcon,
    PauseCircleIcon,
    PencilIcon,
    PlusIcon,
    RefreshCwIcon,
    TimerIcon,
    Trash2Icon,
    XCircleIcon,
} from 'lucide-react';
import { Datagrid } from '@/components/datagrid/datagrid';
import type {
    DatagridAction,
    DatagridBulkAction,
    DatagridColumn,
    DatagridFilter,
    DatagridTab,
} from '@/components/datagrid/datagrid';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { AuthenticatedSharedData, BreadcrumbItem } from '@/types';
import type { TodoIndexPageProps, TodoListItem } from '../../types/todo';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Todos', href: route('app.todos.index') },
];

export default function TodosIndex({
    config,
    todos,
    filters,
    statistics,
}: TodoIndexPageProps) {
    const page = usePage<AuthenticatedSharedData>();
    const canAddTodos = page.props.auth.abilities.addTodos;
    const canEditTodos = page.props.auth.abilities.editTodos;
    const canDeleteTodos = page.props.auth.abilities.deleteTodos;
    const canRestoreTodos = page.props.auth.abilities.restoreTodos;

    // ----- Bulk action helper -----

    const handleBulkAction = (
        action: string,
        selectedTodos: TodoListItem[],
        clearSelection: () => void,
    ) => {
        if (selectedTodos.length === 0) {
            return;
        }

        router.post(
            route('app.todos.bulk-action'),
            {
                action,
                ids: selectedTodos.map((todo) => todo.id),
                status: filters.status,
            },
            {
                preserveScroll: true,
                onSuccess: () => clearSelection(),
            },
        );
    };

    // ----- Filters -----

    const gridFilters: DatagridFilter[] = [
        {
            type: 'search',
            name: 'search',
            value: filters.search,
            placeholder: 'Search todos...',
            className: 'lg:min-w-80',
        },
        {
            type: 'select',
            name: 'priority',
            value: filters.priority,
            options: [
                { value: 'low', label: 'Low' },
                { value: 'medium', label: 'Medium' },
                { value: 'high', label: 'High' },
                { value: 'critical', label: 'Critical' },
            ],
        },
        {
            type: 'select',
            name: 'visibility',
            value: filters.visibility,
            options: [
                { value: 'private', label: 'Private' },
                { value: 'public', label: 'Public' },
            ],
        },
    ];

    // ----- Status tabs -----

    const statusTabs: DatagridTab[] = [
        {
            label: 'All',
            value: 'all',
            count: statistics.total,
            active: filters.status === 'all',
            icon: <ListIcon />,
            countVariant: 'secondary',
        },
        {
            label: 'Pending',
            value: 'pending',
            count: statistics.pending,
            active: filters.status === 'pending',
            icon: <TimerIcon />,
            countVariant: 'warning',
        },
        {
            label: 'In Progress',
            value: 'in_progress',
            count: statistics.in_progress,
            active: filters.status === 'in_progress',
            icon: <AlertCircleIcon />,
            countVariant: 'info',
        },
        {
            label: 'Completed',
            value: 'completed',
            count: statistics.completed,
            active: filters.status === 'completed',
            icon: <CheckCircleIcon />,
            countVariant: 'success',
        },
        {
            label: 'On Hold',
            value: 'on_hold',
            count: statistics.on_hold,
            active: filters.status === 'on_hold',
            icon: <PauseCircleIcon />,
            countVariant: 'secondary',
        },
        {
            label: 'Cancelled',
            value: 'cancelled',
            count: statistics.cancelled,
            active: filters.status === 'cancelled',
            icon: <XCircleIcon />,
            countVariant: 'danger',
        },
        {
            label: 'Trash',
            value: 'trash',
            count: statistics.trash,
            active: filters.status === 'trash',
            icon: <Trash2Icon />,
            countVariant: 'destructive',
        },
    ];

    // ----- Columns -----

    const columns: DatagridColumn<TodoListItem>[] = [
        {
            key: 'title',
            header: 'Title',
            sortable: true,
            cell: (todo) => (
                <Link
                    href={todo.show_url}
                    className="flex min-w-0 flex-col gap-1 hover:opacity-80"
                >
                    <span className="font-medium text-foreground">
                        {todo.title}
                        {todo.is_starred && (
                            <span
                                className="ml-2 text-yellow-500"
                                aria-label="Starred"
                            >
                                ★
                            </span>
                        )}
                    </span>
                    {todo.description_preview && (
                        <span className="truncate text-xs text-muted-foreground">
                            {todo.description_preview}
                        </span>
                    )}
                </Link>
            ),
        },
        {
            key: 'priority_label',
            header: 'Priority',
            headerClassName: 'w-28 text-center',
            cellClassName: 'w-28 text-center',
            type: 'badge',
            badgeVariantKey: 'priority_badge',
            sortable: true,
            sortKey: 'priority',
        },
        {
            key: 'status_label',
            header: 'Status',
            headerClassName: 'w-32 text-center',
            cellClassName: 'w-32 text-center',
            type: 'badge',
            badgeVariantKey: 'status_badge',
            sortable: true,
            sortKey: 'status',
        },
        {
            key: 'assigned_to_name',
            header: 'Assigned To',
            headerClassName: 'w-36',
            cellClassName: 'w-36 text-sm text-muted-foreground',
            sortable: false,
        },
        {
            key: 'due_date_formatted',
            header: 'Due Date',
            headerClassName: 'w-28',
            cellClassName: 'w-28',
            sortable: true,
            sortKey: 'due_date',
            cell: (todo) =>
                todo.due_date_formatted ? (
                    <span
                        className={
                            todo.is_overdue
                                ? 'font-medium text-destructive'
                                : 'text-muted-foreground'
                        }
                    >
                        {todo.due_date_formatted}
                        {todo.is_overdue && (
                            <span className="ml-1 text-xs">(overdue)</span>
                        )}
                    </span>
                ) : (
                    <span className="text-muted-foreground">—</span>
                ),
        },
    ];

    // ----- Row actions -----

    const rowActions = (todo: TodoListItem): DatagridAction[] => {
        if (todo.is_trashed) {
            return [
                ...(canRestoreTodos
                    ? [
                          {
                              label: 'Restore',
                              icon: <RefreshCwIcon />,
                              href: route('app.todos.restore', todo.id),
                              method: 'PATCH' as const,
                              confirm: `Restore "${todo.title}"?`,
                          },
                      ]
                    : []),
                ...(canDeleteTodos
                    ? [
                          {
                              label: 'Delete Permanently',
                              icon: <Trash2Icon />,
                              href: route('app.todos.force-delete', todo.id),
                              method: 'DELETE' as const,
                              confirm: `⚠️ Permanently delete "${todo.title}"? This cannot be undone!`,
                              variant: 'destructive' as const,
                          },
                      ]
                    : []),
            ];
        }

        return [
            {
                label: 'View',
                href: todo.show_url,
                icon: <EyeIcon />,
            },
            ...(canEditTodos
                ? [
                      {
                          label: 'Edit',
                          href: todo.edit_url,
                          icon: <PencilIcon />,
                      },
                  ]
                : []),
            ...(canDeleteTodos
                ? [
                      {
                          label: 'Move to Trash',
                          href: route('app.todos.destroy', todo.id),
                          method: 'DELETE' as const,
                          confirm: `Move "${todo.title}" to trash?`,
                          icon: <Trash2Icon />,
                          variant: 'destructive' as const,
                      },
                  ]
                : []),
        ];
    };

    // ----- Bulk actions -----

    const bulkActions: DatagridBulkAction<TodoListItem>[] = [
        ...(canDeleteTodos
            ? [
                  {
                      key: 'bulk-delete',
                      label: 'Move to Trash',
                      icon: <Trash2Icon />,
                      variant: 'destructive' as const,
                      confirm: 'Move selected todos to trash?',
                      onSelect: (rows: TodoListItem[], clear: () => void) =>
                          handleBulkAction('delete', rows, clear),
                  },
              ]
            : []),
        ...(canRestoreTodos
            ? [
                  {
                      key: 'bulk-restore',
                      label: 'Restore',
                      icon: <RefreshCwIcon />,
                      confirm: 'Restore selected todos from trash?',
                      onSelect: (rows: TodoListItem[], clear: () => void) =>
                          handleBulkAction('restore', rows, clear),
                  },
              ]
            : []),
        ...(canDeleteTodos
            ? [
                  {
                      key: 'bulk-force-delete',
                      label: 'Delete Permanently',
                      icon: <Trash2Icon />,
                      variant: 'destructive' as const,
                      confirm:
                          '⚠️ Permanently delete selected todos? This cannot be undone!',
                      onSelect: (rows: TodoListItem[], clear: () => void) =>
                          handleBulkAction('force_delete', rows, clear),
                  },
              ]
            : []),
    ];

    const visibleBulkActions =
        filters.status === 'trash'
            ? bulkActions.filter((a) => a.key !== 'bulk-delete')
            : bulkActions.filter((a) => a.key === 'bulk-delete');

    // ----- Render -----

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Todos"
            description="Manage tasks and to-dos"
            headerActions={
                canAddTodos ? (
                    <Button asChild>
                        <Link href={route('app.todos.create')}>
                            <PlusIcon data-icon="inline-start" />
                            Add Todo
                        </Link>
                    </Button>
                ) : undefined
            }
        >
            <div className="flex flex-col gap-6">
                <Datagrid
                    action={route('app.todos.index')}
                    rows={todos}
                    columns={columns}
                    scaffoldColumns={config.columns}
                    filters={gridFilters}
                    tabs={{
                        name: 'status',
                        items: statusTabs,
                    }}
                    getRowKey={(todo) => todo.id}
                    rowActions={rowActions}
                    bulkActions={visibleBulkActions}
                    isRowSelectable={() => visibleBulkActions.length > 0}
                    sorting={{
                        sort: filters.sort,
                        direction: filters.direction,
                    }}
                    perPage={{
                        value: filters.per_page,
                        options: [10, 25, 50, 100],
                    }}
                    view={{
                        value: filters.view ?? 'table',
                        storageKey: 'todos-datagrid-view',
                    }}
                    renderCard={(todo) => (
                        <div className="flex flex-col gap-4">
                            <Link
                                href={todo.show_url}
                                className="flex flex-col gap-1 hover:opacity-80"
                            >
                                <span className="font-medium text-foreground">
                                    {todo.title}
                                    {todo.is_starred && (
                                        <span className="ml-2 text-yellow-500">
                                            ★
                                        </span>
                                    )}
                                </span>
                                {todo.description_preview && (
                                    <span className="text-xs text-muted-foreground">
                                        {todo.description_preview}
                                    </span>
                                )}
                            </Link>

                            <div className="grid gap-3 sm:grid-cols-3">
                                <div className="rounded-lg border bg-muted/30 px-3 py-2">
                                    <div className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                        Status
                                    </div>
                                    <div className="mt-1">
                                        <Badge
                                            variant={
                                                (todo.status_badge as Parameters<
                                                    typeof Badge
                                                >[0]['variant']) ?? 'outline'
                                            }
                                        >
                                            {todo.status_label}
                                        </Badge>
                                    </div>
                                </div>
                                <div className="rounded-lg border bg-muted/30 px-3 py-2">
                                    <div className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                        Priority
                                    </div>
                                    <div className="mt-1">
                                        <Badge
                                            variant={
                                                (todo.priority_badge as Parameters<
                                                    typeof Badge
                                                >[0]['variant']) ?? 'outline'
                                            }
                                        >
                                            {todo.priority_label}
                                        </Badge>
                                    </div>
                                </div>
                                <div className="rounded-lg border bg-muted/30 px-3 py-2">
                                    <div className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                        Assigned
                                    </div>
                                    <div className="mt-1 text-sm font-medium text-foreground">
                                        {todo.assigned_to_name}
                                    </div>
                                </div>
                            </div>
                        </div>
                    )}
                    submitLabel="Filters"
                    submitButtonVariant="outline"
                    empty={{
                        icon: <ClipboardListIcon />,
                        title: 'No todos found',
                        description:
                            'Try a different filter or create the first todo.',
                    }}
                />
            </div>
        </AppLayout>
    );
}
