<?php

namespace App\Http\Middleware;

use App\Models\LoginAttempt;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rate limit login attempts to prevent brute force attacks.
 *
 * This middleware only CHECKS if the user is rate limited.
 * It does NOT increment the counter - that's done by AuthService on failed attempts.
 */
class LimitLoginAttempts
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if login attempt limiting is enabled
        $enableLimitLoginAttempts = setting('login_security_limit_login_attempts_enabled', false);

        if (! $enableLimitLoginAttempts) {
            return $next($request);
        }

        // Get the maximum number of attempts and lockout time from settings
        $maxAttempts = (int) setting('login_security_limit_login_attempts', 5);
        $lockoutMinutes = (int) setting('login_security_lockout_time', 60);

        // Get the throttle key (IP-based only for security)
        $throttleKey = $this->throttleKey($request);
        $ip = $request->ip();

        // Check rate limiter (primary check)
        if (RateLimiter::tooManyAttempts($throttleKey, $maxAttempts)) {
            $this->recordBlockedAttemptOnce($request, $throttleKey);

            $seconds = RateLimiter::availableIn($throttleKey);
            $timeString = $this->formatLockoutTime($seconds);

            throw ValidationException::withMessages([
                'email' => __('auth.throttle_detailed', ['time' => $timeString]),
            ]);
        }

        // Database fallback check (in case cache was cleared)
        // This provides extra security against cache bypass attacks
        // Excludes 'cleared' status (set when user resets password)
        $recentFailedAttempts = LoginAttempt::query()->where('ip_address', $ip)
            ->where('status', LoginAttempt::STATUS_FAILED)
            ->where('created_at', '>=', now()->subMinutes($lockoutMinutes))
            ->count();

        if ($recentFailedAttempts >= $maxAttempts) {
            // Restore the rate limiter from database state
            $oldestAttempt = LoginAttempt::query()->where('ip_address', $ip)
                ->where('status', LoginAttempt::STATUS_FAILED)
                ->where('created_at', '>=', now()->subMinutes($lockoutMinutes))
                ->oldest()
                ->first();

            $remainingSeconds = $oldestAttempt
                ? $lockoutMinutes * 60 - now()->diffInSeconds($oldestAttempt->created_at)
                : $lockoutMinutes * 60;

            $remainingSeconds = max(60, $remainingSeconds); // At least 1 minute

            // Re-populate rate limiter
            for ($i = 0; $i < $maxAttempts; $i++) {
                RateLimiter::hit($throttleKey, $remainingSeconds);
            }

            $this->recordBlockedAttemptOnce($request, $throttleKey);

            $timeString = $this->formatLockoutTime($remainingSeconds);

            throw ValidationException::withMessages([
                'email' => __('auth.throttle_detailed', ['time' => $timeString]),
            ]);
        }

        // DO NOT increment here - AuthService handles this on failed attempts only
        return $next($request);
    }

    /**
     * Record a blocked attempt only once per lockout period.
     *
     * Uses a separate rate limiter key to track if we've already logged this block.
     */
    protected function recordBlockedAttemptOnce(Request $request, string $throttleKey): void
    {
        $blockLogKey = 'login_block_logged:'.$throttleKey;

        // Only record if we haven't already logged this lockout
        if (RateLimiter::tooManyAttempts($blockLogKey, 1)) {
            return; // Already recorded for this lockout period
        }

        // Record the blocked attempt
        $email = $request->input('email');
        LoginAttempt::recordBlocked(
            is_string($email) ? $email : '',
            $request->ip(),
            $request->userAgent()
        );

        // Mark as logged - expires when lockout expires
        $lockoutTime = (int) setting('login_security_lockout_time', 60);
        RateLimiter::hit($blockLogKey, $lockoutTime * 60);
    }

    /**
     * Format lockout time for display.
     */
    protected function formatLockoutTime(int $seconds): string
    {
        if ($seconds < 60) {
            return __('auth.time_seconds', ['count' => $seconds]);
        }

        $minutes = (int) ceil($seconds / 60);

        if ($minutes < 60) {
            return trans_choice('auth.time_minutes', $minutes, ['count' => $minutes]);
        }

        $hours = (int) floor($minutes / 60);
        $remainingMinutes = $minutes % 60;

        if ($remainingMinutes > 0) {
            return __('auth.time_hours_minutes', [
                'hours' => $hours,
                'minutes' => $remainingMinutes,
            ]);
        }

        return trans_choice('auth.time_hours', $hours, ['count' => $hours]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     *
     * Uses IP address only (not email) to prevent attackers from
     * trying different emails to bypass rate limiting.
     */
    protected function throttleKey(Request $request): string
    {
        return Str::transliterate($request->ip());
    }
}
