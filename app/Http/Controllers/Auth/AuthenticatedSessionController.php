<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AuthService;
use App\Services\SiteAccessProtectionService;
use App\Services\SocialLoginService;
use App\Services\TwoFactorAuthenticationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function __construct(
        protected readonly AuthService $authService,
        protected readonly SocialLoginService $socialLoginService,
        protected readonly TwoFactorAuthenticationService $twoFactorAuthenticationService
    ) {}

    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('auth/login', [
            'status' => session('status'),
            'canResetPassword' => Route::has('password.request'),
            'canRegister' => Route::has('register') && (bool) setting('registration_enable_registration', true),
            'socialProviders' => [
                'google' => config('services.social_auth.enabled', false) && config('services.google.enabled', false),
                'github' => config('services.social_auth.enabled', false) && config('services.github.enabled', false),
            ],
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $remember = $request->boolean('remember');

        $user = $this->authService->attemptPrimaryAuthentication($request);

        if ($this->twoFactorAuthenticationService->isEnabled($user)) {
            $request->session()->put('auth.two_factor', [
                'user_id' => $user->id,
                'remember' => $remember,
                'created_at' => now()->timestamp,
            ]);

            return to_route('two-factor.challenge');
        }

        $this->authService->completeLogin($user, $request, $remember);

        // Regenerate session after successful authentication
        $request->session()->regenerate();

        // If remember me is checked, extend the session lifetime
        if ($remember) {
            $rememberDuration = config('auth.remember_me_duration', 43200); // 30 days default
            config(['session.lifetime' => $rememberDuration]);

            // Set the session cookie to expire with the remember duration
            $request->session()->put('remember_me', true);
        }

        // Check if email verification is required
        if (! $user->hasVerifiedEmail() && $this->authService->isEmailVerificationRequired()) {
            return to_route('verification.notice')
                ->with('status', 'verification-required');
        }

        if (blank($user->first_name)) {
            return to_route('profile.complete');
        }

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        // Clear site access protection session if service is available
        if (app()->bound(SiteAccessProtectionService::class)) {
            resolve(SiteAccessProtectionService::class)->clearSiteAccessProtectionSession();
        }

        $this->authService->logout($request);

        if (module_enabled('agency')) {
            return to_route('agency.sign-in');
        }

        return to_route('login');
    }
}
