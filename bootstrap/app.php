<?php

use App\Http\Middleware\CdnCacheHeadersMiddleware;
use App\Http\Middleware\CheckRegistrationEnabled;
use App\Http\Middleware\CheckUserStatusMiddleware;
use App\Http\Middleware\EnsureEmailVerificationIsSatisfied;
use App\Http\Middleware\EnsureModuleIsEnabled;
use App\Http\Middleware\EnsureProfileCompletionIsSatisfied;
use App\Http\Middleware\EnsureReleaseApiKey;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleCrudExceptions;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\JsonRedirectMiddleware;
use App\Http\Middleware\LanguageMiddleware;
use App\Http\Middleware\ModuleAccessMiddleware;
use App\Http\Middleware\SiteAccessProtectionMiddleware;
use App\Http\Middleware\SuperUserPermissionMiddleware;
use App\Http\Middleware\UrlExtension;
use App\Services\NotFoundLogger;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->alias([
            'cdnCacheHeaders' => CdnCacheHeadersMiddleware::class,
            'check.registration.enabled' => CheckRegistrationEnabled::class,
            'crud.exceptions' => HandleCrudExceptions::class,
            'module.enabled' => EnsureModuleIsEnabled::class,
            'module_access' => ModuleAccessMiddleware::class,
            'permission' => SuperUserPermissionMiddleware::class,
            'profile.completed' => EnsureProfileCompletionIsSatisfied::class,
            'release.api.key' => EnsureReleaseApiKey::class,
            'role' => RoleMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'site.access.protection' => SiteAccessProtectionMiddleware::class,
            'url.extension' => UrlExtension::class,
            'user.status' => CheckUserStatusMiddleware::class,
            'verified' => EnsureEmailVerificationIsSatisfied::class,
        ]);

        $middleware->web(append: [
            HandleAppearance::class,
            LanguageMiddleware::class,
            JsonRedirectMiddleware::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (NotFoundHttpException $exception, Request $request) {
            if (! $request->expectsJson()) {
                rescue(
                    callback: function () use ($request): void {
                        resolve(NotFoundLogger::class)->log($request);
                    },
                    report: true,
                );
            }

            return null;
        });

        $exceptions->respond(function (Response $response, Throwable $exception, Request $request): Response {
            if (app()->hasDebugModeEnabled() || $request->expectsJson()) {
                return $response;
            }

            $status = $response->getStatusCode();
            $supportedStatuses = [401, 402, 403, 404, 419, 429, 500, 503];

            if (! in_array($status, $supportedStatuses, true)) {
                return $response;
            }

            $message = trim($exception->getMessage());
            $defaultMessage = Response::$statusTexts[$status] ?? '';

            if ($message === '' || $message === $defaultMessage) {
                $message = null;
            }

            return Inertia::render("errors/{$status}", [
                'status' => $status,
                'message' => $message,
            ])->toResponse($request)->setStatusCode($status);
        });
    })->create();
