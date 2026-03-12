<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\NotificationPriority;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\BroadcastNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

/**
 * BroadcastNotificationController
 *
 * Admin controller for sending broadcast notifications to users.
 */
class BroadcastNotificationController extends Controller
{
    /**
     * Show the broadcast notification form.
     */
    public function create(): View
    {
        $roles = Role::query()->orderBy('name')->get()->map(fn (Role $role): array => [
            'value' => $role->name,
            'label' => ucfirst($role->name),
        ])->all();

        $priorityOptions = collect(NotificationPriority::cases())->map(fn (NotificationPriority $p): array => [
            'value' => $p->value,
            'label' => $p->label(),
        ])->all();

        // Count users per role
        $userCounts = DB::table('model_has_roles')
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->select('roles.name', DB::raw('count(*) as count'))
            ->groupBy('roles.name')
            ->pluck('count', 'name')
            ->toArray();

        $totalUsers = User::query()->count();

        return view('admin.notifications.broadcast', ['roles' => $roles, 'priorityOptions' => $priorityOptions, 'userCounts' => $userCounts, 'totalUsers' => $totalUsers]);
    }

    /**
     * Send broadcast notification.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:65535'],
            'priority' => ['required', Rule::enum(NotificationPriority::class)],
            'target' => ['required', 'in:all,roles,users'],
            'roles' => ['required_if:target,roles', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
            'user_ids' => ['required_if:target,users', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'icon' => ['nullable', 'string', 'max:50'],
        ]);

        // Get target users
        $query = User::query();

        if ($validated['target'] === 'roles' && ! empty($validated['roles'])) {
            $query->whereHas('roles', function ($q) use ($validated): void {
                $q->whereIn('name', $validated['roles']);
            });
        } elseif ($validated['target'] === 'users' && ! empty($validated['user_ids'])) {
            $query->whereIn('id', $validated['user_ids']);
        }

        if (! $query->exists()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => __('notifications.broadcast_no_users'),
                ], 400);
            }

            return back()->with('error', __('notifications.broadcast_no_users'));
        }

        $payload = [
            'title' => $validated['title'],
            'text' => $validated['message'],
            'priority' => $validated['priority'],
            'icon' => $validated['icon'] ?? 'ri-megaphone-line',
            'url_backend' => route('app.notifications.index'),
        ];

        $count = 0;
        $query->orderBy('id')->chunkById(200, function ($users) use ($payload, &$count): void {
            Notification::send($users, new BroadcastNotification($payload));
            $count += $users->count();
        });

        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('notifications.broadcast_sent', ['count' => $count]),
            ]);
        }

        return to_route('app.notifications.broadcast.create')
            ->with('success', __('notifications.broadcast_sent', ['count' => $count]));
    }

    /**
     * Show broadcast history.
     */
    public function index(): View
    {
        // Get recent broadcast notifications
        $broadcasts = DB::table('notifications')
            ->select(
                DB::raw("CAST(data AS jsonb)->>'title' as title"),
                DB::raw("CAST(data AS jsonb)->>'text' as message"),
                DB::raw('MIN(created_at) as created_at'),
                DB::raw('COUNT(*) as recipient_count')
            )
            ->where('type', BroadcastNotification::class)
            ->groupBy(DB::raw("CAST(data AS jsonb)->>'title'"), DB::raw("CAST(data AS jsonb)->>'text'"))->latest()
            ->paginate(15);

        return view('admin.notifications.broadcast-history', ['broadcasts' => $broadcasts]);
    }
}
