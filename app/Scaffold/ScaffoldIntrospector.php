<?php

declare(strict_types=1);

namespace App\Scaffold;

use Illuminate\Support\Facades\Route;
use RuntimeException;
use Throwable;

class ScaffoldIntrospector
{
    /**
     * @return array<int, class-string<ScaffoldController>>
     */
    public function discoverControllers(): array
    {
        return collect(Route::getRoutes()->getRoutes())
            ->map(function ($route): ?string {
                $action = $route->getActionName();

                if (! is_string($action) || ! str_contains($action, '@')) {
                    return null;
                }

                $controllerClass = explode('@', $action)[0];

                if (! is_string($controllerClass) || ! class_exists($controllerClass)) {
                    return null;
                }

                if (! is_subclass_of($controllerClass, ScaffoldController::class)) {
                    return null;
                }

                return $controllerClass;
            })
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @return array<int, class-string<ScaffoldController>>
     */
    public function findControllers(string $target): array
    {
        $needle = strtolower(trim($target));

        return collect($this->discoverControllers())
            ->filter(function (string $controllerClass) use ($needle): bool {
                if (in_array($needle, [
                    strtolower($controllerClass),
                    strtolower(class_basename($controllerClass)),
                ], true)) {
                    return true;
                }

                try {
                    $inspection = $this->inspectController($controllerClass);
                } catch (Throwable) {
                    return false;
                }

                return in_array($needle, [
                    strtolower($inspection['definition_class']),
                    strtolower(class_basename($inspection['definition_class'])),
                    strtolower($inspection['entity_name']),
                    strtolower($inspection['entity_plural']),
                    strtolower($inspection['route_prefix']),
                ], true);
            })
            ->values()
            ->all();
    }

    /**
     * @param  class-string<ScaffoldController>  $controllerClass
     * @return array{
     *     controller: string,
     *     definition_class: string,
     *     module: ?string,
     *     entity_name: string,
     *     entity_plural: string,
     *     route_prefix: string,
     *     permission_prefix: string,
     *     inertia_page: string,
     *     expected_inertia_page: string,
     *     uses_soft_deletes: bool,
     *     validate_conventional_route_names: bool,
     *     page_components: array<string, string>,
     *     route_names: array<string, string>,
     *     permission_names: array<string, string>,
     *     ability_map: array<string, string>,
     *     file_paths: array<string, string>,
     *     test_paths: array<string, string>,
     *     registered_routes: array<string, array<int, string>>
     * }
     */
    public function inspectController(string $controllerClass): array
    {
        $controller = app()->make($controllerClass);

        if (! $controller instanceof ScaffoldController) {
            throw new RuntimeException(sprintf('Resolved [%s] is not a scaffold controller.', $controllerClass));
        }

        /** @var ScaffoldDefinition $definition */
        $definition = $this->callProtectedMethod($controller, 'scaffold');
        /** @var string $inertiaPage */
        $inertiaPage = $this->callProtectedMethod($controller, 'inertiaPage');

        return [
            'controller' => $controllerClass,
            'definition_class' => $definition::class,
            'module' => $this->extractModuleName($controllerClass),
            'entity_name' => $definition->getEntityName(),
            'entity_plural' => $definition->getEntityPlural(),
            'route_prefix' => $definition->getRoutePrefix(),
            'permission_prefix' => $definition->getPermissionPrefix(),
            'inertia_page' => $inertiaPage,
            'expected_inertia_page' => $definition->getInertiaPagePrefix(),
            'uses_soft_deletes' => $definition->usesSoftDeletes(),
            'validate_conventional_route_names' => $definition->shouldValidateConventionalRouteNames(),
            'page_components' => $definition->expectedPageComponents(),
            'route_names' => $definition->expectedRouteNames(),
            'permission_names' => $definition->expectedPermissionNames(),
            'ability_map' => $definition->expectedAbilityMap(),
            'file_paths' => [
                'controller' => $this->resolveClassFilePath($controllerClass),
                ...$definition->expectedFilePaths(),
            ],
            'test_paths' => $definition->expectedTestPaths(),
            'registered_routes' => $this->collectControllerRoutes($controllerClass),
        ];
    }

    /**
     * @param  class-string<ScaffoldController>  $controllerClass
     * @return array<string, array<int, string>>
     */
    public function collectControllerRoutes(string $controllerClass): array
    {
        return collect(Route::getRoutes()->getRoutes())
            ->map(function ($route) use ($controllerClass): ?array {
                $action = $route->getActionName();

                if (! is_string($action) || ! str_starts_with($action, $controllerClass.'@')) {
                    return null;
                }

                $method = explode('@', $action)[1] ?? null;
                $routeName = $route->getName();

                if (! is_string($method) || ! is_string($routeName) || $routeName === '') {
                    return null;
                }

                $normalizedMethod = match ($method) {
                    'bulkAction' => 'bulk-action',
                    'forceDelete' => 'force-delete',
                    default => $method,
                };

                return [$normalizedMethod => $routeName];
            })
            ->filter()
            ->reduce(function (array $carry, array $item): array {
                foreach ($item as $action => $routeName) {
                    if (! array_key_exists($action, $carry)) {
                        $carry[$action] = [];
                    }

                    if (! in_array($routeName, $carry[$action], true)) {
                        $carry[$action][] = $routeName;
                    }
                }

                return $carry;
            }, []);
    }

    private function extractModuleName(string $controllerClass): ?string
    {
        if (! str_starts_with($controllerClass, 'Modules\\')) {
            return null;
        }

        $segments = explode('\\', $controllerClass);

        return $segments[1] ?? null;
    }

    private function resolveClassFilePath(string $class): ?string
    {
        if (str_starts_with($class, 'App\\')) {
            return base_path('app/'.str_replace('\\', '/', substr($class, strlen('App\\'))).'.php');
        }

        if (str_starts_with($class, 'Modules\\')) {
            $segments = explode('\\', $class);
            $moduleName = $segments[1] ?? null;
            $relativePath = implode('/', array_slice($segments, 2));

            if (is_string($moduleName) && $moduleName !== '' && $relativePath !== '') {
                return base_path(sprintf('modules/%s/app/%s.php', $moduleName, $relativePath));
            }
        }

        return null;
    }

    private function callProtectedMethod(object $target, string $method): mixed
    {
        return \Closure::bind(fn () => $this->{$method}(), $target, $target)();
    }
}
