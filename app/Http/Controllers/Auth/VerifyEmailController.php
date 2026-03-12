<?php

namespace App\Http\Controllers\Auth;

use App\Enums\ActivityAction;
use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController extends Controller
{
    public function __construct(
        protected readonly ActivityLogger $activityLogger
    ) {}

    /**
     * Mark the authenticated user's email address as verified.
     */
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            if (blank($user->first_name)) {
                return to_route('profile.complete')
                    ->with('status', __('auth.email_already_verified'));
            }

            return redirect()
                ->intended(route('dashboard'))
                ->with('status', __('auth.email_already_verified'));
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));

            $this->activityLogger->log(
                $user,
                ActivityAction::EMAIL_VERIFICATION,
                'Email address verified.',
                ['ip' => $request->ip()],
                false,
                $user
            );
        }

        if (blank($user->first_name)) {
            return to_route('profile.complete')
                ->with('status', __('auth.email_verified_success'));
        }

        return redirect()
            ->intended(route('dashboard'))
            ->with('status', __('auth.email_verified_success'));
    }
}
