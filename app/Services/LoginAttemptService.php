<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\ScaffoldServiceInterface;
use App\Definitions\LoginAttemptDefinition;
use App\Http\Resources\LoginAttemptResource;
use App\Models\LoginAttempt;
use App\Models\User;
use App\Scaffold\ScaffoldDefinition;
use App\Traits\Scaffoldable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

/**
 * LoginAttemptService
 *
 * Scaffold-based service for managing login attempts with DataGrid support.
 */
class LoginAttemptService implements ScaffoldServiceInterface
{
    use Scaffoldable;

    // ================================================================
    // REQUIRED SCAFFOLD METHODS
    // ================================================================

    public function getScaffoldDefinition(): ScaffoldDefinition
    {
        return new LoginAttemptDefinition;
    }

    // ================================================================
    // STATISTICS (for tab counts)
    // ================================================================

    public function getStatistics(): array
    {
        // Apply visibility scope to hide super user login attempts from statistics
        return [
            'total' => LoginAttempt::visibleToCurrentUser()->whereNull('deleted_at')->count(),
            'success' => LoginAttempt::visibleToCurrentUser()->where('status', LoginAttempt::STATUS_SUCCESS)
                ->whereNull('deleted_at')->count(),
            'failed' => LoginAttempt::visibleToCurrentUser()->where('status', LoginAttempt::STATUS_FAILED)
                ->whereNull('deleted_at')->count(),
            'blocked' => LoginAttempt::visibleToCurrentUser()->where('status', LoginAttempt::STATUS_BLOCKED)
                ->whereNull('deleted_at')->count(),
            'trash' => LoginAttempt::visibleToCurrentUser()->onlyTrashed()->count(),
        ];
    }

    /**
     * Recent activity stats for the LoginAttempt show page.
     *
     * @return array{email: array{total:int,success:int,failed:int,blocked:int}, ip: array{total:int,success:int,failed:int,blocked:int,unique_emails:int}}
     */
    public function getRecentActivityStats(LoginAttempt $loginAttempt, int $minutes = 1440): array
    {
        $baseEmail = LoginAttempt::query()
            ->where('email', $loginAttempt->email)
            ->recent($minutes);

        $baseIp = LoginAttempt::query()
            ->where('ip_address', $loginAttempt->ip_address)
            ->recent($minutes);

        $emailCounts = (clone $baseEmail)
            ->select('status')
            ->selectRaw('COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $ipCounts = (clone $baseIp)
            ->select('status')
            ->selectRaw('COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return [
            'email' => [
                'total' => (clone $baseEmail)->count(),
                'success' => (int) ($emailCounts[LoginAttempt::STATUS_SUCCESS] ?? 0),
                'failed' => (int) ($emailCounts[LoginAttempt::STATUS_FAILED] ?? 0),
                'blocked' => (int) ($emailCounts[LoginAttempt::STATUS_BLOCKED] ?? 0),
            ],
            'ip' => [
                'total' => (clone $baseIp)->count(),
                'success' => (int) ($ipCounts[LoginAttempt::STATUS_SUCCESS] ?? 0),
                'failed' => (int) ($ipCounts[LoginAttempt::STATUS_FAILED] ?? 0),
                'blocked' => (int) ($ipCounts[LoginAttempt::STATUS_BLOCKED] ?? 0),
                'unique_emails' => (clone $baseIp)->distinct('email')->count('email'),
            ],
        ];
    }

    // ================================================================
    // OVERRIDE: Skip audit fields (login_attempts table has no deleted_by/updated_by)
    // ================================================================

    public function delete(Model $model): void
    {
        DB::transaction(function () use ($model): void {
            $this->beforeDelete($model);
            $model->delete();
            $this->afterDelete($model);
        });
    }

    public function restore(int|string $id): Model
    {
        return DB::transaction(function () use ($id) {
            $modelClass = $this->getModelClass();
            $model = $modelClass::withTrashed()->findOrFail($id);

            $model->restore();
            $this->afterRestore($model);

            return $model->fresh();
        });
    }

    // ================================================================
    // CLEANUP FUNCTIONALITY
    // ================================================================

    /**
     * Clean up old login attempts
     */
    public function cleanupOldAttempts(int $daysToKeep = 30): int
    {
        return LoginAttempt::query()->where('created_at', '<', now()->subDays($daysToKeep))
            ->forceDelete();
    }

    // ================================================================
    // RATE LIMIT / BLOCKED IPS MANAGEMENT
    // ================================================================

    /**
     * Get blocked IPs
     */
    public function getBlockedIps(): array
    {
        $blockedAttemptsQuery = LoginAttempt::visibleToCurrentUser()
            ->where('status', LoginAttempt::STATUS_BLOCKED)
            ->whereNull('deleted_at')
            ->select('ip_address')
            ->selectRaw('count(*) as attempt_count')
            ->selectRaw('MAX(created_at) as last_attempt')
            ->groupBy('ip_address')
            ->orderBy('attempt_count', 'desc');

        $this->applySuperUserBlockedIpExclusion($blockedAttemptsQuery);

        $blockedAttempts = $blockedAttemptsQuery
            ->limit(50)
            ->get();

        /** @var Collection<int, LoginAttempt> $blockedAttempts */
        return $blockedAttempts->map(function (LoginAttempt $item): array {
            $ipAddress = (string) $item->getAttribute('ip_address');
            $attemptCount = (int) ($item->getAttribute('attempt_count') ?? 0);
            $lastAttempt = $item->getAttribute('last_attempt');

            $key = 'login_attempts:'.$ipAddress;
            $remainingSeconds = RateLimiter::availableIn($key);

            return [
                'ip_address' => $ipAddress,
                'attempt_count' => $attemptCount,
                'last_attempt' => $lastAttempt,
                'remaining_time' => $remainingSeconds > 0 ? gmdate('H:i:s', $remainingSeconds) : null,
                'is_blocked' => $remainingSeconds > 0,
            ];
        })->all();
    }

    /**
     * Clear rate limit for an IP address
     */
    public function clearRateLimit(?string $ipAddress = null, bool $clearAll = false): int
    {
        $count = 0;

        if ($clearAll) {
            // Get all unique IPs
            $ipsQuery = LoginAttempt::query()->whereNull('deleted_at');
            $this->applySuperUserBlockedIpExclusion($ipsQuery);

            if ($this->shouldHideSuperUserIps()) {
                $ipsQuery->visibleToCurrentUser();
            }

            $ips = $ipsQuery->distinct()->pluck('ip_address');

            foreach ($ips as $ip) {
                RateLimiter::clear('login_attempts:'.$ip);
                $count++;
            }
        } elseif ($ipAddress) {
            if ($this->isSuperUserBlockedIp($ipAddress)) {
                return 0;
            }

            RateLimiter::clear('login_attempts:'.$ipAddress);
            $count = 1;
        }

        return $count;
    }

    protected function getResourceClass(): ?string
    {
        return LoginAttemptResource::class;
    }

    // ================================================================
    // EAGER LOADING
    // ================================================================

    protected function getEagerLoadRelationships(): array
    {
        return [
            'user:id,first_name,last_name,email',
        ];
    }

    // ================================================================
    // QUERY BUILDING (Status Tab Support)
    // ================================================================

    protected function buildListQuery(Request $request): Builder
    {
        $query = LoginAttempt::query();

        // Apply visibility scope to hide super user login attempts from non-super users
        $query->visibleToCurrentUser();

        // Get status from request or route
        $status = $request->input('status') ?? $request->route('status') ?? 'all';

        // Handle status filtering
        if ($status === 'trash') {
            $query->onlyTrashed();
        } elseif ($status === 'success') {
            $query->where('status', LoginAttempt::STATUS_SUCCESS)->whereNull('deleted_at');
        } elseif ($status === 'failed') {
            $query->where('status', LoginAttempt::STATUS_FAILED)->whereNull('deleted_at');
        } elseif ($status === 'blocked') {
            $query->where('status', LoginAttempt::STATUS_BLOCKED)->whereNull('deleted_at');
        } else {
            // 'all' - only non-deleted
            $query->whereNull('deleted_at');
        }

        // Merge route status into request for filters
        if (! $request->has('status') && $request->route('status')) {
            $request->merge(['status' => $status]);
        }

        // Apply standard scaffold methods
        $this->applyEagerLoading($query);
        $this->applySearch($query, $request);
        $this->applyFilters($query, $request);
        $this->applySorting($query, $request);
        $this->customizeListQuery($query, $request);

        return $query;
    }

    // ================================================================
    // CUSTOM FILTER HANDLING
    // ================================================================

    protected function applyFilters(Builder $query, Request $request): void
    {
        // Date range filter
        if ($from = $request->input('created_at_from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->input('created_at_to')) {
            $query->whereDate('created_at', '<=', $to);
        }
    }

    // ================================================================
    // EMPTY STATE CONFIGURATION
    // ================================================================

    protected function getEmptyStateConfig(): array
    {
        return [
            'icon' => 'ri-shield-keyhole-line',
            'title' => 'No Login Attempts Found',
            'message' => 'Login attempts will appear here once users try to log in.',
            'showAddButton' => false,
        ];
    }

    private function shouldHideSuperUserIps(): bool
    {
        $currentUser = auth()->user();

        return $currentUser && ! $currentUser->isSuperUser();
    }

    private function getSuperUserIdentifiers(): array
    {
        $superUserRoleId = User::superUserRoleId();
        $superUserIds = DB::table('model_has_roles')
            ->where('role_id', $superUserRoleId)
            ->where('model_type', User::class)
            ->pluck('model_id');

        $superUserEmails = User::query()->whereIn('id', $superUserIds)->pluck('email');

        return [$superUserIds, $superUserEmails];
    }

    private function applySuperUserBlockedIpExclusion(Builder $query): void
    {
        if (! $this->shouldHideSuperUserIps()) {
            return;
        }

        [$superUserIds, $superUserEmails] = $this->getSuperUserIdentifiers();

        if ($superUserIds->isEmpty() && $superUserEmails->isEmpty()) {
            return;
        }

        $query->whereNotIn('ip_address', function ($sub) use ($superUserIds, $superUserEmails): void {
            $sub->select('ip_address')
                ->from((new LoginAttempt)->getTable())
                ->whereNull('deleted_at')
                ->where('status', LoginAttempt::STATUS_BLOCKED)
                ->where(function ($q) use ($superUserIds, $superUserEmails): void {
                    if ($superUserIds->isNotEmpty()) {
                        $q->whereIn('user_id', $superUserIds);
                    }

                    if ($superUserEmails->isNotEmpty()) {
                        $method = $superUserIds->isNotEmpty() ? 'orWhereIn' : 'whereIn';
                        $q->{$method}('email', $superUserEmails);
                    }
                });
        });
    }

    private function isSuperUserBlockedIp(string $ipAddress): bool
    {
        if (! $this->shouldHideSuperUserIps()) {
            return false;
        }

        [$superUserIds, $superUserEmails] = $this->getSuperUserIdentifiers();

        if ($superUserIds->isEmpty() && $superUserEmails->isEmpty()) {
            return false;
        }

        return LoginAttempt::query()
            ->whereNull('deleted_at')
            ->where('status', LoginAttempt::STATUS_BLOCKED)
            ->where('ip_address', $ipAddress)
            ->where(function ($q) use ($superUserIds, $superUserEmails): void {
                if ($superUserIds->isNotEmpty()) {
                    $q->whereIn('user_id', $superUserIds);
                }

                if ($superUserEmails->isNotEmpty()) {
                    $method = $superUserIds->isNotEmpty() ? 'orWhereIn' : 'whereIn';
                    $q->{$method}('email', $superUserEmails);
                }
            })
            ->exists();
    }
}
