<?php

namespace App\Services;

use App\DataTransferObjects\EmailSendResult;
use App\Enums\ActivityAction;
use App\Enums\Status;
use App\Jobs\SendAuthEmail;
use App\Models\LoginAttempt;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Centralized Authentication Service
 *
 * Handles all authentication-related operations including:
 * - Login with rate limiting and attempt tracking
 * - Registration with email verification
 * - Password reset flow
 * - Account status validation
 */
class AuthService
{
    public function __construct(
        protected readonly UserService $userService,
        protected readonly EmailService $emailService,
        protected readonly ActivityLogger $activityLogger
    ) {}

    // =========================================================================
    // LOGIN
    // =========================================================================

    /**
     * Attempt to authenticate a user.
     *
     * Note: Rate limit CHECK is done by LimitLoginAttempts middleware.
     * This service only handles incrementing the counter on failed attempts.
     *
     * @throws ValidationException
     */
    public function attemptPrimaryAuthentication(Request $request): User
    {
        $email = $request->input('email');
        $password = $request->input('password');
        $ip = $request->ip();
        $userAgent = $request->userAgent();

        if (! Auth::validate(['email' => $email, 'password' => $password])) {
            $remainingAttempts = $this->handleFailedLogin($email, $ip, $userAgent, LoginAttempt::REASON_INVALID_CREDENTIALS);

            // Build error message with warning if few attempts remain
            $errorMessage = __('auth.failed');
            if ($remainingAttempts !== null && $remainingAttempts <= 2 && $remainingAttempts > 0) {
                $errorMessage .= ' '.trans_choice('auth.attempts_remaining_warning', $remainingAttempts, ['count' => $remainingAttempts]);
            }

            throw ValidationException::withMessages([
                'email' => [$errorMessage],
            ]);
        }

        /** @var User|null $user */
        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        // Validate account status
        $this->validateAccountStatus($user, $email, $ip, $userAgent);

        return $user;
    }

    public function completeLogin(User $user, Request $request, bool $remember = false): void
    {
        $ip = $request->ip();
        $userAgent = $request->userAgent();

        Auth::login($user, $remember);

        // Record successful login
        $this->handleSuccessfulLogin($user, $ip, $userAgent);
    }

    /**
     * Fully clear all rate limiting for an IP address.
     *
     * This clears both the rate limiter cache AND marks database records
     * as cleared so the database fallback check won't block the user.
     */
    public function clearRateLimitForIp(string $ip): void
    {
        $throttleKey = $this->getThrottleKey($ip);

        // Clear the main rate limiter
        RateLimiter::clear($throttleKey);

        // Clear the block log key (used to prevent duplicate blocked logs)
        RateLimiter::clear('login_block_logged:'.$throttleKey);

        // Mark recent failed attempts as "cleared" so database fallback doesn't count them
        // We do this by updating them to a special status or by simply deleting them
        $lockoutMinutes = (int) setting('login_security_lockout_time', 60);

        LoginAttempt::query()->where('ip_address', $ip)
            ->where('status', LoginAttempt::STATUS_FAILED)
            ->where('created_at', '>=', now()->subMinutes($lockoutMinutes))
            ->update(['status' => LoginAttempt::STATUS_CLEARED]);

        Log::info('Rate limit cleared for IP', ['ip' => $ip]);
    }

    /**
     * Get remaining login attempts.
     */
    public function getRemainingAttempts(string $ip): int
    {
        if (! $this->isRateLimitingEnabled()) {
            return PHP_INT_MAX;
        }

        $maxAttempts = (int) setting('login_security_limit_login_attempts', 5);
        $current = RateLimiter::attempts($this->getThrottleKey($ip));

        return max(0, $maxAttempts - $current);
    }

    /**
     * Logout a user.
     */
    public function logout(Request $request): void
    {
        $user = $request->user();

        if ($user) {
            $this->activityLogger->log(
                $user,
                ActivityAction::LOGOUT,
                'User logged out.',
                ['ip' => $request->ip()],
                false,
                $user
            );
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    // =========================================================================
    // REGISTRATION
    // =========================================================================

    /**
     * Register a new user.
     *
     * @return array{user: User, requires_verification: bool, auto_approved: bool}
     */
    public function register(array $data, Request $request): array
    {
        $requiresVerification = (bool) setting('registration_require_email_verification', true);
        $autoApprove = (bool) setting('registration_auto_approve', true);

        // Create the user
        $user = $this->userService->register($data);

        // Mark email as skipped if verification not required
        if (! $requiresVerification) {
            $user->markEmailVerificationAsSkipped();
        }

        // Log activity
        $this->activityLogger->log(
            $user,
            ActivityAction::REGISTER,
            'User completed registration.',
            [
                'auto_approved' => $autoApprove,
                'requires_verification' => $requiresVerification,
                'ip' => $request->ip(),
            ],
            false,
            $user
        );

        // Send verification email if needed (queued)
        if ($requiresVerification && $autoApprove) {
            $this->sendVerificationEmailQueued($user);
        }

        return [
            'user' => $user,
            'requires_verification' => $requiresVerification,
            'auto_approved' => $autoApprove,
        ];
    }

    // =========================================================================
    // PASSWORD RESET
    // =========================================================================

    /**
     * Send password reset email.
     */
    public function sendPasswordResetLink(string $email, Request $request): bool
    {
        $user = User::query()->where('email', $email)->first();

        // Always return true for security (prevent user enumeration)
        if (! $user) {
            Log::info('Password reset requested for non-existent email', [
                'email' => $email,
                'ip' => $request->ip(),
            ]);

            return true;
        }

        // Generate token
        $token = Str::random(64);

        // Store the token
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            [
                'email' => $email,
                'token' => Hash::make($token),
                'created_at' => now(),
            ]
        );

        // Send email (queued)
        dispatch(new SendAuthEmail('password_reset', $user->id, ['token' => $token]));

        // Log activity
        $this->activityLogger->log(
            $user,
            ActivityAction::PASSWORD_RESET_REQUEST,
            'Password reset link requested.',
            ['ip' => $request->ip()],
            false,
            $user
        );

        Log::info('Password reset email queued', [
            'user_id' => $user->id,
            'email' => $email,
            'ip' => $request->ip(),
        ]);

        return true;
    }

    /**
     * Reset a user's password.
     *
     * @return array{success: bool, error?: string}
     */
    public function resetPassword(string $email, string $token, string $password, Request $request): array
    {
        // Check token validity
        $resetRecord = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->first();

        if (! $resetRecord) {
            return ['success' => false, 'error' => 'token_not_found'];
        }

        if (! Hash::check($token, $resetRecord->token)) {
            return ['success' => false, 'error' => 'token_invalid'];
        }

        // Check expiration
        $expireMinutes = config('auth.passwords.users.expire', 60);
        if (abs(now()->diffInMinutes($resetRecord->created_at)) > $expireMinutes) {
            return ['success' => false, 'error' => 'token_expired'];
        }

        // Find user
        $user = User::query()->where('email', $email)->first();
        if (! $user) {
            return ['success' => false, 'error' => 'user_not_found'];
        }

        // Update password
        $user->forceFill([
            'password' => Hash::make($password),
            'remember_token' => Str::random(60),
        ])->save();

        // Delete the reset token
        DB::table('password_reset_tokens')
            ->where('email', $email)
            ->delete();

        // Fully clear all rate limits for this IP (including database fallback)
        $this->clearRateLimitForIp($request->ip());

        // Log activity
        $this->activityLogger->log(
            $user,
            ActivityAction::PASSWORD_RESET,
            'Password reset successfully.',
            ['ip' => $request->ip()],
            false,
            $user
        );

        Log::info('Password reset completed', [
            'user_id' => $user->id,
            'email' => $email,
            'ip' => $request->ip(),
        ]);

        return ['success' => true];
    }

    // =========================================================================
    // EMAIL VERIFICATION
    // =========================================================================

    /**
     * Send verification email (queued).
     */
    public function sendVerificationEmailQueued(User $user): void
    {
        dispatch(new SendAuthEmail('verification', $user->id));
    }

    /**
     * Send verification email (synchronous).
     */
    public function sendVerificationEmailSync(User $user): EmailSendResult
    {
        return $this->emailService->sendVerificationEmail($user);
    }

    /**
     * Resend verification email.
     */
    public function resendVerificationEmail(User $user, bool $queued = true): EmailSendResult|bool
    {
        if ($user->hasVerifiedEmail()) {
            return EmailSendResult::failure('Email already verified');
        }

        if ($queued) {
            $this->sendVerificationEmailQueued($user);

            $this->activityLogger->log(
                $user,
                ActivityAction::EMAIL_VERIFICATION,
                'Verification email resent.',
                ['queued' => true],
                false,
                $user
            );

            return true;
        }

        $result = $this->sendVerificationEmailSync($user);

        if ($result->success) {
            $this->activityLogger->log(
                $user,
                ActivityAction::EMAIL_VERIFICATION,
                'Verification email resent.',
                ['queued' => false],
                false,
                $user
            );
        }

        return $result;
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Check if registration is enabled.
     */
    public function isRegistrationEnabled(): bool
    {
        return (bool) setting('registration_enable_registration', true);
    }

    /**
     * Check if email verification is required.
     */
    public function isEmailVerificationRequired(): bool
    {
        return (bool) setting('registration_require_email_verification', true);
    }

    /**
     * Get login security settings.
     */
    public function getSecuritySettings(): array
    {
        return [
            'rate_limiting_enabled' => $this->isRateLimitingEnabled(),
            'max_attempts' => (int) setting('login_security_limit_login_attempts', 5),
            'lockout_minutes' => (int) setting('login_security_lockout_time', 60),
        ];
    }

    // =========================================================================
    // SESSION MANAGEMENT
    // =========================================================================

    /**
     * Check if session management is supported for the current driver.
     * Only database driver supports listing/managing other sessions.
     */
    public function isSessionManagementSupported(): bool
    {
        return config('session.driver') === 'database';
    }

    /**
     * Get the session driver name.
     */
    public function getSessionDriver(): string
    {
        return config('session.driver', 'file');
    }

    /**
     * Get all active sessions for a user with parsed device information.
     * Only works with database session driver.
     *
     * @return array Array of session data, or empty array if not supported
     */
    public function getUserSessions(User $user, string $currentSessionId): array
    {
        // Only database driver supports listing sessions
        if (! $this->isSessionManagementSupported()) {
            // Return only current session info for non-database drivers
            return [
                [
                    'id' => $currentSessionId,
                    'ip_address' => request()->ip(),
                    'is_current' => true,
                    'last_activity' => time(),
                    'last_active_at' => now(),
                    'device' => $this->parseUserAgent(request()->userAgent())['device'],
                    'platform' => $this->parseUserAgent(request()->userAgent())['platform'],
                    'browser' => $this->parseUserAgent(request()->userAgent())['browser'],
                ],
            ];
        }

        try {
            $sessions = DB::table('sessions')
                ->where('user_id', $user->id)
                ->orderBy('last_activity', 'desc')
                ->get();

            return $sessions->map(function ($session) use ($currentSessionId): array {
                $agent = $this->parseUserAgent($session->user_agent ?? null);

                return [
                    'id' => $session->id,
                    'ip_address' => $session->ip_address ?? 'Unknown',
                    'is_current' => $session->id === $currentSessionId,
                    'last_activity' => $session->last_activity ?? time(),
                    'last_active_at' => Date::createFromTimestamp($session->last_activity ?? time()),
                    'device' => $agent['device'],
                    'platform' => $agent['platform'],
                    'browser' => $agent['browser'],
                ];
            })->all();
        } catch (Exception $exception) {
            Log::error('Failed to fetch user sessions', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);

            // Return current session only on error
            return [
                [
                    'id' => $currentSessionId,
                    'ip_address' => request()->ip(),
                    'is_current' => true,
                    'last_activity' => time(),
                    'last_active_at' => now(),
                    'device' => $this->parseUserAgent(request()->userAgent())['device'],
                    'platform' => $this->parseUserAgent(request()->userAgent())['platform'],
                    'browser' => $this->parseUserAgent(request()->userAgent())['browser'],
                ],
            ];
        }
    }

    /**
     * Delete a specific session for a user.
     * Only works with database session driver.
     *
     * @return bool True if deleted, false otherwise
     */
    public function deleteSession(User $user, string $sessionId): bool
    {
        // Prevent deleting current session
        if ($sessionId === request()->session()->getId()) {
            return false;
        }

        // Only database driver supports deleting specific sessions
        if (! $this->isSessionManagementSupported()) {
            Log::warning('Attempted to delete session with non-database driver', [
                'driver' => $this->getSessionDriver(),
                'user_id' => $user->id,
            ]);

            return false;
        }

        try {
            $deleted = DB::table('sessions')
                ->where('id', $sessionId)
                ->where('user_id', $user->id)
                ->delete();

            if ($deleted) {
                // Log activity using the user model
                $this->activityLogger->log(
                    $user,
                    ActivityAction::DELETE,
                    'User session terminated',
                    ['session_id' => $sessionId, 'module' => 'Security']
                );
            }

            return (bool) $deleted;
        } catch (Exception $exception) {
            Log::error('Failed to delete session', [
                'user_id' => $user->id,
                'session_id' => $sessionId,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Delete all sessions except the current one.
     * Only works with database session driver.
     *
     * @return int Number of sessions deleted
     */
    public function deleteOtherSessions(User $user, string $currentSessionId): int
    {
        // Only database driver supports deleting sessions
        if (! $this->isSessionManagementSupported()) {
            Log::warning('Attempted to delete other sessions with non-database driver', [
                'driver' => $this->getSessionDriver(),
                'user_id' => $user->id,
            ]);

            return 0;
        }

        try {
            $deleted = DB::table('sessions')
                ->where('user_id', $user->id)
                ->where('id', '!=', $currentSessionId)
                ->delete();

            if ($deleted > 0) {
                // Log activity using the user model
                $this->activityLogger->log(
                    $user,
                    ActivityAction::DELETE,
                    sprintf('All other sessions terminated (%d sessions)', $deleted),
                    ['sessions_count' => $deleted, 'module' => 'Security']
                );
            }

            return $deleted;
        } catch (Exception $exception) {
            Log::error('Failed to delete other sessions', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Validate that the user's account status allows login.
     *
     * @throws ValidationException
     */
    protected function validateAccountStatus(User $user, string $email, string $ip, ?string $userAgent): void
    {
        $status = $this->resolveStatus($user);

        if ($status === Status::ACTIVE) {
            return;
        }

        $reason = match ($status) {
            Status::SUSPENDED => LoginAttempt::REASON_ACCOUNT_SUSPENDED,
            Status::BANNED => LoginAttempt::REASON_ACCOUNT_BANNED,
            Status::PENDING => LoginAttempt::REASON_ACCOUNT_PENDING,
            default => 'account_inactive',
        };

        $message = match ($status) {
            Status::SUSPENDED => __('auth.account_suspended'),
            Status::BANNED => __('auth.account_banned'),
            Status::PENDING => __('auth.account_pending_approval'),
            default => __('auth.account_inactive'),
        };

        // Record the attempt
        LoginAttempt::recordFailure($email, $ip, $reason, $user->id, $userAgent);

        // Clear rate limiter for valid credentials with account issues
        if ($this->isRateLimitingEnabled()) {
            RateLimiter::clear($this->getThrottleKey($ip));
        }

        throw ValidationException::withMessages([
            'email' => [$message],
        ]);
    }

    /**
     * Handle a failed login attempt.
     *
     * @return int|null Remaining attempts, or null if rate limiting is disabled
     */
    protected function handleFailedLogin(string $email, string $ip, ?string $userAgent, string $reason): ?int
    {
        // Record the attempt
        $user = User::query()->where('email', $email)->first();
        LoginAttempt::recordFailure($email, $ip, $reason, $user?->id, $userAgent);

        $remainingAttempts = null;

        // Hit rate limiter and calculate remaining attempts
        if ($this->isRateLimitingEnabled()) {
            $lockoutTime = (int) setting('login_security_lockout_time', 60);
            $maxAttempts = (int) setting('login_security_limit_login_attempts', 5);
            $throttleKey = $this->getThrottleKey($ip);

            RateLimiter::hit($throttleKey, $lockoutTime * 60);

            $currentAttempts = RateLimiter::attempts($throttleKey);
            $remainingAttempts = max(0, $maxAttempts - $currentAttempts);
        }

        Log::info('Login attempt failed', [
            'email' => $email,
            'ip' => $ip,
            'reason' => $reason,
            'remaining_attempts' => $remainingAttempts,
        ]);

        return $remainingAttempts;
    }

    /**
     * Handle a successful login.
     */
    protected function handleSuccessfulLogin(User $user, string $ip, ?string $userAgent): void
    {
        // Record the attempt
        LoginAttempt::recordSuccess($user->email, $ip, $user->id, $userAgent);

        // Clear rate limiter
        if ($this->isRateLimitingEnabled()) {
            RateLimiter::clear($this->getThrottleKey($ip));
        }

        // Update last access
        $user->forceFill(['last_access' => now()])->save();

        // Log activity
        $this->activityLogger->log(
            $user,
            ActivityAction::LOGIN,
            'User logged in successfully.',
            [
                'ip' => $ip,
                'user_agent' => Str::limit($userAgent, 200),
            ],
            false,
            $user
        );

        Log::info('User logged in', [
            'user_id' => $user->id,
            'email' => $user->email,
            'ip' => $ip,
        ]);
    }

    /**
     * Get the throttle key for rate limiting.
     */
    protected function getThrottleKey(string $ip): string
    {
        return Str::transliterate($ip);
    }

    /**
     * Check if rate limiting is enabled.
     */
    protected function isRateLimitingEnabled(): bool
    {
        return (bool) setting('login_security_limit_login_attempts_enabled', false);
    }

    private function resolveStatus(User $user): ?Status
    {
        $status = $user->getAttribute('status');

        if ($status instanceof Status) {
            return $status;
        }

        if (is_string($status)) {
            return Status::tryFrom($status);
        }

        return null;
    }

    /**
     * Parse user agent string to extract device, platform, and browser info.
     */
    private function parseUserAgent(?string $userAgent): array
    {
        if (in_array($userAgent, [null, '', '0'], true)) {
            return [
                'device' => 'Unknown Device',
                'platform' => 'Unknown',
                'browser' => 'Unknown Browser',
            ];
        }

        // Detect device type
        $device = 'Desktop';
        if (preg_match('/mobile|android|iphone|ipad|phone/i', $userAgent)) {
            $device = 'Mobile';
        } elseif (preg_match('/tablet|ipad/i', $userAgent)) {
            $device = 'Tablet';
        }

        // Detect platform/OS
        $platform = 'Unknown';
        if (preg_match('/windows nt 10/i', $userAgent)) {
            $platform = 'Windows 10/11';
        } elseif (preg_match('/windows nt 6\.[23]/i', $userAgent)) {
            $platform = 'Windows 8';
        } elseif (preg_match('/windows nt 6\.1/i', $userAgent)) {
            $platform = 'Windows 7';
        } elseif (preg_match('/windows/i', $userAgent)) {
            $platform = 'Windows';
        } elseif (preg_match('/macintosh|mac os x/i', $userAgent)) {
            if (preg_match('/mac os x ([\d_]+)/i', $userAgent, $matches)) {
                $version = str_replace('_', '.', $matches[1]);
                $platform = 'macOS '.$version;
            } else {
                $platform = 'macOS';
            }
        } elseif (preg_match('/linux/i', $userAgent)) {
            $platform = 'Linux';
        } elseif (preg_match('/android ([\d\.]+)/i', $userAgent, $matches)) {
            $platform = 'Android '.$matches[1];
        } elseif (preg_match('/iphone|ipad|ipod/i', $userAgent)) {
            if (preg_match('/os ([\d_]+)/i', $userAgent, $matches)) {
                $version = str_replace('_', '.', $matches[1]);
                $platform = 'iOS '.$version;
            } else {
                $platform = 'iOS';
            }
        }

        // Detect browser
        $browser = 'Unknown Browser';
        if (preg_match('/edg\/([\d\.]+)/i', $userAgent, $matches)) {
            $browser = 'Edge '.$matches[1];
        } elseif (preg_match('/chrome\/([\d\.]+)/i', $userAgent, $matches)) {
            $browser = 'Chrome '.$matches[1];
        } elseif (preg_match('/firefox\/([\d\.]+)/i', $userAgent, $matches)) {
            $browser = 'Firefox '.$matches[1];
        } elseif (preg_match('/safari\/([\d\.]+)/i', $userAgent, $matches)) {
            if (! preg_match('/chrome|chromium/i', $userAgent)) {
                $browser = 'Safari '.$matches[1];
            }
        } elseif (preg_match('/opera|opr\/([\d\.]+)/i', $userAgent, $matches)) {
            $browser = 'Opera '.$matches[1];
        }

        return [
            'device' => $device,
            'platform' => $platform,
            'browser' => $browser,
        ];
    }
}
