<?php

namespace App\Http\Controllers;

use App\Enums\Status;
use App\Models\ActivityLog;
use App\Models\CustomMedia;
use App\Models\Role;
use App\Models\User;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Handle the root admin URL.
     *
     * Redirects to the dashboard if the user is authenticated,
     * otherwise redirects to the login page.
     */
    public function root(): RedirectResponse
    {
        if (Auth::check()) {
            return to_route('dashboard');
        }

        return to_route('login');
    }

    public function index(): Response|RedirectResponse
    {
        $user = auth()->user();

        if (! $user?->can('view_dashboard')) {
            if (module_enabled('agency') && $user?->hasRole('customer')) {
                return to_route('agency.websites.index');
            }

            abort(403);
        }

        if (module_enabled('agency') && $user->hasRole('customer')) {
            return to_route('agency.websites.index');
        }

        $mediaUsage = $user->can('view_media') ? CustomMedia::getUsedStorageSize() : null;
        $activitySummary = ActivityLog::getStatistics(30);
        $verifiedUsers = User::query()->whereNotNull('email_verified_at')->count();
        $totalUsers = User::query()->count();
        $defaultRoleNames = ['super_user', 'administrator', 'manager', 'customer', 'staff', 'user'];

        return Inertia::render('dashboard', [
            'summary' => [
                'totalUsers' => $totalUsers,
                'activeUsers' => User::query()->where('status', Status::ACTIVE)->count(),
                'verifiedUsers' => $verifiedUsers,
                'totalRoles' => Role::query()->count(),
                'customRoles' => Role::query()->whereNotIn('name', $defaultRoleNames)->count(),
                'totalMedia' => $user->can('view_media') ? CustomMedia::withoutTrashed()->count() : null,
                'imageMedia' => $user->can('view_media')
                    ? CustomMedia::withoutTrashed()->where('mime_type', 'like', 'image/%')->count()
                    : null,
                'recentActivityCount' => $activitySummary['total_activities'] ?? 0,
                'activeActors' => $activitySummary['unique_users'] ?? 0,
            ],
            'verificationRate' => $totalUsers > 0
                ? (int) round(($verifiedUsers / $totalUsers) * 100)
                : 0,
            'mediaUsage' => $mediaUsage ? [
                'usedSizeReadable' => $mediaUsage['used_size_readable'],
                'maxSizeReadable' => $mediaUsage['max_size_readable'],
                'remainingReadable' => $mediaUsage['remaining_readable'],
                'percentageUsed' => (float) $mediaUsage['percentage_used'],
            ] : null,
            'recentUsers' => User::query()
                ->latest()
                ->limit(5)
                ->get()
                ->map(fn (User $recentUser): array => [
                    'id' => $recentUser->id,
                    'name' => $recentUser->name ?: $recentUser->email ?: 'Unnamed user',
                    'email' => $recentUser->email,
                    'status' => $recentUser->status?->label() ?? 'Unknown',
                    'joinedAt' => $recentUser->created_at?->diffForHumans(),
                ])
                ->values()
                ->all(),
            'recentActivities' => ActivityLog::query()
                ->with('causer')
                ->latest()
                ->limit(6)
                ->get()
                ->map(function (ActivityLog $activity): array {
                    $causerName = $activity->causer?->name ?: $activity->causer?->email ?: 'System';

                    return [
                        'id' => $activity->id,
                        'title' => $activity->description ?: ($activity->event ? ucfirst(str_replace('_', ' ', $activity->event)) : 'Activity recorded'),
                        'meta' => sprintf('%s • %s', $causerName, $activity->created_at?->diffForHumans() ?? 'just now'),
                    ];
                })
                ->values()
                ->all(),
        ]);
    }

    public function cacheClear(): JsonResponse
    {
        try {
            Artisan::call('astero:recache');

            activity('cache cleared')
                ->causedBy(auth()->user())
                ->event('cache cleared')
                ->log(__('cache cleared successfully'));

            return response()->json([
                'status' => 1,
                'type' => 'toast',
                'message' => __('cache cleared successfully'),
                'refresh' => 'true',
            ]);
        } catch (Exception) {
            return response()->json([
                'status' => 0,
                'type' => 'toast',
                'message' => __('cache clear failed'),
            ]);
        }
    }
}
