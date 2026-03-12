<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SuperUserPermissionMiddleware
{
    /**
     * Handle an incoming request.
     * This middleware extends Spatie Permission middleware with super user bypass.
     *
     * @param  Closure(Request):Response  $next
     */
    public function handle(Request $request, Closure $next, ...$permissions): Response
    {
        $user = Auth::user();

        // Super user bypass - if user has super_user role, allow access to everything
        if ($user && $user->hasRole(User::superUserRoleId())) {
            return $next($request);
        }

        // For non-super users, check permissions normally
        abort_unless($user !== null, 401, 'Unauthenticated');

        // Check if user has any of the required permissions
        $hasPermission = false;
        foreach ($permissions as $permission) {
            if ($user->can($permission)) {
                $hasPermission = true;
                break;
            }
        }

        abort_unless($hasPermission, 403, 'User does not have the right permissions.');

        return $next($request);
    }
}
