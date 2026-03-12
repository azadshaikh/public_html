<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\CustomMedia;
use App\Models\User;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Modules\CMS\Models\CmsPost;
use Modules\Todos\Services\TodoService;

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

    public function index(): View|RedirectResponse
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

        $view_data = [];
        $view_data['page_title'] = __('dashboard');
        $view_data['page_description'] = __('dashboard description');

        // CMS Module Statistics (failsafe - only loads if module exists)
        if (active_modules('cms')) {
            $cmsPostModel = CmsPost::class;

            // Page Statistics
            $view_data['page_statistics'] = $cmsPostModel::getStatistics('page');

            // Post Statistics
            $view_data['post_statistics'] = $cmsPostModel::getStatistics('post');

            // Category Statistics
            if (auth()->user()->can('view_categories')) {
                $view_data['category_statistics'] = $cmsPostModel::getStatistics('category');
            }

            // Recent Posts
            $view_data['recent_posts'] = $cmsPostModel::with(['author', 'featuredImage'])
                ->where('type', 'post')
                ->where('status', 'published')
                ->latest('published_at')
                ->limit(5)
                ->get();
        }

        // Media Statistics
        if (auth()->user()->can('view_media')) {
            $view_data['total_media'] = CustomMedia::withoutTrashed()->count();
            $view_data['total_media_images'] = CustomMedia::withoutTrashed()
                ->where('mime_type', 'like', 'image/%')
                ->count();
        }

        // Todo Module Statistics (failsafe - only loads if module exists)
        if (active_modules('todos') && auth()->user()->can('view_todos')) {
            $todoService = resolve(TodoService::class);
            $todoStatistics = $todoService->getStatistics();

            $view_data['todo_statistics'] = $todoStatistics;
            $view_data['pending_todos'] = $todoStatistics['pending'] ?? 0;
            $view_data['in_progress_todos'] = $todoStatistics['in_progress'] ?? 0;
            $view_data['completed_todos'] = $todoStatistics['completed'] ?? 0;
            $view_data['overdue_todos'] = $todoStatistics['overdue'] ?? 0;
            $view_data['total_todos'] = $todoStatistics['total'] ?? 0;

            $todoModel = $todoService->getModelClass();
            $view_data['pending_todos_list'] = $todoModel::query()
                ->whereIn('status', ['pending', 'in_progress'])
                ->orderByRaw("CASE WHEN status = 'in_progress' THEN 0 WHEN status = 'pending' THEN 1 ELSE 2 END")
                ->orderBy('due_date')
                ->limit(5)
                ->get();
        }

        // Recent Activities (super_user only)
        if (auth()->user()->hasRole('super_user')) {
            $recentActivitiesQuery = ActivityLog::query();
            $currentUser = Auth::user();

            if ($currentUser && ! $currentUser->isSuperUser()) {
                $superUserIds = DB::table('model_has_roles')
                    ->where('role_id', User::superUserRoleId())
                    ->where('model_type', User::class)
                    ->pluck('model_id');

                $recentActivitiesQuery
                    ->whereNotNull('causer_id')
                    ->whereNotIn('causer_id', $superUserIds);
            }

            $view_data['recent_activities'] = $recentActivitiesQuery
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();
        }

        return view('app.dashboard', $view_data);
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
