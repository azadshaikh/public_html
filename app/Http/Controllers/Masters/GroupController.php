<?php

declare(strict_types=1);

namespace App\Http\Controllers\Masters;

use App\Definitions\GroupDefinition;
use App\Definitions\GroupItemDefinition;
use App\Scaffold\ScaffoldController;
use App\Services\GroupService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\View\View;

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
     * Override show to include group with items relation and items config
     */
    public function show(int|string $id): View|JsonResponse
    {
        $model = $this->findModel((int) $id);
        $model->load('items');

        if (request()->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'data' => $model,
            ]);
        }

        // Create GroupItemDefinition with explicit group ID for nested route generation
        $itemsDefinition = new GroupItemDefinition((int) $id);
        $itemsConfig = $itemsDefinition->toDataGridConfig();

        return view($this->scaffold()->getShowView(), [
            $this->getModelKey() => $model,
            'itemsConfig' => $itemsConfig,
        ]);
    }

    protected function service(): GroupService
    {
        return $this->groupService;
    }

    protected function getFormViewData(Model $model): array
    {
        return [
            'statusOptions' => $this->groupService->getStatusOptions(),
        ];
    }
}
