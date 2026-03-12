<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\AuthService;
use App\Services\SocialLoginService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class RegisteredUserController extends Controller
{
    public function __construct(
        protected readonly AuthService $authService,
        protected readonly SocialLoginService $socialLoginService
    ) {}

    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('auth/register', [
            'status' => session('status'),
            'canLogin' => Route::has('login'),
            'socialProviders' => [
                'google' => config('services.social_auth.enabled', false) && config('services.google.enabled', false),
                'github' => config('services.social_auth.enabled', false) && config('services.github.enabled', false),
            ],
        ]);
    }

    /**
     * Handle an incoming registration request.
     */
    public function store(RegisterRequest $request): RedirectResponse
    {
        try {
            $data = $request->safe()->except('terms');

            $result = $this->authService->register($data, $request);
            $user = $result['user'];

            // If not auto-approved, redirect to login with pending message
            if (! $result['auto_approved']) {
                return to_route('login')
                    ->with('status', __('auth.registration_pending_approval'));
            }

            // Auto-login the user
            Auth::login($user);
            $request->session()->regenerate();

            // If verification not required, go straight to dashboard
            if (! $result['requires_verification']) {
                return redirect()
                    ->intended(route('dashboard'))
                    ->with('status', __('auth.registration_welcome'));
            }

            // Redirect to email verification notice
            return to_route('verification.notice')
                ->with('status', 'verification-link-sent');
        } catch (Throwable $throwable) {
            Log::error('User registration failed', [
                'exception' => $throwable->getMessage(),
                'email' => $request->input('email'),
                'ip' => $request->ip(),
            ]);

            return back()
                ->withInput($request->except('password', 'password_confirmation'))
                ->withErrors([
                    'email' => __('auth.registration_failed'),
                ]);
        }
    }
}
