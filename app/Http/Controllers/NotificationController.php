<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\NotificationCategory;
use App\Enums\NotificationPriority;
use App\Models\Notification;
use App\Services\NotificationService;
use App\Traits\ActivityTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;

/**
 * NotificationController
 *
 * Handles notification center, CRUD operations, and real-time polling.
 */
class NotificationController extends Controller implements HasMiddleware
{
    use ActivityTrait;

    protected string $activityLogModule = 'Notifications';

    public function __construct(private readonly NotificationService $notificationService) {}

    /**
     * Define middleware for the controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('auth'),
        ];
    }

    /**
     * Display the notification center page.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        // Map 'filter' to 'status' for service compatibility
        $filters = $request->only(['category', 'priority', 'search', 'date_from', 'date_to']);
        if ($request->has('filter')) {
            $filters['status'] = $request->input('filter');
        }

        $notifications = $this->notificationService->getForUser($user, $filters);
        $stats = $this->notificationService->getStatsForUser($user);

        return Inertia::render('notifications/index', [
            'notifications' => $notifications,
            'stats' => $stats,
            'filters' => [
                'search' => $request->input('search', ''),
                'filter' => $request->input('filter', 'all'),
                'category' => $request->input('category', ''),
                'priority' => $request->input('priority', ''),
            ],
            'categoryOptions' => $this->notificationService->getCategoryOptions(),
            'priorityOptions' => $this->notificationService->getPriorityOptions(),
            'statusOptions' => $this->notificationService->getStatusOptions(),
            'status' => session('status'),
            'error' => session('error'),
        ]);
    }

    /**
     * Get notifications list partial (for AJAX/Unpoly refresh).
     */
    public function list(Request $request): View
    {
        $user = $request->user();
        $filters = $request->only(['status', 'category', 'priority', 'search', 'date_from', 'date_to']);

        $notifications = $this->notificationService->getForUser($user, $filters);

        return view('app.notifications._list', [
            'notifications' => $notifications,
        ]);
    }

    /**
     * Get unread notification count (for polling).
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $count = $this->notificationService->getUnreadCount($request->user());

        return response()->json([
            'count' => $count,
        ]);
    }

    /**
     * Get dropdown notifications (for topbar).
     */
    public function dropdown(Request $request): JsonResponse
    {
        $notifications = $this->notificationService->getUnreadForDropdown($request->user());
        $count = $this->notificationService->getUnreadCount($request->user());

        return response()->json([
            'notifications' => $notifications->map(fn (Notification $n): array => $n->toDropdownArray()),
            'count' => $count,
        ]);
    }

    /**
     * Show a single notification.
     */
    public function show(Request $request, Notification $notification): JsonResponse|RedirectResponse|View
    {
        // Ensure user owns this notification
        abort_unless($notification->belongsToUser($request->user()->id), 403, 'Unauthorized');

        // Mark as read when viewed
        $this->notificationService->markAsRead($notification);

        if ($request->wantsJson()) {
            return response()->json([
                'notification' => $notification->toDropdownArray(),
            ]);
        }

        // Redirect to URL if exists and is not the notifications index
        $notificationUrl = $notification->url;
        $notificationsIndexUrl = route('app.notifications.index');

        if ($notificationUrl && ! str_starts_with($notificationUrl, $notificationsIndexUrl)) {
            return redirect($notificationUrl);
        }

        return view('app.notifications.show', [
            'notification' => $notification,
        ]);
    }

    /**
     * Mark single notification as read.
     */
    public function markAsRead(Request $request, Notification $notification): JsonResponse|RedirectResponse
    {
        // Ensure user owns this notification
        if (! $notification->belongsToUser($request->user()->id)) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            abort(403, 'Unauthorized');
        }

        $this->notificationService->markAsRead($notification);

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 1,
                'type' => 'toast',
                'message' => __('notifications.notification_marked_read'),
            ]);
        }

        return back()
            ->with('success', __('notifications.notification_marked_read'));
    }

    /**
     * Mark single notification as unread.
     */
    public function markAsUnread(Request $request, Notification $notification): JsonResponse|RedirectResponse
    {
        // Ensure user owns this notification
        if (! $notification->belongsToUser($request->user()->id)) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            abort(403, 'Unauthorized');
        }

        $this->notificationService->markAsUnread($notification);

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 1,
                'type' => 'toast',
                'message' => __('notifications.notification_marked_unread'),
            ]);
        }

        return back()
            ->with('success', __('notifications.notification_marked_unread'));
    }

    /**
     * Mark multiple notifications as read.
     */
    public function markMultipleAsRead(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['required', 'string'],
        ]);

        $count = $this->notificationService->markMultipleAsRead(
            $request->user(),
            $request->input('ids')
        );

        return response()->json([
            'status' => 1,
            'type' => 'toast',
            'message' => $count.' notification(s) marked as read.',
            'count' => $count,
        ]);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(Request $request): JsonResponse|RedirectResponse
    {
        $count = $this->notificationService->markAllAsRead($request->user());

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 1,
                'type' => 'toast',
                'message' => __('notifications.all_notifications_marked_read'),
                'count' => $count,
            ]);
        }

        return back()
            ->with('success', __('notifications.all_notifications_marked_read'));
    }

    /**
     * Delete single notification.
     */
    public function destroy(Request $request, Notification $notification): JsonResponse|RedirectResponse
    {
        // Ensure user owns this notification
        if (! $notification->belongsToUser($request->user()->id)) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            abort(403, 'Unauthorized');
        }

        $this->notificationService->delete($notification);

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 1,
                'type' => 'toast',
                'message' => __('notifications.notification_deleted'),
            ]);
        }

        return to_route('app.notifications.index')
            ->with('success', __('notifications.notification_deleted'));
    }

    /**
     * Delete multiple notifications.
     */
    public function deleteMultiple(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['required', 'string'],
        ]);

        $count = $this->notificationService->deleteMultiple(
            $request->user(),
            $request->input('ids')
        );

        return response()->json([
            'status' => 1,
            'type' => 'toast',
            'message' => $count.' notification(s) deleted.',
            'count' => $count,
        ]);
    }

    /**
     * Delete all read notifications.
     */
    public function deleteAllRead(Request $request): JsonResponse|RedirectResponse
    {
        $count = $this->notificationService->deleteAllRead($request->user());

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 1,
                'type' => 'toast',
                'message' => __('notifications.read_notifications_deleted'),
                'count' => $count,
            ]);
        }

        return back()
            ->with('success', __('notifications.read_notifications_deleted'));
    }

    /**
     * Toggle notifications enabled/disabled for user.
     */
    public function toggleEnabled(Request $request): JsonResponse
    {
        $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        $this->notificationService->toggleForUser(
            $request->user(),
            $request->boolean('enabled')
        );

        $status = $request->boolean('enabled') ? 'enabled' : 'disabled';

        return response()->json([
            'status' => 1,
            'type' => 'toast',
            'message' => sprintf('Notifications %s.', $status),
        ]);
    }

    /**
     * Get notification statistics.
     */
    public function stats(Request $request): JsonResponse
    {
        $stats = $this->notificationService->getStatsForUser($request->user());
        $byCategory = $this->notificationService->getCountByCategory($request->user());

        return response()->json([
            'stats' => $stats,
            'by_category' => $byCategory,
        ]);
    }

    /**
     * Show notification preferences page.
     */
    public function preferences(Request $request): View
    {
        $user = $request->user();

        return view('app.notifications.preferences', [
            'user' => $user,
            'preferences' => $this->notificationService->getPreferencesForUser($user),
        ]);
    }

    /**
     * Update notification preferences.
     */
    public function updatePreferences(Request $request): RedirectResponse
    {
        $user = $request->user();

        $preferences = [
            'categories' => [],
            'priorities' => [],
        ];

        // Process category preferences (unchecked = false, checked = true)
        foreach (NotificationCategory::cases() as $category) {
            $preferences['categories'][$category->value] = $request->has('preferences.categories.'.$category->value);
        }

        // Process priority preferences
        foreach (NotificationPriority::cases() as $priority) {
            $preferences['priorities'][$priority->value] = $request->has('preferences.priorities.'.$priority->value);
        }

        // Update notifications enabled flag
        $user->update([
            'notifications_enabled' => $request->boolean('notifications_enabled'),
            'notification_preferences' => $preferences,
        ]);

        return to_route('app.notifications.preferences')
            ->with('success', __('notifications.preferences_updated'));
    }
}
