<?php

namespace App\Http\Controllers\Auth;

use App\Enums\ActivityAction;
use App\Exceptions\AccountBannedException;
use App\Exceptions\AccountSuspendedException;
use App\Http\Controllers\Controller;
use App\Models\LoginAttempt;
use App\Models\User;
use App\Models\UserProvider;
use App\Services\ActivityLogger;
use App\Services\SocialLoginService;
use App\Services\UserService;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\InvalidStateException;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

class SocialLoginController extends Controller
{
    public function __construct(protected SocialLoginService $socialLoginService, protected UserService $userService, protected ActivityLogger $activityLogger) {}

    /**
     * Redirect the user to the Provider authentication page.
     */
    public function redirectToProvider(string $provider): SymfonyRedirectResponse
    {
        if (($redirect = $this->validateProvider($provider)) instanceof RedirectResponse) {
            return $redirect;
        }

        return Socialite::driver($provider)->redirect();
    }

    /**
     * Handle the provider callback and authenticate the user.
     *
     * When an authenticated user arrives here with the `social_link_account`
     * session flag (set by ProfileController::connectSocialLogin), we link the
     * social account to their profile instead of performing a login.  This lets
     * both flows share a single callback URL per provider.
     */
    public function handleProviderCallback(Request $request, string $provider): RedirectResponse
    {
        if (($redirect = $this->validateProvider($provider)) instanceof RedirectResponse) {
            return $redirect;
        }

        // Account-linking flow: authenticated user connecting a social account from profile.
        if (Auth::check() && session()->pull('social_link_account')) {
            return $this->handleAccountLinking($provider);
        }

        $socialEmail = null;
        $socialProviderUserId = null;

        try {
            try {
                $socialUser = Socialite::driver($provider)->user();
            } catch (InvalidStateException $exception) {
                report($exception);
                $driver = Socialite::driver($provider);

                throw_unless($driver instanceof AbstractProvider, $exception);

                $socialUser = $driver->stateless()->user();
            }

            $socialEmail = $socialUser->getEmail();
            $socialProviderUserId = $socialUser->getId();

            if (blank($socialEmail)) {
                $this->recordSocialLoginFailure(
                    $request,
                    $provider,
                    $socialEmail,
                    LoginAttempt::REASON_SOCIAL_EMAIL_MISSING,
                    null,
                    $socialProviderUserId
                );

                return $this->handleMissingEmail($socialUser);
            }

            $authUser = $this->socialLoginService->findOrCreateUser($socialUser, $provider);
            $wasRecentlyCreated = $authUser->wasRecentlyCreated;

            Auth::login($authUser);
            $this->recordSocialLoginSuccess($request, $authUser, $provider, $socialProviderUserId);

            if ($wasRecentlyCreated && ! $authUser->hasVerifiedEmail()) {
                $authUser->markEmailAsVerified();
            }

            if ($wasRecentlyCreated) {
                $this->activityLogger->log(
                    $authUser,
                    ActivityAction::REGISTER,
                    sprintf('User registered via %s.', $provider),
                    [
                        'provider' => $provider,
                        'registration_channel' => 'social',
                    ],
                    false,
                    $authUser
                );

                if (blank($authUser->first_name)) {
                    return to_route('profile.complete');
                }

                if ($provider === 'google') {
                    return to_route('dashboard');
                }
            }

            $this->activityLogger->log(
                $authUser,
                ActivityAction::LOGIN,
                sprintf('User logged in via %s.', $provider),
                [
                    'provider' => $provider,
                    'guard' => 'web',
                ],
                false,
                $authUser
            );

            return redirect()->intended($this->redirectTo());
        } catch (AccountBannedException|AccountSuspendedException $e) {
            $reason = $e instanceof AccountBannedException
                ? LoginAttempt::REASON_ACCOUNT_BANNED
                : LoginAttempt::REASON_ACCOUNT_SUSPENDED;

            $this->recordSocialLoginFailure(
                $request,
                $provider,
                $socialEmail,
                $reason,
                $this->resolveUserIdByEmail($socialEmail),
                $socialProviderUserId
            );

            return redirect('/')->with('error', $e->getMessage());
        } catch (Exception $e) {
            // Log the exception or handle it as needed
            report($e);

            $this->recordSocialLoginFailure(
                $request,
                $provider,
                $socialEmail,
                LoginAttempt::REASON_SOCIAL_AUTH_FAILED,
                $this->resolveUserIdByEmail($socialEmail),
                $socialProviderUserId
            );

            return redirect('/')->with('error', __('auth.social_login_failed'));
        }
    }

    public function showProfileCompletionForm(): RedirectResponse|Response
    {
        if (! Auth::check()) {
            return to_route('login');
        }

        $user = Auth::user();

        if (filled($user->first_name)) {
            return to_route('dashboard');
        }

        return Inertia::render('auth/profile-complete', [
            'user' => [
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
            ],
        ]);
    }

    public function storeProfileCompletion(Request $request): RedirectResponse
    {
        if (! Auth::check()) {
            return to_route('login');
        }

        $user = Auth::user();

        if (filled($user->first_name)) {
            return to_route('dashboard');
        }

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
        ]);

        $firstName = trim((string) $validated['first_name']);
        $lastName = trim((string) ($validated['last_name'] ?? ''));

        $user->forceFill([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'name' => trim($firstName.' '.$lastName),
        ])->save();

        return to_route('dashboard');
    }

    /**
     * Get the redirect path after login.
     */
    protected function redirectTo(): string
    {
        return request()->redirectTo ?? route('dashboard');
    }

    /**
     * Get the list of supported social providers.
     *
     * @return array<string>
     */
    protected function getSupportedProviders(): array
    {
        return ['google', 'github'];
    }

    /**
     * Check if social authentication is globally enabled.
     */
    protected function isSocialAuthEnabled(): bool
    {
        return config('services.social_auth.enabled', false);
    }

    /**
     * Check if a provider is enabled in settings.
     */
    protected function isProviderEnabled(string $provider): bool
    {
        return (bool) config(sprintf('services.%s.enabled', $provider), false);
    }

    /**
     * Validate that the provider is supported and enabled.
     *
     * When called for an authenticated user (account-linking flow), error
     * redirects go to the profile social-logins page instead of the login page.
     */
    protected function validateProvider(string $provider): ?RedirectResponse
    {
        $errorRoute = Auth::check() ? 'app.profile.security.social-logins' : 'login';

        if (! $this->isSocialAuthEnabled()) {
            return to_route($errorRoute)->with('error', __('auth.social_auth_disabled'));
        }

        if (! in_array($provider, $this->getSupportedProviders())) {
            return to_route($errorRoute)->with('error', __('auth.invalid_provider'));
        }

        if (! $this->isProviderEnabled($provider)) {
            return to_route($errorRoute)->with('error', __('auth.provider_disabled'));
        }

        return null;
    }

    protected function recordSocialLoginSuccess(
        Request $request,
        User $user,
        string $provider,
        mixed $providerUserId = null
    ): void {
        LoginAttempt::recordSuccess(
            $user->email,
            (string) $request->ip(),
            $user->id,
            $request->userAgent(),
            array_filter([
                'auth_channel' => 'social',
                'provider' => $provider,
                'provider_user_id' => $providerUserId,
            ], static fn (mixed $value): bool => $value !== null && $value !== '')
        );
    }

    protected function recordSocialLoginFailure(
        Request $request,
        string $provider,
        ?string $email,
        string $reason,
        ?int $userId = null,
        mixed $providerUserId = null
    ): void {
        LoginAttempt::recordFailure(
            $this->normalizeSocialAttemptEmail($provider, $email, $providerUserId),
            (string) $request->ip(),
            $reason,
            $userId,
            $request->userAgent(),
            array_filter([
                'auth_channel' => 'social',
                'provider' => $provider,
                'provider_user_id' => $providerUserId,
            ], static fn (mixed $value): bool => $value !== null && $value !== '')
        );
    }

    protected function resolveUserIdByEmail(?string $email): ?int
    {
        if (blank($email)) {
            return null;
        }

        return User::query()
            ->where('email', strtolower($email))
            ->value('id');
    }

    protected function normalizeSocialAttemptEmail(string $provider, ?string $email, mixed $providerUserId = null): string
    {
        if (filled($email)) {
            return strtolower($email);
        }

        $providerSegment = preg_replace('/[^a-z0-9]/', '', strtolower($provider)) ?: 'social';
        $idSegment = preg_replace('/[^a-z0-9]/', '', strtolower((string) ($providerUserId ?? 'unknown'))) ?: 'unknown';

        return substr(sprintf('unknown+%s-%s@social.local', $providerSegment, $idSegment), 0, 255);
    }

    /**
     * Link a social account to the currently authenticated user.
     *
     * Mirrors the logic previously in ProfileController::handleSocialLoginConnectionCallback
     * but reuses the shared OAuth callback URL.
     */
    protected function handleAccountLinking(string $provider): RedirectResponse
    {
        try {
            try {
                $socialUser = Socialite::driver($provider)->user();
            } catch (InvalidStateException $exception) {
                report($exception);
                $driver = Socialite::driver($provider);

                throw_unless($driver instanceof AbstractProvider, $exception);

                $socialUser = $driver->stateless()->user();
            }

            $user = Auth::user();

            $existingProviderAccount = UserProvider::query()
                ->where('provider', $provider)
                ->where('provider_id', $socialUser->getId())
                ->first();

            if ($existingProviderAccount && (int) $existingProviderAccount->user_id !== (int) $user->id) {
                return to_route('dashboard')
                    ->with('error', __('profile.social_provider_already_linked'));
            }

            $providerLabel = match ($provider) {
                'github' => 'GitHub',
                'google' => 'Google',
                default => ucfirst($provider),
            };

            UserProvider::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'provider' => $provider,
                ],
                [
                    'provider_id' => (string) $socialUser->getId(),
                    'avatar' => $socialUser->getAvatar(),
                ]
            );

            return to_route('dashboard')
                ->with('success', __('profile.social_provider_connected', ['provider' => $providerLabel]));
        } catch (Exception $exception) {
            report($exception);

            return to_route('dashboard')
                ->with('error', __('auth.social_login_failed'));
        }
    }

    /**
     * Handle the case when email is missing from social login.
     */
    protected function handleMissingEmail($socialUser): RedirectResponse
    {
        $name = $socialUser->getName();
        [$firstName, $lastName] = $this->userService->splitName($name);

        return redirect(route('register'))
            ->with(['first_name' => $firstName, 'last_name' => $lastName, 'email' => $socialUser->getEmail()]);
    }
}
