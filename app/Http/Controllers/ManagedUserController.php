<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateManagedUserRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ManagedUserController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'search' => trim((string) $request->string('search')),
            'role' => trim((string) $request->string('role')),
            'status' => in_array((string) $request->string('status'), ['all', 'active', 'inactive'], true)
                ? (string) $request->string('status')
                : 'all',
        ];

        $users = User::query()
            ->with('roles')
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $query->where(function (Builder $query) use ($filters): void {
                    $query
                        ->where('name', 'ilike', sprintf('%%%s%%', $filters['search']))
                        ->orWhere('email', 'ilike', sprintf('%%%s%%', $filters['search']));
                });
            })
            ->when($filters['role'] !== '', function (Builder $query) use ($filters): void {
                $query->whereHas('roles', function (Builder $query) use ($filters): void {
                    $query->where('name', $filters['role']);
                });
            })
            ->when($filters['status'] === 'active', fn (Builder $query) => $query->where('active', true))
            ->when($filters['status'] === 'inactive', fn (Builder $query) => $query->where('active', false))
            ->orderByRaw('LOWER(name)')
            ->get()
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'active' => (bool) $user->active,
                'email_verified_at' => $user->email_verified_at,
                'roles' => $user->roles
                    ->map(function (mixed $role): array {
                        /** @var Role $role */

                        return [
                            'id' => $role->id,
                            'name' => $role->name,
                            'display_name' => $role->display_name ?: Str::headline($role->name),
                        ];
                    })
                    ->values()
                    ->all(),
            ])
            ->values();

        return Inertia::render('users/index', [
            'users' => $users,
            'filters' => $filters,
            'stats' => [
                'total' => User::query()->count(),
                'active' => User::query()->where('active', true)->count(),
                'inactive' => User::query()->where('active', false)->count(),
            ],
            'roles' => $this->roleOptions(),
            'status' => session('status'),
            'error' => session('error'),
        ]);
    }

    public function edit(User $user): Response
    {
        $user->loadMissing('roles');

        return Inertia::render('users/edit', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'active' => (bool) $user->active,
                'email_verified_at' => $user->email_verified_at,
                'roles' => $user->roles
                    ->map(function (mixed $role): array {
                        /** @var Role $role */

                        return [
                            'id' => $role->id,
                            'name' => $role->name,
                            'display_name' => $role->display_name ?: Str::headline($role->name),
                        ];
                    })
                    ->values()
                    ->all(),
            ],
            'initialValues' => [
                'name' => $user->name,
                'email' => $user->email,
                'active' => (bool) $user->active,
                'roles' => $user->roles->pluck('id')->values()->all(),
            ],
            'availableRoles' => $this->roleOptions(),
        ]);
    }

    public function update(UpdateManagedUserRequest $request, User $user): RedirectResponse
    {
        $validated = $request->validated();

        if (
            $user->hasRole(Role::SUPER_USER)
            && ! in_array($this->superUserRoleId(), $validated['roles'], true)
            && User::role(Role::SUPER_USER)->count() <= 1
        ) {
            return back()->with('error', 'The last super user role assignment cannot be removed.');
        }

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'active' => $validated['active'],
        ]);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();
        $user->syncRoles($validated['roles']);

        return to_route('users.index')->with('status', sprintf('Updated %s.', $user->name));
    }

    /**
     * @return array<int, array{id:int,name:string,display_name:string,is_system:bool}>
     */
    protected function roleOptions(): array
    {
        return Role::query()
            ->orderByDesc('is_system')
            ->orderByRaw('LOWER(COALESCE(display_name, name))')
            ->get()
            ->map(fn (Role $role): array => [
                'id' => $role->id,
                'name' => $role->name,
                'display_name' => $role->display_name ?: Str::headline($role->name),
                'is_system' => $role->is_system,
            ])
            ->values()
            ->all();
    }

    protected function superUserRoleId(): int
    {
        return Role::query()
            ->where('name', Role::SUPER_USER)
            ->value('id');
    }
}
