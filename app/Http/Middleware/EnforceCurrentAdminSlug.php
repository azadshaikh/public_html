<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Env;
use Symfony\Component\HttpFoundation\Response;

class EnforceCurrentAdminSlug
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request):Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $path = trim($request->path(), '/');
        if ($path === '') {
            return $next($request);
        }

        $firstSegment = explode('/', $path)[0];
        $configuredSlug = trim((string) config('app.admin_slug'), '/');

        $requiresValidation = $firstSegment === 'admin'
            || ($configuredSlug !== '' && $firstSegment === $configuredSlug);

        if (! $requiresValidation) {
            return $next($request);
        }

        $runtimeSlug = trim($this->resolveRuntimeAdminSlug($configuredSlug), '/');

        if ($runtimeSlug !== '' && $firstSegment === $runtimeSlug) {
            return $next($request);
        }

        $isLegacyAdminPath = $firstSegment === 'admin' && $runtimeSlug !== 'admin';
        $isStaleConfiguredPath = $configuredSlug !== '' && $configuredSlug !== $runtimeSlug && $firstSegment === $configuredSlug;

        abort_if($isLegacyAdminPath || $isStaleConfiguredPath, 404);

        return $next($request);
    }

    private function resolveRuntimeAdminSlug(string $fallback): string
    {
        $dotEnvValue = get_env_file_value('ADMIN_SLUG');
        if (is_string($dotEnvValue) && $dotEnvValue !== '') {
            return trim($dotEnvValue, '/');
        }

        $runtime = Env::get('ADMIN_SLUG', \Illuminate\Support\Facades\Request::server('ADMIN_SLUG') ?? null);
        if (is_string($runtime) && $runtime !== '') {
            return $runtime;
        }

        return $fallback;
    }
}
