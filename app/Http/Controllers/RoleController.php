<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkDestroyRolesRequest;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $filters = $this->filters($request);

        $roles = Role::query()
            ->withCount(['permissions', 'users'])
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $query->where(function (Builder $query) use ($filters): void {
                    $query
                        ->where('name', 'ilike', sprintf('%%%s%%', $filters['search']))
                        ->orWhere('display_name', 'ilike', sprintf('%%%s%%', $filters['search']))
                        ->orWhere('description', 'ilike', sprintf('%%%s%%', $filters['search']));
                });
            })
            ->when($filters['scope'] === 'system', fn (Builder $query) => $query->where('is_system', true))
            ->when($filters['scope'] === 'custom', fn (Builder $query) => $query->where('is_system', false))
            ->tap(fn (Builder $query) => $this->applySort($query, $filters['sort'], $filters['direction']))
            ->paginate($filters['per_page'])
            ->withQueryString()
            ->through(fn (Role $role): array => $this->roleListItem($role));

        return Inertia::render('roles/index', [
            'roles' => $roles,
            'filters' => $filters,
            'stats' => [
                'total' => Role::query()->count(),
                'system' => Role::query()->where('is_system', true)->count(),
                'custom' => Role::query()->where('is_system', false)->count(),
            ],
            'status' => session('status'),
            'error' => session('error'),
        ]);
    }

    public function bulkDestroy(BulkDestroyRolesRequest $request): RedirectResponse
    {
        $roleIds = collect($request->validated('role_ids'))
            ->map(fn (mixed $roleId): int => (int) $roleId)
            ->unique()
            ->values();

        $roles = Role::query()
            ->withCount('users')
            ->whereIn('id', $roleIds)
            ->get();

        $deletedCount = 0;
        $systemCount = 0;
        $assignedCount = 0;

        foreach ($roles as $role) {
            if ($role->is_system) {
                $systemCount++;

                continue;
            }

            if ($role->users_count > 0) {
                $assignedCount++;

                continue;
            }

            $role->delete();
            $deletedCount++;
        }

        $response = back();

        if ($deletedCount > 0) {
            $response = $response->with(
                'status',
                sprintf('Deleted %d %s.', $deletedCount, Str::plural('role', $deletedCount)),
            );
        }

        if ($systemCount > 0 || $assignedCount > 0) {
            $blockedParts = [];

            if ($systemCount > 0) {
                $blockedParts[] = sprintf('%d system %s', $systemCount, Str::plural('role', $systemCount));
            }

            if ($assignedCount > 0) {
                $blockedParts[] = sprintf('%d assigned %s', $assignedCount, Str::plural('role', $assignedCount));
            }

            $response = $response->with(
                'error',
                sprintf('Skipped %s.', implode(' and ', $blockedParts)),
            );
        }

        return $response;
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('roles/create', [
            'initialValues' => $this->initialValues(),
            'permissionGroups' => $this->permissionGroups(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $role = Role::query()->create([
            'name' => $validated['name'] ?: $this->generateRoleName($validated['display_name']),
            'guard_name' => 'web',
            'display_name' => $validated['display_name'],
            'description' => $validated['description'] ?: null,
            'is_system' => false,
        ]);

        $role->syncPermissions($validated['permissions'] ?? []);

        return to_route('roles.index')->with('status', sprintf('Role "%s" created.', $role->display_name ?: $role->name));
    }

    /**
     * Display the specified resource.
     */
    public function edit(Role $role): Response
    {
        $role->loadMissing('permissions');
        $role->loadCount(['permissions', 'users']);

        return Inertia::render('roles/edit', [
            'role' => [
                ...$this->initialValues($role),
                'id' => $role->id,
                'is_system' => $role->is_system,
                'users_count' => $role->users_count,
                'permissions_count' => $role->permissions_count,
            ],
            'initialValues' => $this->initialValues($role),
            'permissionGroups' => $this->permissionGroups(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $validated = $request->validated();

        $role->fill([
            'name' => $role->is_system
                ? $role->name
                : ($validated['name'] ?: $this->generateRoleName($validated['display_name'])),
            'display_name' => $validated['display_name'],
            'description' => $validated['description'] ?: null,
        ]);

        $role->save();
        $role->syncPermissions($validated['permissions'] ?? []);

        return to_route('roles.index')->with('status', sprintf('Role "%s" updated.', $role->display_name ?: $role->name));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Role $role): RedirectResponse
    {
        if ($role->is_system) {
            return back()->with('error', 'System roles cannot be deleted.');
        }

        if ($role->users()->exists()) {
            return back()->with('error', 'This role still has assigned users. Reassign them before deleting the role.');
        }

        $roleName = $role->display_name ?: $role->name;
        $role->delete();

        return to_route('roles.index')->with('status', sprintf('Role "%s" deleted.', $roleName));
    }

    /**
     * @return array<string, mixed>
     */
    protected function roleListItem(Role $role): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'display_name' => $role->display_name ?: Str::headline($role->name),
            'description' => $role->description,
            'is_system' => $role->is_system,
            'permissions_count' => (int) $role->permissions_count,
            'users_count' => (int) $role->users_count,
        ];
    }

    /**
     * @return array{search: string, scope: 'all'|'system'|'custom', sort: 'role'|'permissions'|'users'|'status', direction: 'asc'|'desc', per_page: int, view: 'table'|'cards'}
     */
    protected function filters(Request $request): array
    {
        $sort = (string) $request->query('sort', 'role');
        $direction = (string) $request->query('direction', 'asc');
        $perPage = (int) $request->integer('per_page', 10);
        $view = (string) $request->query('view', 'table');

        return [
            'search' => trim((string) $request->query('search', '')),
            'scope' => in_array((string) $request->query('scope', 'all'), ['all', 'system', 'custom'], true)
                ? (string) $request->query('scope', 'all')
                : 'all',
            'sort' => in_array($sort, ['role', 'permissions', 'users', 'status'], true)
                ? $sort
                : 'role',
            'direction' => in_array($direction, ['asc', 'desc'], true)
                ? $direction
                : 'asc',
            'per_page' => in_array($perPage, [10, 25, 50, 100], true)
                ? $perPage
                : 10,
            'view' => in_array($view, ['table', 'cards'], true)
                ? $view
                : 'table',
        ];
    }

    protected function applySort(Builder $query, string $sort, string $direction): void
    {
        $direction = $direction === 'desc' ? 'desc' : 'asc';

        match ($sort) {
            'permissions' => $query
                ->orderBy('permissions_count', $direction)
                ->orderByDesc('is_system')
                ->orderByRaw('LOWER(COALESCE(display_name, name)) ASC'),
            'users' => $query
                ->orderBy('users_count', $direction)
                ->orderByDesc('is_system')
                ->orderByRaw('LOWER(COALESCE(display_name, name)) ASC'),
            'status' => $query
                ->orderBy('is_system', $direction)
                ->orderByRaw('LOWER(COALESCE(display_name, name)) ASC'),
            default => $query
                ->orderByDesc('is_system')
                ->orderByRaw(sprintf('LOWER(COALESCE(display_name, name)) %s', strtoupper($direction))),
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function initialValues(?Role $role = null): array
    {
        return [
            'name' => $role instanceof Role ? $role->name : '',
            'display_name' => $role instanceof Role ? ($role->display_name ?? '') : '',
            'description' => $role instanceof Role ? ($role->description ?? '') : '',
            'permissions' => $role?->permissions->pluck('id')->values()->all() ?? [],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function permissionGroups(): array
    {
        return Permission::query()
            ->orderByRaw('LOWER(COALESCE("group", module_slug, name))')
            ->orderByRaw('LOWER(COALESCE(display_name, name))')
            ->get()
            ->groupBy(fn (Permission $permission): string => $permission->group ?: 'other')
            ->map(function (Collection $permissions, string $group): array {
                return [
                    'group' => $group,
                    'label' => Str::headline(str_replace('_', ' ', $group)),
                    'permissions' => $permissions
                        ->map(fn (Permission $permission): array => [
                            'id' => $permission->id,
                            'name' => $permission->name,
                            'display_name' => $permission->display_name ?: Str::headline($permission->name),
                            'description' => $permission->description,
                            'module_slug' => $permission->module_slug,
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();
    }

    protected function generateRoleName(string $displayName): string
    {
        return (string) Str::of($displayName)->trim()->lower()->snake();
    }
}
