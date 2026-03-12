<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\ConfirmTwoFactorRequest;
use App\Http\Requests\Profile\DisableTwoFactorRequest;
use App\Services\TwoFactorAuthenticationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProfileTwoFactorController extends Controller
{
    public function __construct(
        private readonly TwoFactorAuthenticationService $twoFactorAuthenticationService
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($this->twoFactorAuthenticationService->isEnabled($user)) {
            return to_route('app.profile.security.two-factor')
                ->with('info', __('auth.two_factor_already_enabled'));
        }

        $this->twoFactorAuthenticationService->beginSetup($user);

        return to_route('app.profile.security.two-factor')
            ->with('success', __('auth.two_factor_setup_started'));
    }

    public function confirm(ConfirmTwoFactorRequest $request): RedirectResponse
    {
        $user = $request->user();

        if (! $this->twoFactorAuthenticationService->hasPendingSetup($user)) {
            return to_route('app.profile.security.two-factor')
                ->with('error', __('auth.two_factor_setup_not_found'));
        }

        $confirmed = $this->twoFactorAuthenticationService->confirmSetup(
            $user,
            $request->string('code')->toString()
        );

        if (! $confirmed) {
            return to_route('app.profile.security.two-factor')
                ->withErrors(['code' => __('auth.invalid_two_factor_code')]);
        }

        $recoveryCodes = $this->twoFactorAuthenticationService->getRecoveryCodes($user->fresh());

        return to_route('app.profile.security.two-factor')
            ->with('success', __('auth.two_factor_enabled_successfully'))
            ->with('two_factor.recovery_codes', $recoveryCodes);
    }

    public function destroy(DisableTwoFactorRequest $request): RedirectResponse
    {
        $user = $request->user();

        if (! $this->twoFactorAuthenticationService->isEnabled($user) && ! $this->twoFactorAuthenticationService->hasPendingSetup($user)) {
            return to_route('app.profile.security.two-factor')
                ->with('info', __('auth.two_factor_not_enabled'));
        }

        $this->twoFactorAuthenticationService->disable($user);

        return to_route('app.profile.security.two-factor')
            ->with('success', __('auth.two_factor_disabled_successfully'));
    }

    public function regenerateRecoveryCodes(DisableTwoFactorRequest $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();

        if (! $this->twoFactorAuthenticationService->isEnabled($user)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => __('auth.two_factor_not_enabled'),
                ], 422);
            }

            return to_route('app.profile.security.two-factor')
                ->with('error', __('auth.two_factor_not_enabled'));
        }

        $recoveryCodes = $this->twoFactorAuthenticationService->regenerateRecoveryCodes($user);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => __('auth.recovery_codes_regenerated_successfully'),
                'recovery_codes' => $recoveryCodes,
            ]);
        }

        return to_route('app.profile.security.two-factor')
            ->with('success', __('auth.recovery_codes_regenerated_successfully'))
            ->with('two_factor.recovery_codes', $recoveryCodes);
    }
}
