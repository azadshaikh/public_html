<?php

declare(strict_types=1);

namespace App\Http\Controllers\Masters;

use App\Definitions\GroupDefinition;
use App\Scaffold\ScaffoldController;
use App\Services\GroupService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Inertia;
use Inertia\Response;

class GroupController extends ScaffoldController implements HasMiddleware
{
    public function __construct(
        private readonly GroupService $groupService
    ) {}

    public static function middleware(): array
    {
        return (new GroupDefinition)->getMiddleware();
    }

    /**
     * Override show to include group with items relation.
     */
    public function show(int|string $id): Response
    {
        $this->enforcePermission('view');

        $model = $this->findModel((int) $id);
        $model->load('items');

        return Inertia::render($this->inertiaPage().'/show', [
            $this->getModelKey() => $model->toArray(),
            ...$this->getShowViewData($model),
        ]);
    }

    protected function service(): GroupService
    {
        return $this->groupService;
    }

    protected function inertiaPage(): string
    {
        return 'masters/groups';
    }

    protected function getFormViewData(Model $model): array
    {
        return [
            'statusOptions' => $this->groupService->getStatusOptions(),
        ];
    }
}
