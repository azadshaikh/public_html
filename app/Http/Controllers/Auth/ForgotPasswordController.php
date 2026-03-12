<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Services\AuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ForgotPasswordController extends Controller
{
    public function __construct(
        protected readonly AuthService $authService
    ) {}

    /**
     * Display the password reset link request view.
     */
    public function create(): Response
    {
        return Inertia::render('auth/forgot-password', [
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming password reset link request.
     */
    public function store(ForgotPasswordRequest $request): RedirectResponse
    {
        try {
            $email = $request->input('email');

            $this->authService->sendPasswordResetLink($email, $request);

            // Always return the same success message for security (prevent user enumeration)
            return back()->with('status', __('passwords.reset_link_sent'));
        } catch (Throwable $throwable) {
            Log::error('Password reset request failed', [
                'email' => $request->input('email'),
                'error' => $throwable->getMessage(),
                'ip' => $request->ip(),
            ]);

            return back()
                ->withInput(['email' => $request->input('email')])
                ->withErrors(['email' => __('passwords.reset_request_error')]);
        }
    }
}
