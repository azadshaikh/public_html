<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ModuleAccessMiddleware
{
    public function handle(Request $request, Closure $next, $module)
    {
        $user = Auth::user();

        // Super users can access any module (check by role ID for reliability)
        if ($user && $user->hasRole(User::superUserRoleId())) {
            return $next($request);
        }

        // Check if module is active for non-super users
        $moduleSlug = strtolower(trim((string) $module));
        if (function_exists('module_enabled')) {
            abort_unless(module_enabled($moduleSlug), 403, 'Unauthorized');
        } elseif (function_exists('active_modules')) {
            abort_unless(active_modules($moduleSlug), 403, 'Unauthorized');
        } else {
            abort(403, 'Unauthorized');
        }

        return $next($request);
    }
}
