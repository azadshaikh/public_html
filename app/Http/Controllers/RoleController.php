<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Scaffold\ScaffoldController;
use App\Services\RoleService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class RoleController extends ScaffoldController implements HasMiddleware
{
    public function __construct(
        private readonly RoleService $roleService
    ) {}

    public static function middleware(): array
    {
        return [
            ...resolve(RoleService::class)->getScaffoldDefinition()->getMiddleware(),
        ];
    }

    /**
     * Override to provide additional form data for create and edit.
     */
    protected function getFormViewData(Model $model): array
    {
        return [
            'statusOptions' => $this->roleService->getStatusOptions(),
            'permissions' => $this->roleService->getAllPermissions(),
        ];
    }

    /**
     * Override show to eager load relations.
     */
    public function show(int|string $id): Response
    {
        $role = Role::withTrashed()
            ->with(['permissions', 'notes', 'createdBy', 'updatedBy'])
            ->findOrFail((int) $id);

        return Inertia::render($this->inertiaPage().'/show', array_merge(
            ['role' => $role],
            $this->getShowViewData($role),
        ));
    }

    /**
     * Overridden to protect super user role (ID 1) from being trashed.
     */
    public function destroy(int|string $id): RedirectResponse
    {
        if ((int) $id === 1) {
            return back()->with('error', 'Cannot delete the super user role.');
        }

        try {
            return parent::destroy($id);
        } catch (RuntimeException $runtimeException) {
            return back()->with('error', $runtimeException->getMessage());
        }
    }

    /**
     * Overridden to protect super user role (ID 1) from permanent deletion.
     */
    public function forceDelete(int|string $id): RedirectResponse
    {
        try {
            return parent::forceDelete($id);
        } catch (RuntimeException $runtimeException) {
            return back()->with('error', $runtimeException->getMessage());
        }
    }

    /**
     * Handle bulk actions.
     * Overridden to protect super user role (ID 1) from destructive actions.
     */
    public function bulkAction(Request $request): RedirectResponse
    {
        $action = $request->input('action');
        $ids = array_map(intval(...), $request->input('ids', []));

        $protectedActions = ['delete', 'force_delete'];
        if (in_array($action, $protectedActions) && in_array(1, $ids, true)) {
            $ids = array_values(array_filter($ids, fn ($id): bool => $id !== 1));
            $request->merge(['ids' => $ids]);

            if ($ids === []) {
                return back()->with('error', 'Cannot delete the super user role.');
            }
        }

        try {
            return parent::bulkAction($request);
        } catch (RuntimeException $runtimeException) {
            return back()->with('error', $runtimeException->getMessage());
        }
    }

    protected function service(): RoleService
    {
        return $this->roleService;
    }

    protected function inertiaPage(): string
    {
        return 'roles';
    }
}
