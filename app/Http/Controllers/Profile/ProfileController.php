<?php

namespace App\Http\Controllers\Profile;

use App\Enums\ActivityAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\PasswordUpdateRequest;
use App\Http\Requests\ProfileUpdateRequest;
use App\Models\User;
use App\Models\UserProvider;
use App\Services\AuthService;
use App\Services\SocialLoginService;
use App\Services\TwoFactorAuthenticationService;
use App\Services\UserService;
use App\Traits\ActivityTrait;
use App\Traits\HasAlerts;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

class ProfileController extends Controller
{
    use ActivityTrait;
    use HasAlerts;

    protected string $activityLogModule = 'Profile';

    protected string $activityEntityAttribute = 'name';

    public function __construct(
        private readonly UserService $userService,
        private readonly AuthService $authService,
        private readonly TwoFactorAuthenticationService $twoFactorAuthenticationService,
        private readonly SocialLoginService $socialLoginService
    ) {}

    /**
     * Display authenticated user profile
     */
    public function view(): Response
    {
        $user = auth()->user();
        $primaryAddress = $user->primaryAddress;

        return Inertia::render('account/profile', [
            'profile' => [
                'first_name' => (string) ($user->getAttribute('first_name') ?? ''),
                'last_name' => (string) ($user->getAttribute('last_name') ?? ''),
                'full_name' => (string) ($user->getAttribute('full_name') ?? $user->name),
                'username' => (string) ($user->getAttribute('username') ?? ''),
                'email' => (string) $user->email,
                'phone' => (string) ($primaryAddress?->getAttribute('phone') ?? $user->getAttribute('phone') ?? ''),
                'avatar_url' => $user->getAttribute('avatar_image'),
            ],
            'showUsername' => module_enabled('CMS'),
        ]);
    }

    public function billing(): View
    {
        $user = auth()->user();

        return view('app.profile.billing', [
            'page_title' => $user->name.' '.__('billing.billing'),
            'user' => $user,
        ]);
    }

    public function teams(): View
    {
        $user = auth()->user();

        return view('app.profile.teams', [
            'page_title' => $user->name.' '.__('team.team'),
            'user' => $user,
        ]);
    }

    public function security(): Response
    {
        $user = auth()->user();
        $connectedProviders = $this->getConnectedSocialProviders($user);
        $sessionManagementSupported = $this->authService->isSessionManagementSupported();
        $activeSessionCount = 1;

        if ($sessionManagementSupported) {
            $activeSessionCount = count($this->authService->getUserSessions($user, request()->session()->getId()));
        }

        $twoFactorEnabled = $this->twoFactorAuthenticationService->isEnabled($user);
        $twoFactorPending = $this->twoFactorAuthenticationService->hasPendingSetup($user);
        $hasEnabledSocialLogins = $this->socialLoginService->hasEnabledSocialLogins();
        $showSocialLoginCard = $hasEnabledSocialLogins;

        return Inertia::render('account/security', [
            'twoFactorEnabled' => $twoFactorEnabled,
            'twoFactorPending' => $twoFactorPending,
            'showSocialLoginCard' => $showSocialLoginCard,
            'connectedProviderCount' => $connectedProviders->count(),
            'activeSessionCount' => $activeSessionCount,
            'sessionManagementSupported' => $sessionManagementSupported,
            'hasPassword' => $user->hasUsablePassword(),
        ]);
    }

    public function securityPassword(): Response
    {
        return Inertia::render('account/password', [
            'hasPassword' => auth()->user()->hasUsablePassword(),
        ]);
    }

    public function securityTwoFactor(): Response
    {
        $user = auth()->user();
        $twoFactorEnabled = $this->twoFactorAuthenticationService->isEnabled($user);
        $twoFactorPending = $this->twoFactorAuthenticationService->hasPendingSetup($user);
        $twoFactorSetupUrl = $twoFactorPending
            ? $this->twoFactorAuthenticationService->getOtpAuthUrl($user)
            : null;
        $twoFactorQrCodeDataUri = $twoFactorSetupUrl
            ? $this->twoFactorAuthenticationService->getQrCodeDataUri($twoFactorSetupUrl)
            : null;
        $revealedRecoveryCodes = session('two_factor.recovery_codes');
        $twoFactorRecoveryCodes = is_array($revealedRecoveryCodes) ? $revealedRecoveryCodes : [];

        return Inertia::render('account/two-factor', [
            'twoFactorEnabled' => $twoFactorEnabled,
            'twoFactorPending' => $twoFactorPending,
            'twoFactorSetupKey' => $twoFactorPending ? $user->two_factor_secret : null,
            'twoFactorSetupUrl' => $twoFactorSetupUrl,
            'twoFactorQrCodeDataUri' => $twoFactorQrCodeDataUri,
            'twoFactorRecoveryCodes' => $twoFactorRecoveryCodes,
        ]);
    }

    public function securitySocialLogins(Request $request): RedirectResponse|SymfonyRedirectResponse|Response
    {
        $user = auth()->user();
        $connectedProviders = $this->getConnectedSocialProviders($user);
        $hasEnabledSocialLogins = $this->socialLoginService->hasEnabledSocialLogins();

        if (! $hasEnabledSocialLogins) {
            return to_route('app.profile.security')
                ->with('info', __('auth.social_auth_disabled'));
        }

        $connectProvider = strtolower((string) $request->query('connect', ''));
        if ($connectProvider !== '') {
            return $this->connectSocialLogin($connectProvider);
        }

        $disconnectProvider = strtolower((string) $request->query('disconnect', ''));
        if ($disconnectProvider !== '') {
            return $this->disconnectSocialLogin($disconnectProvider);
        }

        $enabledProviders = $this->getEnabledSocialProviders();
        $connectedProviderNames = $connectedProviders
            ->pluck('provider')
            ->map(fn (string $provider): string => strtolower($provider))
            ->values();

        $availableProviders = $enabledProviders
            ->reject(fn (array $provider): bool => $connectedProviderNames->contains($provider['key']))
            ->values();

        return Inertia::render('account/social-logins', [
            'connectedProviders' => $connectedProviders
                ->map(fn (UserProvider $provider): array => [
                    'key' => strtolower((string) $provider->provider),
                    'label' => $this->getSocialProviderLabel((string) $provider->provider),
                    'connected_at' => $provider->created_at?->toIso8601String(),
                    'connected_at_label' => $provider->created_at?->format('M d, Y H:i') ?? 'Recently',
                ])
                ->values(),
            'availableProviders' => $availableProviders,
        ]);
    }

    public function connectSocialLogin(string $provider): SymfonyRedirectResponse|RedirectResponse
    {
        if (! $this->isSupportedSocialProvider($provider)) {
            return to_route('app.profile.security.social-logins')->with('error', __('auth.invalid_provider'));
        }

        if (! $this->isSocialProviderEnabled($provider)) {
            return to_route('app.profile.security.social-logins')->with('error', __('auth.provider_disabled'));
        }

        // Store session flag so the shared OAuth callback knows this is an account-linking flow.
        // This lets us use a single callback URL per provider (required by GitHub).
        session()->put('social_link_account', true);

        return Socialite::driver($provider)->redirect();
    }

    public function disconnectSocialLogin(string $provider): RedirectResponse
    {
        if (! $this->isSupportedSocialProvider($provider)) {
            return to_route('app.profile.security.social-logins')->with('error', __('auth.invalid_provider'));
        }

        $deletedRows = UserProvider::query()
            ->where('user_id', auth()->id())
            ->where('provider', $provider)
            ->delete();

        if ($deletedRows === 0) {
            return to_route('app.profile.security.social-logins')
                ->with('info', __('profile.social_provider_not_connected', ['provider' => $this->getSocialProviderLabel($provider)]));
        }

        return to_route('app.profile.security.social-logins')
            ->with('success', __('profile.social_provider_disconnected', ['provider' => $this->getSocialProviderLabel($provider)]));
    }

    public function securitySessions(): View
    {
        $user = auth()->user();
        $currentSessionId = request()->session()->getId();
        $sessions = $this->authService->getUserSessions($user, $currentSessionId);
        $sessionManagementSupported = $this->authService->isSessionManagementSupported();

        return view('app.profile.security-sessions', [
            'page_title' => __('profile.active_sessions'),
            'user' => $user,
            'sessions' => $sessions,
            'sessionManagementSupported' => $sessionManagementSupported,
        ]);
    }

    /**
     * Get active sessions data for AJAX requests.
     */
    public function getSessions(): JsonResponse
    {
        $user = auth()->user();
        $currentSessionId = request()->session()->getId();
        $sessions = $this->authService->getUserSessions($user, $currentSessionId);

        return response()->json([
            'success' => true,
            'sessions' => $sessions,
        ]);
    }

    /**
     * Delete a specific session.
     */
    public function deleteSession(string $sessionId): JsonResponse
    {
        // Check if session management is supported
        if (! $this->authService->isSessionManagementSupported()) {
            return response()->json([
                'success' => false,
                'message' => __('profile.session_management_limited'),
            ], 400);
        }

        $user = auth()->user();

        $deleted = $this->authService->deleteSession($user, $sessionId);

        if ($deleted) {
            return response()->json([
                'success' => true,
                'message' => __('profile.session_deleted_successfully'),
                'no_reload' => true, // Don't reload the page, we'll handle DOM updates via JS
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => __('profile.session_delete_failed'),
        ], 400);
    }

    /**
     * Delete all other sessions except the current one.
     */
    public function deleteOtherSessions(): JsonResponse
    {
        // Check if session management is supported
        if (! $this->authService->isSessionManagementSupported()) {
            return response()->json([
                'success' => false,
                'message' => __('profile.session_management_limited'),
            ], 400);
        }

        $user = auth()->user();
        $currentSessionId = request()->session()->getId();

        $deleted = $this->authService->deleteOtherSessions($user, $currentSessionId);

        return response()->json([
            'success' => true,
            'message' => __('profile.other_sessions_deleted', ['count' => $deleted]),
            'deleted_count' => $deleted,
        ]);
    }

    /**
     * Update the authenticated user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse|JsonResponse
    {
        try {
            $user = auth()->user();

            // Store previous values for activity logging
            $previousValues = [
                'name' => $user->name,
                'email' => $user->email,
                'username' => $user->username,
            ];

            $data = $request->validated();

            // Handle avatar file upload
            if ($request->hasFile('avatar')) {
                $data['avatar'] = $request->file('avatar')->store('avatars', get_storage_disk());
            } else {
                // Keep existing avatar if no new file uploaded
                $data['avatar'] = $user->avatar;
            }

            $updatedUser = $this->userService->update(
                $user,
                $data
            );

            // Handle password change separately
            if ($request->filled('password')) {
                $this->userService->updatePassword($updatedUser, $request->password);

                $this->logActivity(
                    $updatedUser,
                    ActivityAction::PASSWORD_CHANGE,
                    'Account password updated successfully.',
                    [
                        'module' => 'Profile Security',
                    ]
                );
            }

            // Log activity
            $this->logActivityWithPreviousValues(
                $updatedUser,
                ActivityAction::UPDATE,
                'User profile updated successfully',
                $previousValues,
                [
                    'module' => 'Profile',
                    'updated_fields' => array_keys(array_filter($request->validated())),
                ]
            );

            $message = $this->buildUpdateSuccessMessage();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'redirect' => route('app.profile'),
                ]);
            }

            return to_route('app.profile')
                ->with('success', $message);
        } catch (Exception $exception) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to update profile: '.$exception->getMessage(),
                ], 500);
            }

            return back()
                ->withInput()
                ->with('error', 'Unable to update profile: '.$exception->getMessage());
        }
    }

    /**
     * Update the authenticated user's password.
     */
    public function updatePassword(PasswordUpdateRequest $request): RedirectResponse|JsonResponse
    {
        try {
            $user = auth()->user();
            $newPassword = (string) $request->string('password');

            // Update password
            $this->userService->updatePassword($user, $newPassword);

            // Revoke all sessions for this user except the current one.
            $currentSessionId = $request->session()->getId();
            $terminatedSessions = $this->authService->deleteOtherSessions($user, $currentSessionId);
            Auth::logoutOtherDevices($newPassword);

            // Log activity
            $this->logActivity(
                $user,
                ActivityAction::PASSWORD_CHANGE,
                'Account password updated successfully.',
                [
                    'module' => 'Profile Security',
                    'terminated_other_sessions' => $terminatedSessions,
                ]
            );

            $message = [
                'title' => 'Password Updated!',
                'message' => __('passwords.password_updated'),
            ];

            // Return JSON for AJAX requests
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'redirect' => route('app.profile.security.password'),
                ]);
            }

            return to_route('app.profile.security.password')
                ->with('success', $message);
        } catch (Exception $exception) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to update password: '.$exception->getMessage(),
                ], 500);
            }

            return back()
                ->withInput()
                ->with('error', 'Unable to update password: '.$exception->getMessage());
        }
    }

    /**
     * Verify the authenticated user's current password for password-gated actions.
     */
    public function verifyCurrentPassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
        ]);

        return response()->json([
            'success' => true,
        ]);
    }

    /**
     * Delete the authenticated user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        // Log activity before deletion
        $this->logActivity(
            $user,
            ActivityAction::DELETE,
            'User account deleted',
            [
                'name' => $user->name,
                'email' => $user->email,
                'module' => 'Profile',
            ]
        );

        // Logout and delete
        Auth::logout();
        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    /**
     * Build success message for profile update.
     */
    private function buildUpdateSuccessMessage(): array
    {
        return [
            'title' => 'Profile updated',
            'actions' => [],
        ];
    }

    private function getConnectedSocialProviders(User $user): Collection
    {
        return UserProvider::query()
            ->where('user_id', $user->id)
            ->orderBy('provider')
            ->get(['provider', 'created_at']);
    }

    /**
     * @return array<string>
     */
    private function getSupportedSocialProviders(): array
    {
        return ['google', 'github'];
    }

    private function isSupportedSocialProvider(string $provider): bool
    {
        return in_array($provider, $this->getSupportedSocialProviders(), true);
    }

    private function isSocialProviderEnabled(string $provider): bool
    {
        return config('services.social_auth.enabled', false)
            && (bool) config(sprintf('services.%s.enabled', $provider), false);
    }

    private function getEnabledSocialProviders(): Collection
    {
        return collect($this->getSupportedSocialProviders())
            ->filter(fn (string $provider): bool => $this->isSocialProviderEnabled($provider))
            ->map(fn (string $provider): array => [
                'key' => $provider,
                'label' => $this->getSocialProviderLabel($provider),
            ])
            ->values();
    }

    private function getSocialProviderLabel(string $provider): string
    {
        return match ($provider) {
            'github' => 'GitHub',
            'google' => 'Google',
            default => ucfirst($provider),
        };
    }
}
