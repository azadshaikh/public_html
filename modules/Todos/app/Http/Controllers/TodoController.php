<?php

declare(strict_types=1);

namespace Modules\Todos\Http\Controllers;

use App\Scaffold\ScaffoldController;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Todos\Definitions\TodoDefinition;
use Modules\Todos\Services\TodoService;

class TodoController extends ScaffoldController implements HasMiddleware
{
    public function __construct(private readonly TodoService $todoService) {}

    public static function middleware(): array
    {
        return (new TodoDefinition)->getMiddleware();
    }

    // ================================================================
    // OVERRIDE INDEX TO PROVIDE CLEAN PROPS FOR REACT PAGE
    // ================================================================

    public function index(Request $request): Response|RedirectResponse
    {
        $this->enforcePermission('view');

        $status = $request->input('status') ?? $request->route('status') ?? 'all';
        $perPage = $this->service()->getScaffoldDefinition()->getPerPage();

        return Inertia::render($this->inertiaPage().'/index', [
            'config' => $this->service()->getScaffoldDefinition()->toInertiaConfig(),
            'todos' => $this->todoService->getPaginatedTodos($request),
            'statistics' => $this->todoService->getStatistics(),
            'filters' => [
                'search' => $request->input('search', ''),
                'status' => $status,
                'priority' => $request->input('priority', ''),
                'visibility' => $request->input('visibility', ''),
                'sort' => $request->input('sort', 'created_at'),
                'direction' => $request->input('direction', 'desc'),
                'per_page' => (int) $request->input('per_page', $perPage),
                'view' => $request->input('view', 'table'),
            ],
            'status' => session('status'),
            'error' => session('error'),
        ]);
    }

    protected function service(): TodoService
    {
        return $this->todoService;
    }

    protected function inertiaPage(): string
    {
        return 'todos';
    }

    protected function getFormViewData(Model $model): array
    {
        return [
            'initialValues' => [
                'title' => (string) ($model->getAttribute('title') ?? ''),
                'description' => (string) ($model->getAttribute('description') ?? ''),
                'status' => (string) ($model->getAttribute('status') ?? 'pending'),
                'priority' => (string) ($model->getAttribute('priority') ?? 'medium'),
                'visibility' => (string) ($model->getAttribute('visibility') ?? 'private'),
                'start_date' => $model->getAttribute('start_date') ? $model->getAttribute('start_date')->format('Y-m-d') : '',
                'due_date' => $model->getAttribute('due_date') ? $model->getAttribute('due_date')->format('Y-m-d') : '',
                'is_starred' => (bool) ($model->getAttribute('is_starred') ?? false),
                'assigned_to' => (string) ($model->getAttribute('assigned_to') ?? ''),
                'labels' => (string) ($model->getAttribute('labels') ?? ''),
            ],
            'statusOptions' => $this->todoService->getStatusOptions(),
            'priorityOptions' => $this->todoService->getPriorityOptions(),
            'visibilityOptions' => $this->todoService->getVisibilityOptions(),
            'assigneeOptions' => $this->todoService->getAssigneeOptions(),
        ];
    }
}
