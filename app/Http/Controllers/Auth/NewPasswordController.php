<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class NewPasswordController extends Controller
{
    public function __construct(
        protected readonly AuthService $authService
    ) {}

    /**
     * Display the password reset view.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('auth/reset-password', [
            'token' => (string) $request->route('token'),
            'email' => (string) $request->string('email'),
        ]);
    }

    /**
     * Handle an incoming new password request.
     */
    public function store(ResetPasswordRequest $request): RedirectResponse
    {
        try {
            $result = $this->authService->resetPassword(
                $request->input('email'),
                $request->input('token'),
                $request->input('password'),
                $request
            );

            if (! $result['success']) {
                $errorKey = 'passwords.'.($result['error'] ?? 'reset_error');

                return back()
                    ->withInput($request->only('email'))
                    ->withErrors(['email' => __($errorKey)]);
            }

            // Fire the password reset event
            $user = User::query()->where('email', $request->input('email'))->first();
            if ($user) {
                event(new PasswordReset($user));
            }

            return to_route('login')
                ->with('status', __('passwords.reset'));
        } catch (Throwable $throwable) {
            Log::error('Password reset failed', [
                'email' => $request->input('email'),
                'error' => $throwable->getMessage(),
                'ip' => $request->ip(),
            ]);

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => __('passwords.reset_error')]);
        }
    }
}
