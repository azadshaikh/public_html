<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Status;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\TwoFactorChallengeRequest;
use App\Models\User;
use App\Services\AuthService;
use App\Services\TwoFactorAuthenticationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TwoFactorChallengeController extends Controller
{
    private const int PENDING_CHALLENGE_TTL_SECONDS = 600;

    public function __construct(
        private readonly TwoFactorAuthenticationService $twoFactorAuthenticationService,
        private readonly AuthService $authService
    ) {}

    public function create(Request $request): Response|RedirectResponse
    {
        $user = $this->resolvePendingUser($request);

        if (! $user instanceof User) {
            return to_route('login');
        }

        return Inertia::render('auth/two-factor-challenge', [
            'email' => $user->email,
        ]);
    }

    public function store(TwoFactorChallengeRequest $request): RedirectResponse
    {
        $user = $this->resolvePendingUser($request);

        if (! $user instanceof User) {
            return to_route('login');
        }

        $pending = $request->session()->get('auth.two_factor');
        if (! is_array($pending)) {
            return to_route('login');
        }

        $code = $request->string('code')->toString();
        $validCode = $this->twoFactorAuthenticationService->verifyCode($user, $code)
            || $this->twoFactorAuthenticationService->consumeRecoveryCode($user, $code);

        if (! $validCode) {
            throw ValidationException::withMessages([
                'code' => [__('auth.invalid_two_factor_code')],
            ]);
        }

        $remember = (bool) ($pending['remember'] ?? false);

        $this->authService->completeLogin($user, $request, $remember);

        $request->session()->regenerate();
        $request->session()->forget('auth.two_factor');

        if ($remember) {
            $rememberDuration = config('auth.remember_me_duration', 43200);
            config(['session.lifetime' => $rememberDuration]);
            $request->session()->put('remember_me', true);
        }

        if (! $user->hasVerifiedEmail() && $this->authService->isEmailVerificationRequired()) {
            return to_route('verification.notice')
                ->with('status', 'verification-required');
        }

        return redirect()->intended(route('dashboard'));
    }

    private function resolvePendingUser(Request $request): ?User // @phpstan-ignore return.unusedType
    {
        $pending = $request->session()->get('auth.two_factor');

        if (! is_array($pending) || ! isset($pending['user_id'])) {
            return null;
        }

        if ((int) ($pending['created_at'] ?? 0) < now()->timestamp - self::PENDING_CHALLENGE_TTL_SECONDS) {
            $request->session()->forget('auth.two_factor');

            return null;
        }

        $user = User::query()->find((int) $pending['user_id']);

        if (! $user || ! $this->twoFactorAuthenticationService->isEnabled($user) || $user->status !== Status::ACTIVE) { // @phpstan-ignore booleanOr.alwaysTrue, notIdentical.alwaysTrue
            $request->session()->forget('auth.two_factor');

            return null;
        }

        return $user; // @phpstan-ignore deadCode.unreachable
    }
}
