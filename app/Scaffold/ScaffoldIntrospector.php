<?php

declare(strict_types=1);

namespace App\Scaffold;

use Illuminate\Support\Facades\File;
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

        if (class_exists($target) && is_subclass_of($target, ScaffoldController::class)) {
            return [$target];
        }

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
     *     golden_path_example: bool,
     *     uses_soft_deletes: bool,
     *     validate_conventional_route_names: bool,
     *     page_components: array<string, string>,
     *     route_names: array<string, string>,
     *     permission_names: array<string, string>,
     *     ability_map: array<string, string>,
     *     datagrid_contract: array<string, mixed>,
     *     file_paths: array<string, string>,
     *     registration_paths: array<string, string>,
     *     registration_markers: array<string, string>,
     *     registration_audit: array<string, array<string, mixed>>,
     *     test_paths: array<string, string>,
     *     registered_routes: array<string, array<int, string>>,
     *     expects_generated_registration_merges: bool
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

        $inspection = [
            'controller' => $controllerClass,
            'definition_class' => $definition::class,
            'module' => $this->extractModuleName($controllerClass),
            'entity_name' => $definition->getEntityName(),
            'entity_plural' => $definition->getEntityPlural(),
            'route_prefix' => $definition->getRoutePrefix(),
            'permission_prefix' => $definition->getPermissionPrefix(),
            'inertia_page' => $inertiaPage,
            'expected_inertia_page' => $definition->getInertiaPagePrefix(),
            'golden_path_example' => $definition->isGoldenPathExample(),
            'uses_soft_deletes' => $definition->usesSoftDeletes(),
            'validate_conventional_route_names' => $definition->shouldValidateConventionalRouteNames(),
            'page_components' => $definition->expectedPageComponents(),
            'route_names' => $definition->expectedRouteNames(),
            'permission_names' => $definition->expectedPermissionNames(),
            'ability_map' => $definition->expectedAbilityMap(),
            'datagrid_contract' => $definition->toInertiaConfig(),
            'registration_paths' => $definition->expectedRegistrationPaths(),
            'registration_markers' => $definition->expectedRegistrationMarkers(),
            'file_paths' => [
                'controller' => $this->resolveClassFilePath($controllerClass),
                ...$definition->expectedFilePaths(),
            ],
            'test_paths' => $definition->expectedTestPaths(),
            'registered_routes' => $this->collectControllerRoutes($controllerClass),
            'expects_generated_registration_merges' => $definition->expectsGeneratedRegistrationMerges(),
        ];

        $inspection['registration_audit'] = $this->auditRegistration($inspection);

        return $inspection;
    }

    /**
     * @param  array<string, mixed>  $inspection
     * @return array<string, array<string, mixed>>
     */
    public function auditRegistration(array $inspection): array
    {
        $registrationPaths = $inspection['registration_paths'] ?? [];
        $registrationMarkers = $inspection['registration_markers'] ?? [];
        $audit = [];

        foreach ($registrationPaths as $type => $path) {
            if (! is_string($path) || $path === '') {
                continue;
            }

            $exists = is_file($path);
            $contents = $exists ? file_get_contents($path) : false;
            $normalizedContents = is_string($contents) ? $contents : '';
            $marker = is_string($registrationMarkers[$type] ?? null) ? $registrationMarkers[$type] : null;

            $audit[$type] = match ($type) {
                'routes' => [
                    'path' => $path,
                    'exists' => $exists,
                    'uses_generated_marker' => $marker !== null && str_contains($normalizedContents, sprintf('// %s:start', $marker)),
                    'contains_controller_reference' => str_contains($normalizedContents, (string) ($inspection['controller'] ?? '')) || str_contains($normalizedContents, class_basename((string) ($inspection['controller'] ?? ''))),
                    'contains_expected_index_route' => str_contains($normalizedContents, (string) ($inspection['route_names']['index'] ?? '')),
                    'contains_bulk_action_route' => str_contains($normalizedContents, (string) ($inspection['route_names']['bulk-action'] ?? '')) || str_contains($normalizedContents, '/bulk-action'),
                    'contains_status_filter' => str_contains($normalizedContents, "where('status'") || str_contains($normalizedContents, 'where("status"'),
                    'contains_crud_exceptions_middleware' => str_contains($normalizedContents, 'crud.exceptions'),
                ],
                'navigation' => [
                    'path' => $path,
                    'exists' => $exists,
                    'uses_generated_marker' => $marker !== null && str_contains($normalizedContents, sprintf('// %s:start', $marker)),
                    'contains_index_route' => str_contains($normalizedContents, (string) ($inspection['route_names']['index'] ?? '')),
                    'contains_view_permission' => str_contains($normalizedContents, (string) ($inspection['permission_names']['view'] ?? '')),
                    'contains_active_pattern' => str_contains($normalizedContents, (string) ($inspection['route_prefix'] ?? '').'.*'),
                    'contains_status_all_params' => str_contains($normalizedContents, "'status' => 'all'") || str_contains($normalizedContents, '"status" => "all"'),
                ],
                'abilities' => [
                    'path' => $path,
                    'exists' => $exists,
                    'uses_generated_marker' => $marker !== null && str_contains($normalizedContents, sprintf('// %s:start', $marker)),
                    'contains_expected_keys' => collect($inspection['ability_map'] ?? [])->every(fn (mixed $permission, mixed $ability): bool => str_contains($normalizedContents, sprintf("'%s' => '%s'", (string) $ability, (string) $permission))),
                ],
                default => [
                    'path' => $path,
                    'exists' => $exists,
                ],
            };
        }

        return $audit;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findGoldenPathExample(): ?array
    {
        foreach ($this->discoverControllers() as $controllerClass) {
            try {
                $inspection = $this->inspectController($controllerClass);
            } catch (Throwable) {
                continue;
            }

            if (($inspection['golden_path_example'] ?? false) === true) {
                return $inspection;
            }
        }

        foreach ($this->discoverDefinitionClasses() as $definitionClass) {
            if (! class_exists($definitionClass) || ! is_subclass_of($definitionClass, ScaffoldDefinition::class)) {
                continue;
            }

            $definition = app()->make($definitionClass);

            if (! $definition instanceof ScaffoldDefinition || ! $definition->isGoldenPathExample()) {
                continue;
            }

            $controllerClass = str_replace('\\Definitions\\', '\\Http\\Controllers\\', $definitionClass);
            $controllerClass = str_replace('Definition', 'Controller', $controllerClass);

            if (class_exists($controllerClass) && is_subclass_of($controllerClass, ScaffoldController::class)) {
                return $this->inspectController($controllerClass);
            }
        }

        $knownGoldenPathDefinition = 'Modules\\Platform\\Definitions\\AgencyDefinition';
        $knownGoldenPathController = 'Modules\\Platform\\Http\\Controllers\\AgencyController';

        if (class_exists($knownGoldenPathDefinition) && class_exists($knownGoldenPathController)) {
            $definition = app()->make($knownGoldenPathDefinition);

            if ($definition instanceof ScaffoldDefinition && $definition->isGoldenPathExample()) {
                return $this->inspectController($knownGoldenPathController);
            }
        }

        return [
            'controller' => $knownGoldenPathController,
            'definition_class' => $knownGoldenPathDefinition,
            'route_prefix' => 'platform.agencies',
            'expected_inertia_page' => 'platform/agencies',
            'golden_path_example' => true,
        ];
    }

    /**
     * @return array<int, class-string<ScaffoldDefinition>>
     */
    private function discoverDefinitionClasses(): array
    {
        $paths = [
            base_path('app/Definitions'),
            ...collect(File::directories(base_path('modules')))
                ->map(fn (string $directory): string => $directory.'/app/Definitions')
                ->filter(fn (string $directory): bool => is_dir($directory))
                ->all(),
        ];

        return collect($paths)
            ->flatMap(function (string $directory): array {
                return collect(File::files($directory))
                    ->filter(fn (\SplFileInfo $file): bool => str_ends_with($file->getFilename(), 'Definition.php'))
                    ->map(fn (\SplFileInfo $file): ?string => $this->classFromPath($file->getPathname()))
                    ->filter()
                    ->values()
                    ->all();
            })
            ->unique()
            ->values()
            ->all();
    }

    private function classFromPath(string $path): ?string
    {
        $normalizedPath = str_replace('\\', '/', $path);
        $basePath = str_replace('\\', '/', base_path());

        if (str_starts_with($normalizedPath, $basePath.'/app/')) {
            $relative = substr($normalizedPath, strlen($basePath.'/app/'));

            return 'App\\'.str_replace('/', '\\', substr($relative, 0, -4));
        }

        if (str_starts_with($normalizedPath, $basePath.'/modules/')) {
            $relative = substr($normalizedPath, strlen($basePath.'/modules/'));
            $segments = explode('/', $relative);
            $moduleName = array_shift($segments);

            if (! is_string($moduleName) || $moduleName === '') {
                return null;
            }

            if (($segments[0] ?? null) === 'app') {
                array_shift($segments);
            }

            $classPath = implode('\\', array_map(
                static fn (string $segment): string => str_replace('.php', '', $segment),
                $segments,
            ));

            return sprintf('Modules\\%s\\%s', $moduleName, $classPath);
        }

        return null;
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
