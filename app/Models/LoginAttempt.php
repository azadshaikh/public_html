<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Login Attempt Model
 *
 * Tracks all login attempts for security monitoring and rate limiting.
 *
 * @property int $id
 * @property string $email
 * @property string $ip_address
 * @property string|null $user_agent
 * @property string $status
 * @property string|null $failure_reason
 * @property int|null $user_id
 * @property array|null $metadata
 * @property Carbon $created_at
 * @property Carbon|null $deleted_at
 */
class LoginAttempt extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * Status constants.
     */
    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const STATUS_BLOCKED = 'blocked';

    public const STATUS_CLEARED = 'cleared';

    /**
     * Common failure reasons.
     */
    public const REASON_INVALID_CREDENTIALS = 'invalid_credentials';

    public const REASON_ACCOUNT_SUSPENDED = 'account_suspended';

    public const REASON_ACCOUNT_BANNED = 'account_banned';

    public const REASON_ACCOUNT_PENDING = 'account_pending';

    public const REASON_RATE_LIMITED = 'rate_limited';

    public const REASON_EMAIL_NOT_VERIFIED = 'email_not_verified';

    public const REASON_SOCIAL_AUTH_FAILED = 'social_auth_failed';

    public const REASON_SOCIAL_EMAIL_MISSING = 'social_email_missing';

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'email',
        'ip_address',
        'user_agent',
        'status',
        'failure_reason',
        'user_id',
        'metadata',
        'created_at',
    ];

    /**
     * Get the user associated with this login attempt.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Count recent failed attempts for an IP.
     */
    public static function recentFailedCountForIp(string $ip, int $minutes = 60): int
    {
        return static::query()->forIp($ip)
            ->failed()
            ->recent($minutes)
            ->count();
    }

    /**
     * Count recent failed attempts for an email.
     */
    public static function recentFailedCountForEmail(string $email, int $minutes = 60): int
    {
        return static::query()->forEmail($email)
            ->failed()
            ->recent($minutes)
            ->count();
    }

    /**
     * Record a login attempt.
     */
    public static function record(
        string $email,
        string $ipAddress,
        string $status,
        ?string $failureReason = null,
        ?int $userId = null,
        ?string $userAgent = null,
        array $metadata = []
    ): static {
        /** @var static $attempt */
        $attempt = static::query()->create([
            'email' => strtolower($email),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'status' => $status,
            'failure_reason' => $failureReason,
            'user_id' => $userId,
            'metadata' => $metadata !== [] ? $metadata : null,
            'created_at' => now(),
        ]);

        return $attempt;
    }

    /**
     * Record a successful login.
     */
    public static function recordSuccess(
        string $email,
        string $ipAddress,
        int $userId,
        ?string $userAgent = null,
        array $metadata = []
    ): static {
        return static::record(
            $email,
            $ipAddress,
            self::STATUS_SUCCESS,
            null,
            $userId,
            $userAgent,
            $metadata
        );
    }

    /**
     * Record a failed login.
     */
    public static function recordFailure(
        string $email,
        string $ipAddress,
        string $reason,
        ?int $userId = null,
        ?string $userAgent = null,
        array $metadata = []
    ): static {
        return static::record(
            $email,
            $ipAddress,
            self::STATUS_FAILED,
            $reason,
            $userId,
            $userAgent,
            $metadata
        );
    }

    /**
     * Record a blocked attempt (rate limited).
     */
    public static function recordBlocked(
        string $email,
        string $ipAddress,
        ?string $userAgent = null,
        array $metadata = []
    ): static {
        return static::record(
            $email,
            $ipAddress,
            self::STATUS_BLOCKED,
            self::REASON_RATE_LIMITED,
            null,
            $userAgent,
            $metadata
        );
    }

    /**
     * Clean up old login attempts.
     */
    public static function cleanupOld(int $daysToKeep = 30): int
    {
        return static::query()->where('created_at', '<', now()->subDays($daysToKeep))->delete();
    }

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Scope to get failed attempts.
     */
    #[Scope]
    protected function failed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope to get successful attempts.
     */
    #[Scope]
    protected function successful($query)
    {
        return $query->where('status', self::STATUS_SUCCESS);
    }

    /**
     * Scope to get attempts for a specific IP.
     */
    #[Scope]
    protected function forIp($query, string $ip)
    {
        return $query->where('ip_address', $ip);
    }

    /**
     * Scope to get attempts for a specific email.
     */
    #[Scope]
    protected function forEmail($query, string $email)
    {
        return $query->where('email', $email);
    }

    /**
     * Scope to get recent attempts within a time window.
     */
    #[Scope]
    protected function recent($query, int $minutes = 60)
    {
        return $query->where('created_at', '>=', now()->subMinutes($minutes));
    }

    /**
     * Scope to filter out login attempts by super users from non-super user views.
     * Super users can see all login attempts, but normal users cannot see super user login attempts.
     */
    #[Scope]
    protected function visibleToCurrentUser($query)
    {
        $currentUser = Auth::user();

        // If no user is authenticated or current user is a super user, show all
        if (! $currentUser || $currentUser->isSuperUser()) {
            return $query;
        }

        // Get super user IDs and emails
        $superUserIds = DB::table('model_has_roles')
            ->where('role_id', User::superUserRoleId())
            ->where('model_type', User::class)
            ->pluck('model_id');

        $superUserEmails = User::query()->whereIn('id', $superUserIds)->pluck('email');

        // For non-super users, exclude login attempts by super users
        // Check both user_id and email (for failed attempts that may not have user_id)
        return $query->where(function ($q) use ($superUserIds, $superUserEmails): void {
            $q->where(function ($subQ) use ($superUserIds): void {
                $subQ->whereNull('user_id')
                    ->orWhereNotIn('user_id', $superUserIds);
            })->whereNotIn('email', $superUserEmails);
        });
    }
}
