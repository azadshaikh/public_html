<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationNotificationController extends Controller
{
    public function __construct(
        protected readonly AuthService $authService
    ) {}

    /**
     * Send a new email verification notification.
     */
    public function store(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard'));
        }

        // Send verification email (queued)
        $this->authService->sendVerificationEmailQueued($request->user());

        return back()->with('status', 'verification-link-sent');
    }
}
