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
 * NotFoundLog Model
 *
 * Tracks all 404 errors for SEO analysis and security monitoring.
 *
 * @property int $id
 * @property string $url
 * @property string|null $full_url
 * @property string|null $referer
 * @property string $ip_address
 * @property string|null $user_agent
 * @property int|null $user_id
 * @property string $method
 * @property bool $is_bot
 * @property bool $is_suspicious
 * @property array|null $metadata
 * @property Carbon $created_at
 * @property Carbon|null $deleted_at
 */
class NotFoundLog extends Model
{
    use HasFactory;
    use HasFactory;
    use SoftDeletes;

    /**
     * Suspicious path patterns for security detection.
     */
    public const SUSPICIOUS_PATTERNS = [
        // WordPress
        '/wp-admin',
        '/wp-content',
        '/wp-login',
        '/wp-includes',
        '/xmlrpc.php',
        // Config files
        '/.env',
        '/.git',
        '/config.php',
        '/config.yml',
        '/database.yml',
        '/.htaccess',
        '/.htpasswd',
        // Admin paths
        '/admin',
        '/administrator',
        '/phpmyadmin',
        '/pma',
        '/mysql',
        '/adminer',
        // API probing
        '/api/v1',
        '/graphql',
        '/.well-known/security.txt',
        // Common exploits
        '/shell',
        '/cmd',
        '/eval',
        '/exec',
        '/system',
        '/passwd',
        '/etc/passwd',
        // Backup files
        '.bak',
        '.backup',
        '.old',
        '.sql',
        '.zip',
        '.tar',
        '.gz',
        // Debug/logs
        '/debug',
        '/logs',
        '/log',
        '/error_log',
        '/access_log',
    ];

    /**
     * Bot user-agent patterns.
     */
    public const BOT_PATTERNS = [
        'bot',
        'crawler',
        'spider',
        'scraper',
        'curl',
        'wget',
        'python',
        'java/',
        'go-http',
        'headless',
        'phantom',
        'selenium',
        'puppeteer',
    ];

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'url',
        'full_url',
        'referer',
        'ip_address',
        'user_agent',
        'user_id',
        'method',
        'is_bot',
        'is_suspicious',
        'metadata',
        'created_at',
    ];

    /**
     * Get the user associated with this 404 log.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Detect if a URL path is suspicious.
     */
    public static function isSuspiciousPath(string $url): bool
    {
        $urlLower = strtolower($url);

        foreach (self::SUSPICIOUS_PATTERNS as $pattern) {
            if (str_contains($urlLower, strtolower($pattern))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect if user-agent is a bot.
     */
    public static function isBotUserAgent(?string $userAgent): bool
    {
        if (in_array($userAgent, [null, '', '0'], true)) {
            return true; // No user-agent is suspicious
        }

        $userAgentLower = strtolower($userAgent);

        foreach (self::BOT_PATTERNS as $pattern) {
            if (str_contains($userAgentLower, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Record a 404 log entry.
     */
    public static function record(
        string $url,
        string $ipAddress,
        ?string $fullUrl = null,
        ?string $referer = null,
        ?string $userAgent = null,
        ?int $userId = null,
        string $method = 'GET',
        array $metadata = []
    ): static {
        $isBot = self::isBotUserAgent($userAgent);
        $isSuspicious = self::isSuspiciousPath($url);

        /** @var static $log */
        $log = static::query()->create([
            'url' => substr($url, 0, 2048),
            'full_url' => $fullUrl ? substr($fullUrl, 0, 4096) : null,
            'referer' => $referer ? substr($referer, 0, 2048) : null,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'user_id' => $userId,
            'method' => strtoupper($method),
            'is_bot' => $isBot,
            'is_suspicious' => $isSuspicious,
            'metadata' => $metadata !== [] ? $metadata : null,
            'created_at' => now(),
        ]);

        return $log;
    }

    /**
     * Clean up old 404 logs.
     */
    public static function cleanupOld(int $daysToKeep = 30): int
    {
        return static::query()->where('created_at', '<', now()->subDays($daysToKeep))->forceDelete();
    }

    /**
     * Get top missing URLs.
     */
    public static function getTopMissingUrls(int $limit = 10, int $days = 30): array
    {
        return static::query()
            ->visibleToCurrentUser()
            ->whereNull('deleted_at')
            ->where('created_at', '>=', now()->subDays($days))
            ->select('url')
            ->selectRaw('COUNT(*) as hit_count')
            ->selectRaw('MAX(created_at) as last_hit')
            ->groupBy('url')
            ->orderByDesc('hit_count')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Get top referers linking to 404 pages.
     */
    public static function getTopReferers(int $limit = 10, int $days = 30): array
    {
        return static::query()
            ->visibleToCurrentUser()
            ->whereNull('deleted_at')
            ->whereNotNull('referer')
            ->where('referer', '!=', '')
            ->where('created_at', '>=', now()->subDays($days))
            ->select('referer')
            ->selectRaw('COUNT(*) as hit_count')
            ->groupBy('referer')
            ->orderByDesc('hit_count')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Get IPs with most 404 hits (potential scanners).
     */
    public static function getTopIps(int $limit = 10, int $days = 7): array
    {
        return static::query()
            ->visibleToCurrentUser()
            ->whereNull('deleted_at')
            ->where('created_at', '>=', now()->subDays($days))
            ->select('ip_address')
            ->selectRaw('COUNT(*) as hit_count')
            ->selectRaw('COUNT(DISTINCT url) as unique_urls')
            ->selectRaw('SUM(is_suspicious) as suspicious_count')
            ->groupBy('ip_address')
            ->orderByDesc('hit_count')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'is_bot' => 'boolean',
            'is_suspicious' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Scope to filter by IP address.
     */
    #[Scope]
    protected function forIp($query, string $ip)
    {
        return $query->where('ip_address', $ip);
    }

    /**
     * Scope to filter by URL.
     */
    #[Scope]
    protected function forUrl($query, string $url)
    {
        return $query->where('url', $url);
    }

    /**
     * Scope to get suspicious requests.
     */
    #[Scope]
    protected function suspicious($query)
    {
        return $query->where('is_suspicious', true);
    }

    /**
     * Scope to get bot requests.
     */
    #[Scope]
    protected function bots($query)
    {
        return $query->where('is_bot', true);
    }

    /**
     * Scope to get human requests.
     */
    #[Scope]
    protected function human($query)
    {
        return $query->where('is_bot', false);
    }

    /**
     * Scope to get recent logs within a time window.
     */
    #[Scope]
    protected function recent($query, int $minutes = 60)
    {
        return $query->where('created_at', '>=', now()->subMinutes($minutes));
    }

    /**
     * Scope to filter out logs by super users from non-super user views.
     */
    #[Scope]
    protected function visibleToCurrentUser($query)
    {
        $currentUser = Auth::user();

        // If no user is authenticated or current user is a super user, show all
        if (! $currentUser || $currentUser->isSuperUser()) {
            return $query;
        }

        // Get super user IDs
        $superUserIds = DB::table('model_has_roles')
            ->where('role_id', User::superUserRoleId())
            ->where('model_type', User::class)
            ->pluck('model_id');

        // For non-super users, exclude logs by super users
        return $query->where(function ($q) use ($superUserIds): void {
            $q->whereNull('user_id')
                ->orWhereNotIn('user_id', $superUserIds);
        });
    }
}
