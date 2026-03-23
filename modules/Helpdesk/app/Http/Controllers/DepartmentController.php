<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Http\Controllers;

use App\Enums\Status;
use App\Scaffold\ScaffoldController;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Controllers\HasMiddleware;
use Modules\Helpdesk\Definitions\DepartmentDefinition;
use Modules\Helpdesk\Models\Department;
use Modules\Helpdesk\Models\Ticket;
use Modules\Helpdesk\Services\DepartmentService;

class DepartmentController extends ScaffoldController implements HasMiddleware
{
    public function __construct(
        private readonly DepartmentService $departmentService
    ) {}

    public static function middleware(): array
    {
        return (new DepartmentDefinition)->getMiddleware();
    }

    protected function service(): DepartmentService
    {
        return $this->departmentService;
    }

    protected function inertiaPage(): string
    {
        return 'helpdesk/departments';
    }

    protected function getFormViewData(Model $model): array
    {
        /** @var Department $department */
        $department = $model;

        return [
            'initialValues' => [
                'name' => (string) ($department->name ?? ''),
                'description' => (string) ($department->description ?? ''),
                'department_head' => $department->department_head ? (string) $department->department_head : '',
                'visibility' => (string) ($department->visibility ?? 'public'),
                'status' => (string) ($department->status?->value ?? $department->status ?? Status::ACTIVE->value),
            ],
            'headOptions' => $this->departmentService->getDepartmentHeadOptions(),
            'visibilityOptions' => $this->departmentService->getVisibilityOptions(),
            'statusOptions' => $this->departmentService->getStatusOptions(),
        ];
    }

    protected function transformModelForEdit(Model $model): array
    {
        /** @var Department $department */
        $department = $model;

        return [
            'id' => $department->getKey(),
            'name' => $department->name,
        ];
    }

    protected function transformModelForShow(Model $model): array
    {
        /** @var Department $department */
        $department = $model;
        $department->loadMissing('departmentHead:id,first_name,last_name,name');

        return [
            'id' => $department->getKey(),
            'name' => $department->name,
            'description' => (string) ($department->description ?? ''),
            'department_head_name' => $department->departmentHead?->full_name ?? $department->departmentHead?->name ?? 'Unassigned',
            'visibility' => (string) ($department->visibility ?? 'public'),
            'visibility_label' => match ((string) ($department->visibility ?? 'public')) {
                'private' => 'Private',
                default => 'Public',
            },
            'status' => (string) ($department->status?->value ?? $department->status ?? Status::ACTIVE->value),
            'status_label' => (string) ($department->status_label ?? 'Active'),
            'created_at' => app_date_time_format($department->created_at, 'datetime'),
            'updated_at' => app_date_time_format($department->updated_at, 'datetime'),
            'deleted_at' => app_date_time_format($department->deleted_at, 'datetime'),
            'is_trashed' => (bool) $department->trashed(),
        ];
    }

    protected function getShowViewData(Model $model): array
    {
        /** @var Department $department */
        $department = $model;

        return [
            'statistics' => [
                'tickets' => Ticket::query()->where('department_id', $department->getKey())->count(),
                'open_tickets' => Ticket::query()
                    ->where('department_id', $department->getKey())
                    ->where('status', 'open')
                    ->count(),
            ],
        ];
    }

    protected function handleRestorationSideEffects(Model $model): void
    {
        if ($model instanceof Department) {
            $model->update(['status' => Status::ACTIVE]);
        }
    }
}
