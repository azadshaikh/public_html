<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Scaffold\ScaffoldController;
use App\Services\RoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\View\View;
use RuntimeException;

class RolesController extends ScaffoldController implements HasMiddleware
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
     * Override create to provide additional form data
     */
    public function create(): View
    {
        return parent::create()->with([
            'statusOptions' => $this->roleService->getStatusOptions(),
            'permissions' => $this->roleService->getAllPermissions(),
        ]);
    }

    /**
     * Override edit to provide additional form data
     */
    public function edit(int|string $id): View
    {
        return parent::edit($id)->with([
            'statusOptions' => $this->roleService->getStatusOptions(),
            'permissions' => $this->roleService->getAllPermissions(),
        ]);
    }

    /**
     * Override show to eager load notes
     */
    public function show(int|string $id): View|JsonResponse
    {
        $role = Role::withTrashed()
            ->with(['permissions', 'notes', 'createdBy', 'updatedBy'])
            ->findOrFail((int) $id);

        if (request()->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'data' => $role,
            ]);
        }

        return view($this->service()->getScaffoldDefinition()->getShowView(), [
            'role' => $role,
        ]);
    }

    // ================================================================
    // OVERRIDE DESTROY TO PROTECT SUPER USER ROLE
    // ================================================================

    /**
     * Remove the specified resource from storage.
     * Overridden to protect super user role (ID 1) from being trashed or deleted.
     */
    public function destroy(Request $request, int|string $id): RedirectResponse|JsonResponse
    {
        // Protect super user role (ID 1) from being deleted
        if ((int) $id === 1) {
            $message = 'Cannot delete the super user role.';

            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $message,
                ], 403);
            }

            return back()->with('error', $message);
        }

        try {
            return parent::destroy($request, $id);
        } catch (RuntimeException $runtimeException) {
            return $this->deletionBlockedResponse($request, $runtimeException->getMessage());
        }
    }

    public function forceDelete(Request $request, int|string $id): RedirectResponse|JsonResponse
    {
        try {
            return parent::forceDelete($request, $id);
        } catch (RuntimeException $runtimeException) {
            return $this->deletionBlockedResponse($request, $runtimeException->getMessage());
        }
    }

    // ================================================================
    // OVERRIDE BULK ACTION TO PROTECT SUPER USER ROLE
    // ================================================================

    /**
     * Handle bulk actions.
     * Overridden to protect super user role (ID 1) from destructive actions.
     */
    public function bulkAction(Request $request): JsonResponse
    {
        $action = $request->input('action');
        $ids = array_map(intval(...), $request->input('ids', []));

        // Protect super user role (ID 1) from delete/force_delete actions
        $protectedActions = ['delete', 'force_delete'];
        if (in_array($action, $protectedActions) && in_array(1, $ids, true)) {
            // Remove super user role from the list
            $ids = array_values(array_filter($ids, fn ($id): bool => $id !== 1));
            $request->merge(['ids' => $ids]);

            if ($ids === []) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot delete the super user role.',
                ], 403);
            }
        }

        try {
            return parent::bulkAction($request);
        } catch (RuntimeException $runtimeException) {
            return response()->json([
                'status' => 'error',
                'message' => $runtimeException->getMessage(),
                'affected' => 0,
            ], 422);
        }
    }

    protected function service(): RoleService
    {
        return $this->roleService;
    }

    protected function deletionBlockedResponse(Request $request, string $message): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'error',
                'message' => $message,
            ], 422);
        }

        return back()->with('error', $message);
    }
}
