<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\ScaffoldServiceInterface;
use App\Definitions\RoleDefinition;
use App\Enums\Status;
use App\Http\Resources\RoleResource;
use App\Models\Permission;
use App\Models\Role;
use App\Scaffold\ScaffoldDefinition;
use App\Traits\Scaffoldable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Role Service - Handles role management logic
 */
class RoleService implements ScaffoldServiceInterface
{
    use Scaffoldable;

    // ================================================================
    // SCAFFOLD DEFINITION
    // ================================================================

    public function getScaffoldDefinition(): ScaffoldDefinition
    {
        return new RoleDefinition;
    }

    // ================================================================
    // STATISTICS (for tab counts)
    // ================================================================

    public function getStatistics(): array
    {
        // Apply visibility scope to hide super_user role from statistics for non-super users
        return [
            'total' => Role::visibleToCurrentUser()->whereNull('deleted_at')->count(),
            'active' => Role::visibleToCurrentUser()->where('status', Status::ACTIVE)->whereNull('deleted_at')->count(),
            'inactive' => Role::visibleToCurrentUser()->where('status', 'inactive')->whereNull('deleted_at')->count(),
            'trash' => Role::visibleToCurrentUser()->onlyTrashed()->count(),
        ];
    }

    // ================================================================
    // CRUD OVERRIDES (Permissions Sync)
    // ================================================================

    /**
     * Create a new role and sync permissions.
     */
    public function create(array $data): Model
    {
        $preparedData = $this->prepareCreateData($data);

        $role = new Role;
        $role->fill($preparedData);
        $role->save();

        if (isset($data['permissions'])) {
            $role->permissions()->sync($data['permissions']);
        }

        return $role;
    }

    /**
     * Update a role and sync permissions.
     */
    public function update(Model $model, array $data): Model
    {
        if (! $model instanceof Role) {
            $model = Role::query()->findOrFail((int) $model->getKey());
        }

        $preparedData = $this->prepareUpdateData($data);
        $model->update($preparedData);

        if (isset($data['permissions'])) {
            $model->permissions()->sync($data['permissions']);
        }

        return $model;
    }

    /**
     * Get the count of users currently assigned to this role.
     */
    public function getAssignedUsersCount(Role $role): int
    {
        return $role->users()->count();
    }

    // ================================================================
    // OPTIONS & HELPERS
    // ================================================================

    public function getStatusOptions(): array
    {
        return [
            ['value' => 'active', 'label' => 'Active'],
            ['value' => 'inactive', 'label' => 'Inactive'],
        ];
    }

    public function getAllPermissions(): Collection
    {
        return Permission::all();
    }

    protected function getResourceClass(): ?string
    {
        return RoleResource::class;
    }

    // ================================================================
    // EAGER LOADING
    // ================================================================

    protected function getEagerLoadRelationships(): array
    {
        return [
            'createdBy', // Assuming these exist from AuditableTrait
            'updatedBy',
        ];
    }

    // ================================================================
    // QUERY CUSTOMIZATION
    // ================================================================

    protected function buildListQuery(Request $request): Builder
    {
        $query = Role::query();

        // Apply visibility scope to hide super_user role from non-super users
        $query->visibleToCurrentUser();

        // Get status from request or route
        $status = $request->input('status') ?? $request->route('status') ?? 'all';

        // Handle status filtering with soft deletes
        if ($status === 'trash') {
            $query->onlyTrashed();
        } elseif ($status === 'active') {
            $query->where('status', Status::ACTIVE)->whereNull('deleted_at');
        } elseif ($status === 'inactive') {
            $query->where('status', Status::INACTIVE)->whereNull('deleted_at');
        } else {
            // 'all' - only non-deleted
            $query->whereNull('deleted_at');
        }

        // Merge route status into request for filters
        if (! $request->has('status') && $request->route('status')) {
            $request->merge(['status' => $status]);
        }

        // Apply standard scaffold methods
        $this->applyEagerLoading($query);
        $this->applySearch($query, $request);
        $this->applyFilters($query, $request);
        $this->applySorting($query, $request);
        $this->customizeListQuery($query, $request);

        return $query;
    }

    protected function customizeListQuery(Builder $query, Request $request): void
    {
        // Add counts for the index page columns
        $query->withCount(['permissions', 'users']);
    }

    protected function beforeDelete(Model $model): void
    {
        if ($model instanceof Role) {
            $this->assertRoleHasNoUsers($model);
        }
    }

    protected function beforeForceDelete(Model $model): void
    {
        if ($model instanceof Role) {
            $this->assertRoleHasNoUsers($model);
        }
    }

    /**
     * Prevent deletion if the role still has assigned users.
     */
    protected function assertRoleHasNoUsers(Role $role): void
    {
        $count = $this->getAssignedUsersCount($role);

        if ($count > 0) {
            $label = $count === 1 ? '1 user' : $count.' users';

            throw new RuntimeException(
                sprintf('Cannot delete this role because it has %s. Please reassign or remove the users first.', $label)
            );
        }
    }

    // ================================================================
    // DATA PREPARATION
    // ================================================================

    protected function prepareCreateData(array $data): array
    {
        $data['guard_name'] ??= 'web';

        // Auto-generate name from display_name if not provided
        if (empty($data['name']) && ! empty($data['display_name'])) {
            $data['name'] = $this->generateRoleName($data['display_name']);
        }

        return $data;
    }

    protected function prepareUpdateData(array $data): array
    {
        $data['guard_name'] ??= 'web';

        // Auto-generate name from display_name if not provided
        if (empty($data['name']) && ! empty($data['display_name'])) {
            $data['name'] = $this->generateRoleName($data['display_name']);
        }

        return $data;
    }

    private function generateRoleName(string $displayName): string
    {
        return strtolower((string) preg_replace('/[^a-zA-Z0-9]/', '_', $displayName));
    }
}
