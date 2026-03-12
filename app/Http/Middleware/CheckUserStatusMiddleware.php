<?php

namespace App\Http\Middleware;

use App\Enums\Status;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckUserStatusMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request):Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            /** @var User $user */
            $user = Auth::user();

            if ($request->session()->has('impersonator_id')) {
                return $next($request);
            }

            $status = $this->resolveStatus($user);

            if ($status !== Status::ACTIVE) {
                Auth::logout();

                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return to_route('login')->withErrors([
                    'email' => sprintf("Your account status is '%s'. Please contact support for assistance.", $status?->label() ?? 'Unknown'),
                ]);
            }
        }

        return $next($request);
    }

    private function resolveStatus(User $user): ?Status
    {
        $status = $user->getAttribute('status');

        if ($status instanceof Status) {
            return $status;
        }

        if (is_string($status)) {
            return Status::tryFrom($status);
        }

        return null;
    }
}
