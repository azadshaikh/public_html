<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Auth\SuperUserAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperUserAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request):Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(SuperUserAccess::allowsRequest($request), 403);

        return $next($request);
    }
}
