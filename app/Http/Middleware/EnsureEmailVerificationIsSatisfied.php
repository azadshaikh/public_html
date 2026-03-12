<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified as BaseEnsureEmailIsVerified;
use Illuminate\Http\Request;

class EnsureEmailVerificationIsSatisfied extends BaseEnsureEmailIsVerified
{
    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     */
    public function handle($request, Closure $next, ...$guards)
    {
        if (! setting('registration_require_email_verification', true)) {
            return $next($request);
        }

        return parent::handle($request, $next, ...$guards);
    }
}
