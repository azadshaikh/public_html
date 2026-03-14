<?php

namespace App\Http\Controllers;

use App\Enums\Status;
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

    // ================================================================
    // OVERRIDE INDEX TO PROVIDE CLEAN PROPS FOR REACT DATAGRID
    // ================================================================

    public function index(Request $request): Response|RedirectResponse
    {
        $this->enforcePermission('view');

        $status = $request->input('status') ?? $request->route('status') ?? 'all';
        $perPage = $this->service()->getScaffoldDefinition()->getPerPage();

        return Inertia::render($this->inertiaPage().'/index', [
            'roles' => $this->roleService->getPaginatedRoles($request),
            'statistics' => $this->roleService->getStatistics(),
            'filters' => [
                'search' => $request->input('search', ''),
                'status' => $status,
                'sort' => $request->input('sort', 'display_name'),
                'direction' => $request->input('direction', 'asc'),
                'per_page' => (int) $request->input('per_page', $perPage),
                'view' => $request->input('view', 'table'),
            ],
            'status' => session('status'),
            'error' => session('error'),
        ]);
    }

    // ================================================================
    // OVERRIDE SHOW TO PROVIDE RICH ROLE DETAIL
    // ================================================================

    public function show(int|string $id): Response
    {
        $role = Role::withTrashed()
            ->with(['permissions', 'notes', 'createdBy', 'updatedBy'])
            ->withCount(['users', 'permissions'])
            ->findOrFail((int) $id);

        return Inertia::render($this->inertiaPage().'/show', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'display_name' => $role->display_name,
                'guard_name' => $role->guard_name,
                'status' => $role->status instanceof Status ? $role->status->value : (string) $role->status,
                'status_label' => $role->status instanceof Status ? $role->status->label() : ucfirst((string) $role->status),
                'status_badge' => $role->status instanceof Status ? $role->status->badge() : 'outline',
                'is_system' => $role->id === (int) config('permission.super_user_role_id', 1),
                'is_trashed' => $role->trashed(),
                'trashed_at' => $role->deleted_at?->toISOString(),
                'trashed_at_formatted' => $role->deleted_at ? app_date_time_format($role->deleted_at, 'datetime') : null,
                'users_count' => $role->users_count,
                'permissions_count' => $role->permissions_count,
                'notes_count' => $role->notes->count(),
                'created_at' => $role->created_at?->toISOString(),
                'created_at_formatted' => app_date_time_format($role->created_at, 'datetime'),
                'updated_at' => $role->updated_at?->toISOString(),
                'updated_at_formatted' => app_date_time_format($role->updated_at, 'datetime'),
                'created_by' => $role->createdBy?->name ?? 'System',
                'updated_by' => $role->updatedBy?->name ?? 'System',
            ],
            'permissionGroups' => $this->roleService->getGroupedPermissionsForRole($role),
            'status' => session('status'),
            'error' => session('error'),
        ]);
    }

    // ================================================================
    // FORM DATA
    // ================================================================

    /**
     * Provide initialValues and permissionGroups for the React form.
     */
    protected function getFormViewData(Model $model): array
    {
        /** @var Role $role */
        $role = $model;

        return [
            'initialValues' => [
                'name' => $role->exists ? ($role->name ?? '') : '',
                'display_name' => $role->exists ? ($role->display_name ?? '') : '',
                'status' => $role->exists
                    ? ($role->status instanceof Status ? $role->status->value : (string) ($role->status ?? Status::ACTIVE->value))
                    : Status::ACTIVE->value,
                'permissions' => $role->exists ? $role->permissions()->pluck('permissions.id')->toArray() : [],
            ],
            'statusOptions' => $this->roleService->getStatusOptions(),
            'permissionGroups' => $this->roleService->getAllPermissionsGrouped(),
        ];
    }

    /**
     * Shape the role for the edit form's RoleEditingTarget.
     */
    protected function transformModelForEdit(Model $model): array
    {
        /** @var Role $role */
        $role = $model;
        $role->loadCount(['users', 'permissions']);

        return [
            'id' => $role->id,
            'name' => $role->name,
            'display_name' => $role->display_name,
            'status' => $role->status instanceof Status ? $role->status->value : (string) ($role->status ?? Status::ACTIVE->value),
            'permissions' => $role->permissions()->pluck('permissions.id')->toArray(),
            'is_system' => $role->id === (int) config('permission.super_user_role_id', 1),
            'users_count' => $role->users_count,
            'permissions_count' => $role->permissions_count,
        ];
    }

    // ================================================================
    // PROTECTION OVERRIDES
    // ================================================================

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
