<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ActivityAction;
use App\Enums\Status;
use App\Models\ActivityLog;
use App\Models\User;
use App\Scaffold\ScaffoldController;
use App\Services\GeoIpService;
use App\Services\UserService;
use Exception;
use Illuminate\Auth\Events\Verified;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class UserController extends ScaffoldController implements HasMiddleware
{
    public function __construct(
        private readonly UserService $userService,
        private readonly GeoIpService $geoIpService
    ) {}

    // ================================================================
    // MIDDLEWARE (Permission control)
    // ================================================================

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view_users', only: ['index', 'show', 'data']),
            new Middleware('permission:add_users', only: ['create', 'store']),
            new Middleware('permission:edit_users', only: ['edit', 'update', 'verifyEmail', 'approve', 'suspend', 'ban', 'unban']),
            new Middleware('permission:delete_users', only: ['destroy', 'bulkAction', 'forceDelete']),
            new Middleware('permission:restore_users', only: ['restore']),
            // Note: stopImpersonating is NOT protected because the impersonated user may not have permissions
            new Middleware('permission:impersonate_users', only: ['impersonate']),
        ];
    }

    // ================================================================
    // OVERRIDE INDEX TO ADD REGISTRATION SETTINGS
    // ================================================================

    public function index(Request $request, ?string $status = null): View|JsonResponse
    {
        // If this is an AJAX/JSON request, return data
        if ($request->expectsJson() || $request->ajax()) {
            return $this->data($request);
        }

        $initialData = $this->service()->getData($request);
        $config = $this->service()->getDataGridConfig();
        $statistics = $initialData['statistics'] ?? $this->service()->getStatistics();

        return view($this->service()->getScaffoldDefinition()->getIndexView(), [
            'config' => $config,
            'initialData' => $initialData,
            'status' => $status ?? 'all',
            'registrationSettings' => $this->userService->getRegistrationSettingsSummary($statistics),
        ]);
    }

    // ================================================================
    // OVERRIDE SHOW TO ADD ACTIVITY LOGS FOR TABS
    // ================================================================

    public function show(int|string $id): View|JsonResponse
    {
        $user = User::withTrashed()->findOrFail((int) $id);

        if (request()->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'data' => $user,
            ]);
        }

        // Get activity logs for this user
        $user_activities = ActivityLog::query()
            ->where('subject_type', User::class)
            ->where('subject_id', $user->id)
            ->with('causer')
            ->latest()
            ->limit(50)
            ->get();

        return view($this->service()->getScaffoldDefinition()->getShowView(), [
            'user' => $user,
            'page_title' => 'User: '.$user->name,
            'user_activities' => $user_activities,
        ]);
    }

    // ================================================================
    // OVERRIDE UPDATE TO PROTECT SUPER USER
    // ================================================================

    /**
     * Update the specified resource in storage.
     * Overridden to protect super user (ID 1) from status changes.
     */
    public function update(Request $request, int|string $id): RedirectResponse|JsonResponse
    {
        $user = User::query()->findOrFail((int) $id);

        // Protect super user (ID 1) from being banned or suspended via edit form
        if ($user->id === 1 && $request->has('status')) {
            $restrictedStatuses = [Status::BANNED->value, Status::SUSPENDED->value];
            if (in_array($request->input('status'), $restrictedStatuses, true)) {
                $message = 'Cannot set super user status to banned or suspended.';

                if ($request->expectsJson()) {
                    return response()->json([
                        'status' => 'error',
                        'message' => $message,
                    ], 403);
                }

                return back()->withInput()->with('error', $message);
            }
        }

        // Call parent update
        return parent::update($request, $id);
    }

    // ================================================================
    // OVERRIDE DESTROY TO PROTECT SUPER USER
    // ================================================================

    /**
     * Remove the specified resource from storage.
     * Overridden to protect super user (ID 1) from being trashed or deleted.
     */
    public function destroy(Request $request, int|string $id): RedirectResponse|JsonResponse
    {
        // Protect super user (ID 1) from being deleted
        if ((int) $id === 1) {
            $message = 'Cannot delete the super user account.';

            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $message,
                ], 403);
            }

            return back()->with('error', $message);
        }

        // Call parent destroy
        return parent::destroy($request, $id);
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
    public function suspend(User $user): RedirectResponse|JsonResponse
    {
        // Protect super user (ID 1) from being suspended
        if ($user->id === 1) {
            $message = 'Cannot suspend the super user account.';

            if (request()->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $message,
                ], 403);
            }

            return back()->with('error', $message);
        }

        $this->userService->suspendUser($user);

        $this->logActivity(
            $user,
            ActivityAction::UPDATE,
            sprintf('Suspended user: %s (%s)', $user->name, $user->email),
            ['action' => 'suspend', 'user_id' => $user->id]
        );

        if (request()->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => sprintf('User %s has been suspended.', $user->name),
            ]);
        }

        return back()->with('success', sprintf('User %s has been suspended.', $user->name));
    }

    /**
     * Ban a user.
     */
    public function ban(User $user): RedirectResponse|JsonResponse
    {
        // Protect super user (ID 1) from being banned
        if ($user->id === 1) {
            $message = 'Cannot ban the super user account.';

            if (request()->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $message,
                ], 403);
            }

            return back()->with('error', $message);
        }

        $this->userService->banUser($user);

        $this->logActivity(
            $user,
            ActivityAction::UPDATE,
            sprintf('Banned user: %s (%s)', $user->name, $user->email),
            ['action' => 'ban', 'user_id' => $user->id]
        );

        if (request()->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => sprintf('User %s has been banned.', $user->name),
            ]);
        }

        return back()->with('success', sprintf('User %s has been banned.', $user->name));
    }

    /**
     * Unban a user.
     */
    public function unban(User $user): RedirectResponse|JsonResponse
    {
        $this->userService->unbanUser($user);

        $this->logActivity(
            $user,
            ActivityAction::UPDATE,
            sprintf('Unbanned user: %s (%s)', $user->name, $user->email),
            ['action' => 'unban', 'user_id' => $user->id]
        );

        if (request()->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => sprintf('User %s has been unbanned.', $user->name),
            ]);
        }

        return back()->with('success', sprintf('User %s has been unbanned.', $user->name));
    }

    /**
     * Impersonate a user.
     */
    public function impersonate(int $id): RedirectResponse
    {
        try {
            $user = User::query()->where('id', $id)->withTrashed()->first();

            if (! $user) {
                return to_route('app.users.index')
                    ->with('error', [
                        'title' => 'User Not Found',
                        'message' => 'The user you are trying to impersonate does not exist.',
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

            return to_route('dashboard')
                ->with('success', [
                    'title' => 'Impersonation Started',
                    'message' => 'You are now impersonating '.$user->name,
                ]);
        } catch (Exception $exception) {
            return to_route('app.users.index')
                ->with('error', [
                    'title' => 'Impersonation Failed',
                    'message' => 'Error impersonating user: '.$exception->getMessage(),
                ]);
        }
    }

    /**
     * Stop impersonating a user.
     */
    public function stopImpersonating(): RedirectResponse
    {
        try {
            $impersonatorId = session('impersonator_id');

            if (! $impersonatorId) {
                return to_route('app.users.index')
                    ->with('error', [
                        'title' => 'Not Impersonating',
                        'message' => 'You are not currently impersonating any user.',
                    ]);
            }

            // Get the impersonator user
            $impersonator = User::query()->find($impersonatorId);

            if (! $impersonator) {
                return to_route('app.users.index')
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

            return to_route('app.users.index')
                ->with('success', [
                    'title' => 'Impersonation Stopped',
                    'message' => 'You have stopped impersonating and are now logged in as '.$impersonator->name,
                ]);
        } catch (Exception $exception) {
            return to_route('app.users.index')
                ->with('error', [
                    'title' => 'Stop Impersonation Failed',
                    'message' => 'Error stopping impersonation: '.$exception->getMessage(),
                ]);
        }
    }

    // ================================================================
    // OVERRIDE BULK ACTION FOR CUSTOM USER ACTIONS
    // ================================================================

    public function bulkAction(Request $request): JsonResponse
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
            // Remove super user from the list and add to errors
            $ids = array_values(array_filter($ids, fn ($id): bool => $id !== 1));
            $request->merge(['ids' => $ids]);

            if ($ids === []) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot perform this action on the super user account.',
                ], 403);
            }
        }

        try {
            // Handle custom user actions
            if (in_array($action, ['suspend', 'ban', 'unban'])) {
                $result = $this->userService->handleCustomBulkAction($action, $ids);
            } else {
                // Use standard scaffold bulk action via service's handleBulkAction
                $result = $this->service()->handleBulkAction($request);
            }

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

            // Get counts from result (handle different response formats)
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

            return response()->json([
                'status' => 'success',
                'message' => $result['message'] ?? sprintf('%s users %s successfully.', $successCount, $actionDescription),
                'results' => [
                    'success_count' => $successCount,
                    'total_count' => $totalCount,
                    'errors' => $result['errors'] ?? [],
                ],
            ]);
        } catch (Exception $exception) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bulk action failed: '.$exception->getMessage(),
            ], 500);
        }
    }

    // ================================================================
    // REQUIRED: Return the service
    // ================================================================

    protected function service(): UserService
    {
        return $this->userService;
    }

    // ================================================================
    // VALIDATION
    // ================================================================

    // ================================================================
    // FORM VIEW DATA (dropdowns, options for create/edit)
    // ================================================================

    protected function getFormViewData(Model $model): array
    {
        $data = [
            'statusOptions' => $this->userService->getStatusOptions(),
            'genderOptions' => $this->userService->getGenderOptions(),
            'roleOptions' => $this->userService->getRoleOptions(),
        ];

        // Auto-detect country for new users only
        if (! $model->exists) {
            $location = $this->geoIpService->getLocationFromIp(request()->ip());
            $detectedCountry = $location['country']['iso_code'] ?? null;

            // Fallback for dev environments where IP is private (127.0.0.1, 192.168.x.x)
            if ($detectedCountry === null && app()->environment('local')) {
                $detectedCountry = 'IN'; // Default for dev
            }

            $data['detectedCountryCode'] = $detectedCountry;
        }

        return $data;
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
