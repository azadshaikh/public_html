<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ActivityAction;
use App\Enums\NoteVisibility;
use App\Enums\Status;
use App\Http\Resources\NoteResource;
use App\Http\Resources\UserResource;
use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\User;
use App\Scaffold\ScaffoldController;
use App\Services\GeoIpService;
use App\Services\NoteService;
use App\Services\UserService;
use App\Support\CacheInvalidation;
use Exception;
use Illuminate\Auth\Events\Verified;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class UserController extends ScaffoldController implements HasMiddleware
{
    public function __construct(
        private readonly UserService $userService,
        private readonly GeoIpService $geoIpService,
        private readonly NoteService $noteService,
    ) {}

    // ================================================================
    // MIDDLEWARE (Permission control)
    // ================================================================

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view_users', only: ['index', 'show']),
            new Middleware('permission:add_users', only: ['create', 'store']),
            new Middleware('permission:edit_users', only: ['edit', 'update', 'verifyEmail', 'approve', 'suspend', 'ban', 'unban']),
            new Middleware('permission:delete_users', only: ['destroy', 'forceDelete']),
            new Middleware('permission:restore_users', only: ['restore']),
            // Note: stopImpersonating is NOT protected because the impersonated user may not have permissions
            new Middleware('permission:impersonate_users', only: ['impersonate']),
        ];
    }

    public function store(Request $request): RedirectResponse
    {
        $this->enforcePermission('add');

        $validatedData = $this->prepareValidatedUserData($request);
        $model = $this->service()->create($validatedData);

        CacheInvalidation::touchForModel($model, $this->getEntityName().' created');

        $this->handleCreationSideEffects($model);
        $this->logActivity($model, ActivityAction::CREATE, $this->getEntityName().' created successfully');

        return redirect()
            ->to($this->getAfterStoreRedirectUrl($model))
            ->with('status', $this->buildCreateSuccessMessage($model));
    }

    // ================================================================
    // OVERRIDE INDEX TO PROVIDE CLEAN PROPS FOR REACT DATAGRID
    // ================================================================

    public function index(Request $request): Response|RedirectResponse
    {
        $this->enforcePermission('view');

        $statistics = $this->userService->getStatistics();
        $autoApprove = filter_var(setting('registration_auto_approve', true), FILTER_VALIDATE_BOOLEAN);
        $status = $request->input('status') ?? $request->route('status') ?? 'all';
        $perPage = $this->service()->getScaffoldDefinition()->getPerPage();

        return Inertia::render($this->inertiaPage().'/index', [
            'config' => $this->userService->getScaffoldDefinition()->toInertiaConfig(),
            'users' => $this->userService->getPaginatedUsers($request),
            'statistics' => $statistics,
            'filters' => [
                'search' => $request->input('search', ''),
                'role_id' => $request->input('role_id', ''),
                'email_verified' => $request->input('email_verified', ''),
                'gender' => $request->input('gender', ''),
                'created_at' => $request->input('created_at', ''),
                'status' => $status,
                'sort' => $request->input('sort', 'created_at'),
                'direction' => $request->input('direction', 'desc'),
                'per_page' => (int) $request->input('per_page', $perPage),
                'view' => $request->input('view', 'table'),
            ],
            'roles' => $this->userService->getRoleFilterOptions(),
            'showPendingTab' => ! $autoApprove,
            'registrationSettings' => $this->userService->getRegistrationSettingsSummary($statistics),
            'status' => session('status'),
            'error' => session('error'),
        ]);
    }

    // ================================================================
    // OVERRIDE SHOW TO ADD ACTIVITY LOGS FOR TABS
    // ================================================================

    public function show(int|string $id): Response
    {
        $user = User::withTrashed()
            ->with(['roles', 'primaryAddress'])
            ->findOrFail((int) $id);

        $userActivities = ActivityLog::query()
            ->where('subject_type', User::class)
            ->where('subject_id', $user->id)
            ->with('causer')
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (ActivityLog $activity): array => [
                'id' => $activity->id,
                'description' => $activity->description,
                'event' => $activity->event,
                'causer_name' => $activity->causer?->name ?? 'System',
                'properties' => $activity->properties,
                'created_at' => $activity->created_at?->toISOString(),
                'created_at_human' => $activity->created_at?->diffForHumans(),
            ]);

        return Inertia::render($this->inertiaPage().'/show', [
            'user' => (new UserResource($user))->toArray(request()),
            'userActivities' => $userActivities,
            'notes' => NoteResource::collection($this->noteService->getAllForModel($user))->resolve(request()),
            'noteTarget' => [
                'type' => User::class,
                'id' => $user->id,
            ],
            'noteVisibilityOptions' => NoteVisibility::options(),
            'status' => session('status'),
            'error' => session('error'),
        ]);
    }

    // ================================================================
    // OVERRIDE UPDATE TO PROTECT SUPER USER
    // ================================================================

    /**
     * Update the specified resource in storage.
     * Overridden to protect super user (ID 1) from status changes.
     */
    public function update(Request $request, int|string $id): RedirectResponse
    {
        $user = User::query()->findOrFail((int) $id);

        // Protect super user (ID 1) from being banned or suspended via edit form
        if ($user->id === 1 && $request->has('status')) {
            $restrictedStatuses = [Status::BANNED->value, Status::SUSPENDED->value];
            if (in_array($request->input('status'), $restrictedStatuses, true)) {
                return back()->withInput()->with('error', 'Cannot set super user status to banned or suspended.');
            }
        }

        $this->enforcePermission('edit');

        $previousValues = $this->capturePreviousValues($user);
        $validatedData = $this->prepareValidatedUserData($request, $user);

        $updatedModel = $this->service()->update($user, $validatedData);

        CacheInvalidation::touchForModel($updatedModel, $this->getEntityName().' updated', $previousValues);

        $this->handleUpdateSideEffectsWithPrevious($updatedModel, $previousValues);
        $this->logActivityWithPreviousValues(
            $updatedModel,
            ActivityAction::UPDATE,
            $this->getEntityName().' updated successfully',
            $previousValues
        );

        return to_route($this->scaffold()->getEditRoute(), $updatedModel)
            ->with('status', $this->buildUpdateSuccessMessage($updatedModel));
    }

    // ================================================================
    // OVERRIDE DESTROY TO PROTECT SUPER USER
    // ================================================================

    /**
     * Remove the specified resource from storage.
     * Overridden to protect super user (ID 1) from being trashed or deleted.
     */
    public function destroy(int|string $id): RedirectResponse
    {
        if ((int) $id === 1) {
            return back()->with('error', 'Cannot delete the super user account.');
        }

        return parent::destroy($id);
    }

    // ================================================================
    // USER-SPECIFIC ACTIONS
    // ================================================================

    /**
     * Manually verify a user's email.
     */
    public function verifyEmail(User $user): RedirectResponse
    {
        if ($user->hasVerifiedEmail()) {
            return back()->with('info', __('auth.email_already_verified'));
        }

        $user->forceFill([
            'email_verified_at' => now(),
        ])->save();

        event(new Verified($user));

        $this->logActivity(
            $user,
            ActivityAction::UPDATE,
            sprintf('Manually verified email for user: %s (%s)', $user->name, $user->email),
            ['action' => 'verify_email', 'user_id' => $user->id]
        );

        return back()->with('success', __('auth.email_marked_verified'));
    }

    /**
     * Approve a pending user.
     */
    public function approve(User $user): RedirectResponse
    {
        if ($user->status !== Status::PENDING->value) {
            return back()->with('info', 'User is not pending approval.');
        }

        $this->userService->approveUser($user);

        $this->logActivity(
            $user,
            ActivityAction::UPDATE,
            sprintf('Approved user: %s (%s)', $user->name, $user->email),
            ['action' => 'approve', 'user_id' => $user->id]
        );

        return back()->with('success', 'User has been approved and activated.');
    }

    /**
     * Suspend a user.
     */
    public function suspend(User $user): RedirectResponse
    {
        if ($user->id === 1) {
            return back()->with('error', 'Cannot suspend the super user account.');
        }

        $this->userService->suspendUser($user);

        $this->logActivity(
            $user,
            ActivityAction::UPDATE,
            sprintf('Suspended user: %s (%s)', $user->name, $user->email),
            ['action' => 'suspend', 'user_id' => $user->id]
        );

        return back()->with('status', sprintf('User %s has been suspended.', $user->name));
    }

    /**
     * Ban a user.
     */
    public function ban(User $user): RedirectResponse
    {
        if ($user->id === 1) {
            return back()->with('error', 'Cannot ban the super user account.');
        }

        $this->userService->banUser($user);

        $this->logActivity(
            $user,
            ActivityAction::UPDATE,
            sprintf('Banned user: %s (%s)', $user->name, $user->email),
            ['action' => 'ban', 'user_id' => $user->id]
        );

        return back()->with('status', sprintf('User %s has been banned.', $user->name));
    }

    /**
     * Unban a user.
     */
    public function unban(User $user): RedirectResponse
    {
        $this->userService->unbanUser($user);

        $this->logActivity(
            $user,
            ActivityAction::UPDATE,
            sprintf('Unbanned user: %s (%s)', $user->name, $user->email),
            ['action' => 'unban', 'user_id' => $user->id]
        );

        return back()->with('status', sprintf('User %s has been unbanned.', $user->name));
    }

    /**
     * Impersonate a user.
     */
    public function impersonate(int $id): RedirectResponse|HttpResponse
    {
        try {
            if (session()->has('impersonator_id')) {
                return to_route('dashboard')
                    ->with('info', [
                        'title' => 'Impersonation Already Active',
                        'message' => 'Stop the current impersonation session before starting another one.',
                    ]);
            }

            $user = User::query()->where('id', $id)->withTrashed()->first();

            if (! $user) {
                return to_route('app.users.index')
                    ->with('error', [
                        'title' => 'User Not Found',
                        'message' => 'The user you are trying to impersonate does not exist.',
                    ]);
            }

            if ((int) Auth::id() === $user->id) {
                return to_route('app.users.index')
                    ->with('info', [
                        'title' => 'Already Signed In',
                        'message' => 'You are already signed in as this user.',
                    ]);
            }

            // Store the activity log
            $this->logActivity(
                $user,
                ActivityAction::IMPERSONATE,
                'Started impersonating user: '.$user->name.' ('.$user->email.')',
                [
                    'impersonated_user_id' => $user->id,
                    'impersonated_user_name' => $user->name,
                    'impersonated_user_email' => $user->email,
                    'impersonator_id' => Auth::id(),
                    'impersonator_name' => Auth::user()->name,
                ]
            );

            // Store the impersonator's ID in the session
            session()->put('impersonator_id', Auth::id());

            // Log in as the impersonated user
            Auth::login($user);

            session()->flash('success', [
                'title' => 'Impersonation Started',
                'message' => 'You are now impersonating '.$user->name,
            ]);

            return $this->impersonationRedirect(route('dashboard'));
        } catch (Exception $exception) {
            report($exception);

            return to_route('app.users.index')
                ->with('error', [
                    'title' => 'Impersonation Failed',
                    'message' => 'Unable to start impersonation right now.',
                ]);
        }
    }

    /**
     * Stop impersonating a user.
     */
    public function stopImpersonating(): RedirectResponse|HttpResponse
    {
        try {
            $impersonatorId = session('impersonator_id');

            if (! $impersonatorId) {
                return to_route('dashboard')
                    ->with('error', [
                        'title' => 'Not Impersonating',
                        'message' => 'You are not currently impersonating any user.',
                    ]);
            }

            // Get the impersonator user
            $impersonator = User::query()->find($impersonatorId);

            if (! $impersonator) {
                return to_route('dashboard')
                    ->with('error', [
                        'title' => 'Impersonator Not Found',
                        'message' => 'The original user account could not be found.',
                    ]);
            }

            // Store impersonated user info before switching
            $impersonatedUserId = Auth::id();
            $impersonatedUserName = Auth::user()->name;

            // Log in as the impersonator FIRST (so activity is logged as super user)
            Auth::login($impersonator);

            // Remove the impersonator's ID from the session
            session()->forget('impersonator_id');

            // Now log the activity (causer will be the super user, hidden from non-super users)
            $this->logActivity(
                $impersonator,
                ActivityAction::IMPERSONATE,
                'Stopped impersonating user: '.$impersonatedUserName,
                [
                    'impersonator_id' => $impersonator->id,
                    'impersonator_name' => $impersonator->name,
                    'impersonator_email' => $impersonator->email,
                    'previously_impersonated_user_id' => $impersonatedUserId,
                    'previously_impersonated_user_name' => $impersonatedUserName,
                ]
            );

            session()->flash('success', [
                'title' => 'Impersonation Stopped',
                'message' => 'You have stopped impersonating and are now logged in as '.$impersonator->name,
            ]);

            return $this->impersonationRedirect(route('dashboard'));
        } catch (Exception $exception) {
            report($exception);

            return to_route('dashboard')
                ->with('error', [
                    'title' => 'Stop Impersonation Failed',
                    'message' => 'Unable to stop impersonation right now.',
                ]);
        }
    }

    private function impersonationRedirect(string $url): RedirectResponse|HttpResponse
    {
        if (request()->header('X-Inertia')) {
            return Inertia::location($url);
        }

        return redirect()->to($url);
    }

    // ================================================================
    // OVERRIDE BULK ACTION FOR CUSTOM USER ACTIONS
    // ================================================================

    public function bulkAction(Request $request): RedirectResponse
    {
        $request->validate([
            'action' => ['required', 'string', 'in:delete,restore,force_delete,suspend,ban,unban'],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer'],
        ]);

        $action = $request->input('action');
        $ids = array_map(intval(...), $request->input('ids'));

        // Protect super user (ID 1) from destructive or restrictive actions
        $protectedActions = ['delete', 'force_delete', 'suspend', 'ban'];
        if (in_array($action, $protectedActions) && in_array(1, $ids, true)) {
            $ids = array_values(array_filter($ids, fn ($id): bool => $id !== 1));
            $request->merge(['ids' => $ids]);

            if ($ids === []) {
                return back()->with('error', 'Cannot perform this action on the super user account.');
            }
        }

        try {
            $result = $this->service()->handleBulkAction($request);

            $activityAction = match ($action) {
                'delete' => ActivityAction::BULK_DELETE,
                'force_delete' => ActivityAction::BULK_FORCE_DELETE,
                'restore' => ActivityAction::BULK_RESTORE,
                'suspend', 'ban', 'unban' => ActivityAction::UPDATE,
                default => ActivityAction::BULK_DELETE,
            };

            $actionDescription = match ($action) {
                'delete' => 'moved to trash',
                'force_delete' => 'permanently deleted',
                'restore' => 'restored from trash',
                'suspend' => 'suspended',
                'ban' => 'banned',
                'unban' => 'unbanned',
                default => 'processed',
            };

            $successCount = $result['success_count'] ?? $result['affected'] ?? 0;
            $totalCount = $result['total_count'] ?? $result['affected'] ?? count($ids);

            $this->logActivity(
                new User,
                $activityAction,
                sprintf('Bulk %s: %s users %s', $action, $successCount, $actionDescription),
                [
                    'action' => $action,
                    'total_count' => $totalCount,
                    'success_count' => $successCount,
                    'failed_count' => $totalCount - $successCount,
                    'ids' => $ids,
                    'module' => 'User',
                    'action_type' => 'bulk_operation',
                ]
            );

            return back()->with('status', sprintf('%s users %s successfully.', $successCount, $actionDescription));
        } catch (Exception $exception) {
            report($exception);

            return back()->with('error', 'Bulk action failed.');
        }
    }

    // ================================================================
    // REQUIRED: Return the service
    // ================================================================

    protected function service(): UserService
    {
        return $this->userService;
    }

    protected function inertiaPage(): string
    {
        return 'users';
    }

    // ================================================================
    // VALIDATION
    // ================================================================

    // ================================================================
    // FORM VIEW DATA (dropdowns, options for create/edit)
    // ================================================================

    protected function getFormViewData(Model $model): array
    {
        /** @var User $user */
        $user = $model;
        $user->loadMissing(['roles:id', 'primaryAddress']);
        $primaryAddress = $user->primaryAddress;

        $detectedCountryCode = null;

        if (! $user->exists) {
            $location = $this->geoIpService->getLocationFromIp(request()->ip());
            $detectedCountryCode = $location['country']['iso_code'] ?? null;

            if ($detectedCountryCode === null && app()->environment('local')) {
                $detectedCountryCode = 'IN';
            }
        }

        $initialValues = [
            'name' => $user->exists ? ($user->getAttribute('name') ?? '') : '',
            'first_name' => $user->exists ? (string) ($user->getAttribute('first_name') ?? '') : '',
            'last_name' => $user->exists ? (string) ($user->getAttribute('last_name') ?? '') : '',
            'email' => $user->exists ? ($user->getAttribute('email') ?? '') : '',
            'username' => $user->exists ? (string) ($user->getAttribute('username') ?? '') : '',
            'status' => $user->exists
                ? ($user->getAttribute('status')?->value ?? Status::ACTIVE->value)
                : Status::ACTIVE->value,
            'password' => '',
            'password_confirmation' => '',
            'address1' => $user->exists ? (string) ($primaryAddress?->getAttribute('address1') ?? '') : '',
            'address2' => $user->exists ? (string) ($primaryAddress?->getAttribute('address2') ?? '') : '',
            'country' => $user->exists ? (string) ($primaryAddress?->getAttribute('country') ?? '') : '',
            'country_code' => $user->exists
                ? (string) ($primaryAddress?->getAttribute('country_code') ?? '')
                : (string) ($detectedCountryCode ?? ''),
            'state' => $user->exists ? (string) ($primaryAddress?->getAttribute('state') ?? '') : '',
            'state_code' => $user->exists ? (string) ($primaryAddress?->getAttribute('state_code') ?? '') : '',
            'city' => $user->exists ? (string) ($primaryAddress?->getAttribute('city') ?? '') : '',
            'city_code' => $user->exists ? (string) ($primaryAddress?->getAttribute('city_code') ?? '') : '',
            'zip' => $user->exists ? (string) ($primaryAddress?->getAttribute('zip') ?? '') : '',
            'phone' => $user->exists ? (string) ($primaryAddress?->getAttribute('phone') ?? '') : '',
            'birth_date' => $user->exists ? (string) ($user->getBirthDate() ?? '') : '',
            'gender' => $user->exists ? (string) ($user->getAttribute('gender') ?? '') : '',
            'tagline' => $user->exists ? (string) ($user->getAttribute('tagline') ?? '') : '',
            'bio' => $user->exists ? (string) ($user->getAttribute('bio') ?? '') : '',
            'avatar' => null,
            'website_url' => $user->exists ? (string) ($user->getWebsiteUrl() ?? '') : '',
            'twitter_url' => $user->exists ? (string) ($user->getTwitterUrl() ?? '') : '',
            'facebook_url' => $user->exists ? (string) ($user->getFacebookUrl() ?? '') : '',
            'instagram_url' => $user->exists ? (string) ($user->getInstagramUrl() ?? '') : '',
            'linkedin_url' => $user->exists ? (string) ($user->getLinkedinUrl() ?? '') : '',
            'roles' => $user->exists ? $user->roles()->pluck('id')->toArray() : [],
        ];

        $superUserRoleId = User::superUserRoleId();
        $availableRoles = Role::visibleToCurrentUser()
            ->select('id', 'name', 'display_name')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role): array => [
                'id' => $role->id,
                'name' => $role->name,
                'display_name' => $role->display_name ?? ucfirst((string) $role->name),
                'is_system' => $role->id === $superUserRoleId,
            ])
            ->toArray();

        $data = [
            'initialValues' => $initialValues,
            'availableRoles' => $availableRoles,
            'statusOptions' => $this->userService->getStatusOptions(),
            'genderOptions' => $this->userService->getGenderOptions(),
        ];

        return $data;
    }

    protected function transformModelForEdit(Model $model): array
    {
        /** @var User $user */
        $user = $model;
        $user->loadMissing(['roles:id,name,display_name', 'primaryAddress']);
        $status = $user->getAttribute('status');
        $statusValue = $status instanceof Status ? $status->value : (string) ($status ?? Status::ACTIVE->value);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'first_name' => (string) ($user->first_name ?? ''),
            'last_name' => (string) ($user->last_name ?? ''),
            'email' => $user->email,
            'username' => (string) ($user->username ?? ''),
            'status' => $statusValue,
            'email_verified_at' => $user->email_verified_at?->toISOString(),
            'avatar_url' => $user->getAttribute('avatar_image'),
            'roles' => $user->roles
                ->map(fn ($role): array => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'display_name' => $role->display_name ?? ucfirst((string) $role->name),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function prepareValidatedUserData(Request $request, ?User $user = null): array
    {
        $validatedData = $this->validateRequest($request);

        if ($request->hasFile('avatar')) {
            $storedAvatar = store_uploaded_file_on_media_disk($request->file('avatar'), 'avatars');
            $validatedData['avatar'] = $storedAvatar !== false ? $storedAvatar : null;
        } elseif ($user !== null) {
            $validatedData['avatar'] = $user->avatar;
        }

        return $validatedData;
    }

    // ================================================================
    // OPTIONAL: Side effects after CRUD operations
    // ================================================================

    protected function handleCreationSideEffects(Model $model): void
    {
        // Could send welcome email, etc.
    }

    protected function handleUpdateSideEffects(Model $model): void
    {
        // Could invalidate user cache, etc.
    }

    protected function handleDeletionSideEffects(Model $model): void
    {
        // Could notify admins, etc.
    }
}
