<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\NotificationCategory;
use App\Enums\NotificationPriority;
use App\Models\Notification;
use App\Models\User;
use App\Notifications\BroadcastNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * NotificationService
 *
 * Business logic for notification management, filtering, and broadcasting.
 *
 * @example
 * $service = app(NotificationService::class);
 * $notifications = $service->getForUser($user, ['status' => 'unread']);
 * $service->markAllAsRead($user);
 */
class NotificationService
{
    /**
     * Get notifications for a user with optional filters.
     *
     * @param  array<string, mixed>  $filters  Available filters: status, category, priority, search, date_from, date_to
     */
    public function getForUser(
        User $user,
        array $filters = [],
        int $perPage = 10
    ): LengthAwarePaginator {
        $query = Notification::forUser($user->id)
            ->latestFirst();

        // Status filter (read/unread)
        if (! empty($filters['status'])) {
            match ($filters['status']) {
                'read' => $query->read(),
                'unread' => $query->unread(),
                default => null,
            };
        }

        // Category filter
        if (! empty($filters['category'])) {
            $query->ofCategory($filters['category']);
        }

        // Priority filter
        if (! empty($filters['priority'])) {
            $query->ofPriority($filters['priority']);
        }

        // Search filter
        if (! empty($filters['search'])) {
            $query->search($filters['search']);
        }

        // Date range filter
        if (! empty($filters['date_from']) || ! empty($filters['date_to'])) {
            $query->inDateRange(
                $filters['date_from'] ?? null,
                $filters['date_to'] ?? null
            );
        }

        return $query->paginate($perPage);
    }

    /**
     * Get unread notification count for a user.
     */
    public function getUnreadCount(User $user): int
    {
        return Notification::forUser($user->id)
            ->unread()
            ->count();
    }

    /**
     * Get unread notifications for dropdown (limited).
     */
    public function getUnreadForDropdown(User $user, int $limit = 10): Collection
    {
        return Notification::forUser($user->id)
            ->unread()
            ->latestFirst()
            ->limit($limit)
            ->get();
    }

    /**
     * Get recent notifications for dropdown (includes read).
     */
    public function getRecentForDropdown(User $user, int $limit = 10): Collection
    {
        return Notification::forUser($user->id)
            ->latestFirst()
            ->limit($limit)
            ->get();
    }

    /**
     * Find a notification by ID.
     */
    public function find(string $id): ?Notification
    {
        return Notification::query()->find($id);
    }

    /**
     * Find a notification by ID for a specific user.
     */
    public function findForUser(string $id, User $user): ?Notification
    {
        return Notification::forUser($user->id)
            ->where('id', $id)
            ->first();
    }

    /**
     * Mark a single notification as read.
     */
    public function markAsRead(Notification $notification): void
    {
        $notification->markAsRead();
    }

    /**
     * Mark a single notification as unread.
     */
    public function markAsUnread(Notification $notification): void
    {
        $notification->markAsUnread();
    }

    /**
     * Mark multiple notifications as read.
     *
     * @param  array<string>  $ids
     */
    public function markMultipleAsRead(User $user, array $ids): int
    {
        return Notification::forUser($user->id)
            ->whereIn('id', $ids)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /**
     * Mark all notifications as read for a user.
     */
    public function markAllAsRead(User $user): int
    {
        return Notification::forUser($user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /**
     * Delete a single notification (soft delete).
     */
    public function delete(Notification $notification): ?bool
    {
        /** @var bool|null */
        return $notification->delete();
    }

    /**
     * Delete multiple notifications.
     *
     * @param  array<string>  $ids
     */
    public function deleteMultiple(User $user, array $ids): int
    {
        return Notification::forUser($user->id)
            ->whereIn('id', $ids)
            ->delete();
    }

    /**
     * Delete all read notifications for a user.
     */
    public function deleteAllRead(User $user): int
    {
        return Notification::forUser($user->id)
            ->whereNotNull('read_at')
            ->delete();
    }

    /**
     * Permanently delete old notifications.
     */
    public function purgeOld(int $daysOld = 90): int
    {
        return Notification::query()->where('created_at', '<', now()->subDays($daysOld))
            ->whereNotNull('read_at')
            ->forceDelete();
    }

    /**
     * Broadcast a notification to users.
     *
     * @param  array<string, mixed>  $data  Notification data (title, text, priority, icon)
     * @param  array<int>|null  $roleIds  Role IDs to filter users (null = all users)
     * @return int Number of users notified
     */
    public function broadcast(array $data, ?array $roleIds = null): int
    {
        $query = User::query()
            ->where('notifications_enabled', true);

        if ($roleIds !== null && $roleIds !== []) {
            $query->whereHas('roles', fn ($q) => $q->whereIn('id', $roleIds));
        }

        $count = 0;
        $query->orderBy('id')->chunkById(200, function ($users) use ($data, &$count): void {
            foreach ($users as $user) {
                $user->notify(new BroadcastNotification($data));
                $count++;
            }
        });

        return $count;
    }

    /**
     * Get category options for filter dropdown.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function getCategoryOptions(): array
    {
        return NotificationCategory::options();
    }

    /**
     * Get priority options for filter dropdown.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function getPriorityOptions(): array
    {
        return NotificationPriority::options();
    }

    /**
     * Get status options for filter dropdown.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function getStatusOptions(): array
    {
        return [
            ['value' => '', 'label' => 'All'],
            ['value' => 'read', 'label' => 'Read'],
            ['value' => 'unread', 'label' => 'Unread'],
        ];
    }

    /**
     * Check if notifications are enabled for a user.
     */
    public function isEnabledForUser(User $user): bool
    {
        return $user->notifications_enabled ?? true;
    }

    /**
     * Toggle notifications enabled/disabled for a user.
     */
    public function toggleForUser(User $user, bool $enabled): void
    {
        $user->update(['notifications_enabled' => $enabled]);
    }

    /**
     * Get notification preferences for a user.
     *
     * @return array<string, mixed>
     */
    public function getPreferencesForUser(User $user): array
    {
        $defaults = [
            'enabled' => true,
            'categories' => array_fill_keys(NotificationCategory::values(), true),
            'priorities' => array_fill_keys(NotificationPriority::values(), true),
        ];

        $stored = $user->notification_preferences ?? [];

        return array_replace_recursive($defaults, $stored);
    }

    /**
     * Update notification preferences for a user.
     *
     * @param  array<string, mixed>  $preferences
     */
    public function updatePreferencesForUser(User $user, array $preferences): void
    {
        $user->update(['notification_preferences' => $preferences]);
    }

    /**
     * Check if a category is enabled for a user.
     */
    public function isCategoryEnabledForUser(User $user, NotificationCategory|string $category): bool
    {
        if (! $this->isEnabledForUser($user)) {
            return false;
        }

        $categoryValue = $category instanceof NotificationCategory ? $category->value : $category;
        $preferences = $this->getPreferencesForUser($user);

        return $preferences['categories'][$categoryValue] ?? true;
    }

    /**
     * Get notification statistics for a user.
     *
     * @return array<string, int>
     */
    public function getStatsForUser(User $user): array
    {
        $baseQuery = Notification::forUser($user->id);

        return [
            'total' => (clone $baseQuery)->count(),
            'unread' => (clone $baseQuery)->unread()->count(),
            'read' => (clone $baseQuery)->read()->count(),
            'high_priority' => (clone $baseQuery)->highPriority()->unread()->count(),
        ];
    }

    /**
     * Get notification count by category for a user.
     *
     * @return array<string, int>
     */
    public function getCountByCategory(User $user): array
    {
        $counts = Notification::forUser($user->id)
            ->selectRaw('category, COUNT(*) as count')
            ->groupBy('category')
            ->pluck('count', 'category')
            ->toArray();

        // Fill in zeros for missing categories
        foreach (NotificationCategory::values() as $category) {
            if (! isset($counts[$category])) {
                $counts[$category] = 0;
            }
        }

        return $counts;
    }

    /**
     * Get notification count by priority for a user.
     *
     * @return array<string, int>
     */
    public function getCountByPriority(User $user): array
    {
        $counts = Notification::forUser($user->id)
            ->selectRaw('priority, COUNT(*) as count')
            ->groupBy('priority')
            ->pluck('count', 'priority')
            ->toArray();

        // Fill in zeros for missing priorities
        foreach (NotificationPriority::values() as $priority) {
            if (! isset($counts[$priority])) {
                $counts[$priority] = 0;
            }
        }

        return $counts;
    }
}
