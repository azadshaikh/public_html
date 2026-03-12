<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\SocialLoginController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// --- Guest Routes ---
$adminPrefix = trim((string) config('app.admin_slug'), '/');
Route::prefix($adminPrefix)->group(function (): void {
    // Routes for users who are not authenticated.
    Route::middleware('guest')->group(function (): void {
        // Registration
        Route::get('register', function (Request $request) {
            if (module_enabled('agency')) {
                return to_route('agency.get-started');
            }

            if (! setting('registration_enable_registration', true)) {
                $message = __('settings.registration_disabled_message');

                if ($request->expectsJson()) {
                    return response()->json(['message' => $message], 403);
                }

                return to_route('login')
                    ->withErrors(['registration' => $message]);
            }

            return resolve(RegisteredUserController::class)->create();
        })->name('register');

        Route::post('register', [RegisteredUserController::class, 'store'])
            ->middleware('check.registration.enabled')
            ->name('register.store');

        // Login
        Route::get('login', function () {
            if (module_enabled('agency')) {
                return to_route('agency.sign-in');
            }

            return resolve(AuthenticatedSessionController::class)->create();
        })->name('login');

        Route::post('login', [AuthenticatedSessionController::class, 'store'])
            ->middleware('limit.login.attempts')
            ->name('login.store');

        Route::get('two-factor-challenge', [TwoFactorChallengeController::class, 'create'])
            ->name('two-factor.challenge');

        Route::post('two-factor-challenge', [TwoFactorChallengeController::class, 'store'])
            ->middleware('throttle:6,1')
            ->name('two-factor.challenge.store');

        // Password Reset
        Route::get('forgot-password', [ForgotPasswordController::class, 'create'])
            ->name('password.request');

        Route::post('forgot-password', [ForgotPasswordController::class, 'store'])
            ->middleware('throttle:6,1')
            ->name('password.email');

        Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
            ->name('password.reset');

        Route::post('reset-password', [NewPasswordController::class, 'store'])
            ->name('password.store');
    });

    // --- Authenticated Routes ---
    // Routes for users who are logged in.
    Route::middleware(['auth', 'user.status', 'profile.completed'])->group(function (): void {
        // Email Verification
        Route::get('verify-email', EmailVerificationPromptController::class)
            ->name('verification.notice');

        Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
            ->middleware(['signed', 'throttle:6,1'])
            ->name('verification.verify');

        Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
            ->middleware('throttle:6,1')
            ->name('verification.send');

        // Password Confirmation & Update
        Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
            ->name('password.confirm');

        Route::post('confirm-password', [ConfirmablePasswordController::class, 'store'])
            ->name('password.confirm.store');

        Route::put('password', [PasswordController::class, 'update'])->name('password.update');

        // Profile completion
        Route::get('profile/complete', [SocialLoginController::class, 'showProfileCompletionForm'])
            ->name('profile.complete');
        Route::post('profile/complete', [SocialLoginController::class, 'storeProfileCompletion'])
            ->name('profile.complete.store');

        // Logout
        Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
            ->name('logout');
    });

    // --- Social Login Routes ---
    // Routes for OAuth-based social login.
    Route::group(['middleware' => 'guest'], function (): void {
        Route::get('login/{provider}', [SocialLoginController::class, 'redirectToProvider'])->name('social.login');
    });

    // OAuth callback is shared between login and account-linking flows.
    // It must be accessible to both guests and authenticated users.
    Route::get('login/{provider}/callback', [SocialLoginController::class, 'handleProviderCallback'])->name('social.login.callback');
});
