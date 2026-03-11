import { Form, Head, Link, router } from '@inertiajs/react';
import {
  CheckSquareIcon,
  LayoutDashboardIcon,
  PencilIcon,
  PlusIcon,
  SearchIcon,
  ShieldAlertIcon,
  Trash2Icon,
} from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import {
  Empty,
  EmptyContent,
  EmptyDescription,
  EmptyHeader,
  EmptyMedia,
  EmptyTitle,
} from '@/components/ui/empty';
import {
  InputGroup,
  InputGroupAddon,
  InputGroupInput,
} from '@/components/ui/input-group';
import {
  NativeSelect,
  NativeSelectOption,
} from '@/components/ui/native-select';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes/index';
import type { BreadcrumbItem } from '@/types';

type Option = { value: string; label: string };

type ModuleMeta = {
  name: string;
  slug: string;
  version: string;
  description: string;
};

type TaskListItem = {
  id: number;
  title: string;
  slug: string;
  status: string;
  priority: string;
  owner: string | null;
  due_date: string | null;
  is_blocked: boolean;
};

type PaginatedData<T> = {
  data: T[];
  prev_page_url: string | null;
  next_page_url: string | null;
  total: number;
  from: number | null;
  to: number | null;
};

type TodosIndexPageProps = {
  module: ModuleMeta;
  filters: { search: string; status: string };
  tasks: PaginatedData<TaskListItem>;
  stats: { total: number; in_progress: number; done: number; blocked: number };
  options: { statusOptions: Option[]; priorityOptions: Option[] };
  status?: string;
};

export default function TodosIndex({
  module,
  filters,
  tasks,
  stats,
  options,
  status,
}: TodosIndexPageProps) {
  const todosIndexUrl = '/todos';
  const todosCreateUrl = '/todos/create';
  const todosEditUrl = (id: number) => `/todos/${id}/edit`;
  const todosDestroyUrl = (id: number) => `/todos/${id}`;

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard() },
    { title: module.name, href: todosIndexUrl },
  ];

  const handleDelete = (task: TaskListItem) => {
    if (!window.confirm(`Delete ${task.title}?`)) {
      return;
    }

    router.delete(todosDestroyUrl(task.id), {
      preserveScroll: true,
    });
  };

  return (
    <AppLayout
      breadcrumbs={breadcrumbs}
      title={`${module.name} tasks`}
      description="Run a lightweight task CRUD from the module runtime."
      headerActions={
        <div className="flex flex-wrap gap-3">
          <Button asChild variant="outline">
            <Link href={dashboard()}>
              <LayoutDashboardIcon />
              Back to dashboard
            </Link>
          </Button>
          <Button asChild>
            <Link href={todosCreateUrl}>
              <PlusIcon />
              Create task
            </Link>
          </Button>
        </div>
      }
    >
      <Head title={`${module.name} tasks`} />

      <section className="grid gap-4 xl:grid-cols-[1.35fr_1fr]">
        <Card className="border-none bg-gradient-to-br from-primary to-primary/80 text-primary-foreground shadow-none ring-0">
          <CardHeader>
            <Badge
              variant="secondary"
              className="w-fit bg-primary-foreground/15 text-primary-foreground hover:bg-primary-foreground/15"
            >
              Starter CRUD
            </Badge>
            <CardTitle className="mt-4 text-3xl font-semibold tracking-tight md:text-4xl">
              Track work with a clean task resource.
            </CardTitle>
            <CardDescription className="text-primary-foreground/75">
              This sample is ready for roadmap work, team views, automation
              rules, and lightweight planning features.
            </CardDescription>
          </CardHeader>
          <CardContent className="grid gap-4 sm:grid-cols-3">
            <div>
              <div className="text-sm text-primary-foreground/70">Tasks</div>
              <div className="mt-1 text-xl font-semibold">{stats.total}</div>
            </div>
            <div>
              <div className="text-sm text-primary-foreground/70">
                In progress
              </div>
              <div className="mt-1 text-xl font-semibold">
                {stats.in_progress}
              </div>
            </div>
            <div>
              <div className="text-sm text-primary-foreground/70">Blocked</div>
              <div className="mt-1 text-xl font-semibold">{stats.blocked}</div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Useful next steps</CardTitle>
            <CardDescription>
              Grow this starter into a real work management surface.
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            {[
              'Add task comments, attachments, or activity logs',
              'Group work by project, sprint, or team',
              'Promote blocked items into escalation queues',
            ].map((item) => (
              <div key={item} className="rounded-xl border bg-muted/40 p-4">
                <div className="mb-2 flex items-center gap-2 font-medium">
                  <CheckSquareIcon className="size-4 text-primary" />
                  Starter idea
                </div>
                <p className="text-sm text-muted-foreground">{item}</p>
              </div>
            ))}
          </CardContent>
        </Card>
      </section>

      {status ? (
        <Alert className="mt-6">
          <CheckSquareIcon className="size-4" />
          <AlertTitle>Saved</AlertTitle>
          <AlertDescription>{status}</AlertDescription>
        </Alert>
      ) : null}

      <Card className="mt-6">
        <CardHeader>
          <CardTitle>Filter tasks</CardTitle>
          <CardDescription>
            Use the list like a lightweight queue for building more workflow
            features later.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <Form
            action={todosIndexUrl}
            method="get"
            options={{ preserveScroll: true }}
            className="grid gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_auto]"
          >
            <InputGroup className="w-full">
              <InputGroupAddon>
                <SearchIcon />
              </InputGroupAddon>
              <InputGroupInput
                name="search"
                defaultValue={filters.search}
                placeholder="Search title, slug, or owner"
              />
            </InputGroup>

            <NativeSelect
              className="w-full"
              name="status"
              defaultValue={filters.status}
            >
              <NativeSelectOption value="">All statuses</NativeSelectOption>
              {options.statusOptions.map((option) => (
                <NativeSelectOption key={option.value} value={option.value}>
                  {option.label}
                </NativeSelectOption>
              ))}
            </NativeSelect>

            <div className="flex gap-2">
              <Button type="submit">Apply</Button>
              <Button asChild type="button" variant="outline">
                <Link href={todosIndexUrl}>Reset</Link>
              </Button>
            </div>
          </Form>
        </CardContent>
      </Card>

      <section className="mt-6 grid gap-4 lg:grid-cols-2">
        {tasks.data.length > 0 ? (
          tasks.data.map((task) => (
            <Card key={task.id}>
              <CardHeader>
                <div className="flex items-start justify-between gap-3">
                  <div>
                    <CardTitle className="text-xl">{task.title}</CardTitle>
                    <CardDescription>
                      {task.owner || 'Unassigned'} • /{task.slug}
                    </CardDescription>
                  </div>
                  <div className="flex gap-2">
                    <Badge variant="outline">{task.status}</Badge>
                    <Badge variant="secondary">{task.priority}</Badge>
                    {task.is_blocked ? (
                      <Badge variant="destructive">Blocked</Badge>
                    ) : null}
                  </div>
                </div>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="text-sm text-muted-foreground">
                  {task.due_date ? `Due ${task.due_date}` : 'No due date set'}
                </div>
                <div className="flex flex-wrap justify-end gap-2">
                  <Button asChild size="sm" variant="outline">
                    <Link href={todosEditUrl(task.id)}>
                      <PencilIcon />
                      Edit
                    </Link>
                  </Button>
                  <Button
                    size="sm"
                    variant="destructive"
                    onClick={() => handleDelete(task)}
                  >
                    <Trash2Icon />
                    Delete
                  </Button>
                </div>
              </CardContent>
            </Card>
          ))
        ) : (
          <Card className="lg:col-span-2">
            <CardContent className="p-6">
              <Empty>
                <EmptyHeader>
                  <EmptyMedia variant="icon">
                    <ShieldAlertIcon />
                  </EmptyMedia>
                  <EmptyTitle>No tasks yet</EmptyTitle>
                  <EmptyDescription>
                    Create the first task to test statuses, priorities, and
                    owner assignment inside the module.
                  </EmptyDescription>
                </EmptyHeader>
                <EmptyContent>
                  <Button asChild>
                    <Link href={todosCreateUrl}>
                      <PlusIcon />
                      Create task
                    </Link>
                  </Button>
                </EmptyContent>
              </Empty>
            </CardContent>
          </Card>
        )}
      </section>

      {tasks.prev_page_url || tasks.next_page_url ? (
        <div className="mt-6 flex items-center justify-between gap-4 text-sm text-muted-foreground">
          <span>
            Showing {tasks.from ?? 0} to {tasks.to ?? 0} of {tasks.total} tasks
          </span>
          <div className="flex gap-2">
            <Button
              asChild
              variant="outline"
              size="sm"
              disabled={!tasks.prev_page_url}
            >
              <Link href={tasks.prev_page_url ?? todosIndexUrl}>Previous</Link>
            </Button>
            <Button
              asChild
              variant="outline"
              size="sm"
              disabled={!tasks.next_page_url}
            >
              <Link href={tasks.next_page_url ?? todosIndexUrl}>Next</Link>
            </Button>
          </div>
        </div>
      ) : null}
    </AppLayout>
  );
}
