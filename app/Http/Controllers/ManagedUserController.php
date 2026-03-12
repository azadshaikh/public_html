<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreManagedUserRequest;
use App\Http\Requests\UpdateManagedUserRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ManagedUserController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $this->filters($request);

        $users = User::query()
            ->with('roles')
            ->withCount('roles')
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
            ->when($filters['verification'] === 'verified', fn (Builder $query) => $query->whereNotNull('email_verified_at'))
            ->when($filters['verification'] === 'unverified', fn (Builder $query) => $query->whereNull('email_verified_at'))
            ->tap(fn (Builder $query) => $this->applySort($query, $filters['sort'], $filters['direction']))
            ->paginate($filters['per_page'])
            ->withQueryString()
            ->through(fn (User $user): array => $this->userPayload($user));

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

    public function create(): Response
    {
        return Inertia::render('users/create', [
            'initialValues' => $this->initialValues(),
            'availableRoles' => $this->roleOptions(),
        ]);
    }

    public function edit(User $user): Response
    {
        $user->loadMissing('roles');

        return Inertia::render('users/edit', [
            'user' => $this->userPayload($user),
            'initialValues' => $this->initialValues($user),
            'availableRoles' => $this->roleOptions(),
        ]);
    }

    public function store(StoreManagedUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'active' => $validated['active'],
            'password' => $validated['password'],
        ]);

        $user->syncRoles($validated['roles']);

        return to_route('users.index')->with('status', sprintf('Created %s.', $user->name));
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

        if (is_string($validated['password'] ?? null) && $validated['password'] !== '') {
            $user->password = $validated['password'];
        }

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();
        $user->syncRoles($validated['roles']);

        return to_route('users.index')->with('status', sprintf('Updated %s.', $user->name));
    }

    public function destroy(User $user): RedirectResponse
    {
        if (Auth::id() === $user->id) {
            return back()->with('error', 'Delete your own account from account settings instead of the user registry.');
        }

        if ($user->hasRole(Role::SUPER_USER) && User::role(Role::SUPER_USER)->count() <= 1) {
            return back()->with('error', 'The last super user account cannot be deleted.');
        }

        $userName = $user->name;
        $user->delete();

        return to_route('users.index')->with('status', sprintf('Deleted %s.', $userName));
    }

    /**
     * @return array<string, mixed>
     */
    protected function userPayload(User $user): array
    {
        return [
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
        ];
    }

    /**
     * @return array{search: string, role: string, status: 'all'|'active'|'inactive', verification: 'all'|'verified'|'unverified', sort: 'name'|'status'|'verification'|'roles', direction: 'asc'|'desc', per_page: int, view: 'table'|'cards'}
     */
    protected function filters(Request $request): array
    {
        $sort = (string) $request->query('sort', 'name');
        $direction = (string) $request->query('direction', 'asc');
        $perPage = (int) $request->integer('per_page', 10);
        $view = (string) $request->query('view', 'table');

        return [
            'search' => trim((string) $request->query('search', '')),
            'role' => trim((string) $request->query('role', '')),
            'status' => in_array((string) $request->query('status', 'all'), ['all', 'active', 'inactive'], true)
                ? (string) $request->query('status', 'all')
                : 'all',
            'verification' => in_array((string) $request->query('verification', 'all'), ['all', 'verified', 'unverified'], true)
                ? (string) $request->query('verification', 'all')
                : 'all',
            'sort' => in_array($sort, ['name', 'status', 'verification', 'roles'], true)
                ? $sort
                : 'name',
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
            'roles' => $query
                ->orderBy('roles_count', $direction)
                ->orderByRaw('LOWER(name) ASC'),
            'status' => $query
                ->orderBy('active', $direction)
                ->orderByRaw('LOWER(name) ASC'),
            'verification' => $query
                ->orderByRaw(
                    sprintf(
                        'CASE WHEN email_verified_at IS NULL THEN 1 ELSE 0 END %s',
                        strtoupper($direction),
                    ),
                )
                ->orderByRaw('LOWER(name) ASC'),
            default => $query->orderByRaw(sprintf('LOWER(name) %s', strtoupper($direction))),
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function initialValues(?User $user = null): array
    {
        $defaultRoleId = $this->defaultRoleId();

        return [
            'name' => $user instanceof User ? $user->name : '',
            'email' => $user instanceof User ? $user->email : '',
            'active' => $user instanceof User ? (bool) $user->active : true,
            'roles' => $user?->roles->pluck('id')->values()->all() ?? ($defaultRoleId ? [$defaultRoleId] : []),
            'password' => '',
            'password_confirmation' => '',
        ];
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

    protected function defaultRoleId(): ?int
    {
        return Role::query()
            ->where('name', 'user')
            ->value('id');
    }
}
