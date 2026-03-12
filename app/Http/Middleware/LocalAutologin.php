<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LocalAutologin
{
    public function handle(Request $request, Closure $next)
    {
        if (! app()->environment('local')) {
            return $next($request);
        }

        if (Auth::check()) {
            return $next($request);
        }

        $autologinUserId = config('astero.autologin_user_id');

        if (! is_numeric($autologinUserId)) {
            return $next($request);
        }

        $autologinUserId = (int) $autologinUserId;

        if ($autologinUserId <= 0) {
            return $next($request);
        }

        // Only log in if the user exists.
        $userModel = config('auth.providers.users.model', User::class);
        if (! class_exists($userModel)) {
            return $next($request);
        }

        $user = $userModel::query()->find($autologinUserId);
        if (! $user) {
            return $next($request);
        }

        Auth::login($user, remember: true);

        return $next($request);
    }
}
