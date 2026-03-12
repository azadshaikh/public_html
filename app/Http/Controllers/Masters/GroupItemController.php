<?php

declare(strict_types=1);

namespace App\Http\Controllers\Masters;

use App\Definitions\GroupItemDefinition;
use App\Enums\ActivityAction;
use App\Models\Group;
use App\Models\GroupItem;
use App\Scaffold\ScaffoldController;
use App\Services\GroupItemService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Response;

class GroupItemController extends ScaffoldController implements HasMiddleware
{
    public function __construct(private readonly GroupItemService $groupItemService) {}

    public static function middleware(): array
    {
        return (new GroupItemDefinition)->getMiddleware();
    }

    /**
     * Override index to redirect to Group show page.
     * Items are displayed within the Group show page, not as standalone index.
     */
    public function index(Request $request): Response|RedirectResponse
    {
        return redirect($this->getGroupShowUrl());
    }

    /**
     * Override store to redirect to group show page.
     */
    public function store(Request $request): RedirectResponse
    {
        $validatedData = $this->validateRequest($request);
        $model = $this->service()->create($validatedData);

        $this->handleCreationSideEffects($model);
        $this->logActivity($model, ActivityAction::CREATE, $this->getEntityName().' created successfully');

        return redirect($this->getGroupShowUrl())
            ->with('status', $this->buildCreateSuccessMessage($model));
    }

    /**
     * Override update to redirect to group show page.
     */
    public function update(Request $request, int|string $id): RedirectResponse
    {
        $model = $this->findModel((int) $id);
        $validatedData = $this->validateRequest($request);

        $this->service()->update($model, $validatedData);

        $this->handleUpdateSideEffects($model);
        $this->logActivity($model, ActivityAction::UPDATE, $this->getEntityName().' updated successfully');

        return redirect($this->getGroupShowUrl())
            ->with('status', $this->buildUpdateSuccessMessage($model));
    }

    /**
     * Override destroy to redirect to group show page.
     */
    public function destroy(int|string $id): RedirectResponse
    {
        $model = $this->findModel((int) $id);
        $this->service()->delete($model);

        $this->handleDeletionSideEffects($model);
        $this->logActivity($model, ActivityAction::DELETE, $this->getEntityName().' moved to trash');

        return redirect($this->getGroupShowUrl())
            ->with('status', $this->getEntityName().' moved to trash.');
    }

    /**
     * Override restore to redirect to group show page.
     */
    public function restore(int|string $id): RedirectResponse
    {
        $model = $this->service()->restore((int) $id);

        $this->logActivity($model, ActivityAction::RESTORE, $this->getEntityName().' restored');

        return redirect($this->getGroupShowUrl())
            ->with('status', $this->getEntityName().' restored successfully.');
    }

    /**
     * Override forceDelete to redirect to group show page.
     */
    public function forceDelete(int|string $id): RedirectResponse
    {
        $model = $this->service()->findModel((int) $id, true);
        $this->service()->forceDelete((int) $id);

        $this->logActivity($model, ActivityAction::FORCE_DELETE, $this->getEntityName().' permanently deleted');

        return redirect($this->getGroupShowUrl())
            ->with('status', $this->getEntityName().' permanently deleted.');
    }

    protected function service(): GroupItemService
    {
        return $this->groupItemService;
    }

    protected function inertiaPage(): string
    {
        return 'masters/group-items';
    }

    /**
     * Get the group ID from route
     */
    protected function getGroupId(): int
    {
        return (int) request()->route('group');
    }

    /**
     * Get the redirect URL to group show page
     */
    protected function getGroupShowUrl(): string
    {
        return route('app.masters.groups.show', ['id' => $this->getGroupId()]);
    }

    protected function getFormViewData(Model $model): array
    {
        if (! $model instanceof GroupItem) {
            $model = new GroupItem;
        }

        // Get group from route or model
        $groupId = request()->route('group') ?? $model->group_id;
        $group = Group::query()->findOrFail($groupId);

        // Get potential parent items for hierarchy (excluding current item if editing)
        $parentItemsQuery = $group->items()->where('status', '!=', 'trash');
        if ($model->exists) {
            $parentItemsQuery->where('id', '!=', $model->id);
        }

        $parentItems = $parentItemsQuery->pluck('name', 'id');

        // Build form config
        $isEdit = $model->exists;
        $formConfig = [
            'action' => $isEdit
                ? route('app.masters.groups.items.update', ['group' => $group->id, 'id' => $model->id])
                : route('app.masters.groups.items.store', ['group' => $group->id]),
            'method' => $isEdit ? 'PUT' : 'POST',
            'submitIcon' => $isEdit ? 'ri-save-line' : 'ri-add-line',
            'submitText' => $isEdit ? 'Update Item' : 'Create Item',
            'cancelUrl' => route('app.masters.groups.show', ['id' => $group->id]),
        ];

        return [
            'parentGroup' => $group,
            'parentItems' => $parentItems,
            'formConfig' => $formConfig,
            'statusOptions' => $this->groupItemService->getStatusOptions(),
        ];
    }
}
