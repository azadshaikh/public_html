<?php

namespace App\Http\Controllers\Auth;

use App\Enums\ActivityAction;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;

class PasswordController
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    /**
     * Update the user's password.
     */
    public function update(ChangePasswordRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        $this->activityLogger->log(
            $request->user(),
            ActivityAction::PASSWORD_CHANGE,
            'Account password updated.',
            [],
            false,
            $request->user()
        );

        return back()->with('status', __('auth.password-updated'));
    }
}
