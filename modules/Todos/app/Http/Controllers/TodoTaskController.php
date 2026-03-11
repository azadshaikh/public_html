<?php

namespace Modules\Todos\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ModuleManager;
use App\Modules\Support\ModuleManifest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Todos\Http\Requests\TodoTaskRequest;
use Modules\Todos\Models\TodoTask;

class TodoTaskController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $this->filters($request);

        $tasks = TodoTask::query()
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $query->where(function (Builder $query) use ($filters): void {
                    $query
                        ->where('title', 'ilike', sprintf('%%%s%%', $filters['search']))
                        ->orWhere('slug', 'ilike', sprintf('%%%s%%', $filters['search']))
                        ->orWhere('owner', 'ilike', sprintf('%%%s%%', $filters['search']));
                });
            })
            ->when($filters['status'] !== '', fn (Builder $query) => $query->where('status', $filters['status']))
            ->orderByDesc('is_blocked')
            ->orderByRaw("case priority when 'high' then 1 when 'medium' then 2 else 3 end")
            ->oldest('due_date')
            ->orderBy('title')
            ->paginate(8)
            ->withQueryString()
            ->through(function (mixed $task): array {
                /** @var TodoTask $task */
                return [
                    'id' => $task->id,
                    'title' => $task->title,
                    'slug' => $task->slug,
                    'status' => $task->status,
                    'priority' => $task->priority,
                    'owner' => $task->owner,
                    'due_date' => $task->due_date?->toDateString(),
                    'is_blocked' => $task->is_blocked,
                ];
            });

        return Inertia::render('todos/index', [
            'module' => $this->module()->toSharedArray(),
            'filters' => $filters,
            'tasks' => $tasks,
            'stats' => [
                'total' => TodoTask::query()->count(),
                'in_progress' => TodoTask::query()->where('status', 'in_progress')->count(),
                'done' => TodoTask::query()->where('status', 'done')->count(),
                'blocked' => TodoTask::query()->where('is_blocked', true)->count(),
            ],
            'options' => $this->options(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('todos/create', [
            'module' => $this->module()->toSharedArray(),
            'task' => null,
            'initialValues' => TodoTask::defaultFormData(),
            'options' => $this->options(),
        ]);
    }

    public function store(TodoTaskRequest $request): RedirectResponse
    {
        TodoTask::query()->create($request->taskAttributes());

        return to_route('todos.index')->with('status', 'Task created.');
    }

    public function edit(TodoTask $todoTask): Response
    {
        return Inertia::render('todos/edit', [
            'module' => $this->module()->toSharedArray(),
            'task' => [
                'id' => $todoTask->id,
                'title' => $todoTask->title,
            ],
            'initialValues' => [
                'title' => $todoTask->title,
                'slug' => $todoTask->slug,
                'details' => $todoTask->details ?? '',
                'status' => $todoTask->status,
                'priority' => $todoTask->priority,
                'owner' => $todoTask->owner ?? '',
                'due_date' => $todoTask->due_date?->toDateString() ?? '',
                'is_blocked' => $todoTask->is_blocked,
            ],
            'options' => $this->options(),
        ]);
    }

    public function update(TodoTaskRequest $request, TodoTask $todoTask): RedirectResponse
    {
        $todoTask->update($request->taskAttributes());

        return to_route('todos.index')->with('status', 'Task updated.');
    }

    public function destroy(TodoTask $todoTask): RedirectResponse
    {
        $todoTask->delete();

        return to_route('todos.index')->with('status', 'Task deleted.');
    }

    /**
     * @return array{search: string, status: string}
     */
    protected function filters(Request $request): array
    {
        return [
            'search' => trim((string) $request->query('search', '')),
            'status' => $this->sanitizeFilter((string) $request->query('status', ''), array_keys(TodoTask::STATUSES)),
        ];
    }

    /**
     * @return array{statusOptions: array<int, array{value: string, label: string}>, priorityOptions: array<int, array{value: string, label: string}>}
     */
    protected function options(): array
    {
        return [
            'statusOptions' => collect(TodoTask::STATUSES)
                ->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])
                ->values()
                ->all(),
            'priorityOptions' => collect(TodoTask::PRIORITIES)
                ->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])
                ->values()
                ->all(),
        ];
    }

    protected function sanitizeFilter(string $value, array $allowed): string
    {
        $value = trim($value);

        return in_array($value, $allowed, true) ? $value : '';
    }

    protected function module(): ModuleManifest
    {
        return resolve(ModuleManager::class)->findOrFail('todos');
    }
}
