<?php

namespace App\Console\Commands;

use App\Scaffold\ScaffoldController;
use App\Scaffold\ScaffoldIntrospector;
use Illuminate\Console\Command;
use Throwable;

class ScaffoldDoctorCommand extends Command
{
    protected $signature = 'scaffold:doctor {--fail-fast : Stop after the first detected issue}';

    protected $description = 'Validate scaffold controllers, Inertia pages, routes, and module ability contracts';

    public function __construct(private readonly ScaffoldIntrospector $introspector)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $issues = [];
        $controllers = $this->introspector->discoverControllers();

        if ($controllers === []) {
            $this->warn('No scaffold controllers were discovered.');

            return self::SUCCESS;
        }

        foreach ($controllers as $controllerClass) {
            $controllerIssues = $this->inspectController($controllerClass);

            if ($controllerIssues === []) {
                $this->line(sprintf('<info>PASS</info> %s', $controllerClass));

                continue;
            }

            $issues = [...$issues, ...$controllerIssues];

            foreach ($controllerIssues as $issue) {
                $this->line(sprintf('<error>FAIL</error> %s — %s', $issue['controller'], $issue['message']));
            }

            if ($this->option('fail-fast')) {
                break;
            }
        }

        if ($issues === []) {
            $this->newLine();
            $this->info(sprintf('Scaffold doctor passed for %d scaffold controller(s).', count($controllers)));

            return self::SUCCESS;
        }

        $this->newLine();
        $this->error(sprintf('Scaffold doctor found %d issue(s).', count($issues)));

        return self::FAILURE;
    }

    /**
     * @param  class-string<ScaffoldController>  $controllerClass
     * @return array<int, array{controller: string, message: string}>
     */
    private function inspectController(string $controllerClass): array
    {
        try {
            $inspection = $this->introspector->inspectController($controllerClass);
        } catch (Throwable $throwable) {
            return [[
                'controller' => $controllerClass,
                'message' => 'Could not be resolved from the container: '.$throwable->getMessage(),
            ]];
        }

        $issues = [];
        $controllerRoutes = $inspection['registered_routes'];

        if ($inspection['inertia_page'] !== $inspection['expected_inertia_page']) {
            $issues[] = [
                'controller' => $controllerClass,
                'message' => sprintf("inertiaPage() returns '%s' but the scaffold definition derives '%s'.", $inspection['inertia_page'], $inspection['expected_inertia_page']),
            ];
        }

        foreach ($inspection['page_components'] as $pageName => $component) {
            if (! array_key_exists($pageName, $controllerRoutes)) {
                continue;
            }

            $expectedPath = $inspection['file_paths']['page:'.$pageName] ?? null;

            if (! is_string($expectedPath) || ! is_file($expectedPath)) {
                $issues[] = [
                    'controller' => $controllerClass,
                    'message' => sprintf('Missing %s page at [%s].', $pageName, (string) $expectedPath),
                ];
            }
        }

        foreach ($inspection['file_paths'] as $fileKey => $path) {
            if (str_starts_with($fileKey, 'page:') || $fileKey === 'abilities') {
                continue;
            }

            if (! is_file($path)) {
                $issues[] = [
                    'controller' => $controllerClass,
                    'message' => sprintf('Missing expected %s file at [%s].', $fileKey, $path),
                ];
            }
        }

        if ($inspection['validate_conventional_route_names']) {
            foreach ($this->expectedRoutesForRegisteredActions($inspection['route_names'], $controllerRoutes) as $routePurpose => $routeName) {
                $actualRouteNames = $controllerRoutes[$routePurpose] ?? [];

                if ($actualRouteNames === []) {
                    $issues[] = [
                        'controller' => $controllerClass,
                        'message' => sprintf('Missing expected %s route [%s].', $routePurpose, $routeName),
                    ];

                    continue;
                }

                if (! in_array($routeName, $actualRouteNames, true)) {
                    $issues[] = [
                        'controller' => $controllerClass,
                        'message' => sprintf("Routes for %s are [%s] but '%s' was expected.", $routePurpose, implode(', ', $actualRouteNames), $routeName),
                    ];
                }
            }
        }

        if (is_string($inspection['module']) && $inspection['module'] !== '') {
            $issues = [...$issues, ...$this->inspectModuleAbilityMap($controllerClass, $inspection['module'], $inspection['ability_map'], $controllerRoutes)];
        }

        return $issues;
    }

    /**
     * @param  array<string, string>  $expectedAbilityMap
     * @param  array<string, array<int, string>>  $controllerRoutes
     * @return array<int, array{controller: string, message: string}>
     */
    private function inspectModuleAbilityMap(string $controllerClass, string $moduleName, array $expectedAbilityMap, array $controllerRoutes): array
    {
        $configPath = base_path(sprintf('modules/%s/config/abilities.php', $moduleName));

        if (! is_file($configPath)) {
            return [[
                'controller' => $controllerClass,
                'message' => sprintf('Missing module ability config [%s].', $configPath),
            ]];
        }

        $definitions = include $configPath;

        if (! is_array($definitions)) {
            return [[
                'controller' => $controllerClass,
                'message' => sprintf('Module ability config [%s] must return an array.', $configPath),
            ]];
        }

        $issues = [];

        foreach ($this->expectedAbilitiesForRegisteredActions($expectedAbilityMap, $controllerRoutes) as $abilityKey => $permission) {
            if (! array_key_exists($abilityKey, $definitions)) {
                $issues[] = [
                    'controller' => $controllerClass,
                    'message' => sprintf("Missing expected ability key '%s' in [%s].", $abilityKey, $configPath),
                ];

                continue;
            }

            if ($definitions[$abilityKey] !== $permission) {
                $issues[] = [
                    'controller' => $controllerClass,
                    'message' => sprintf("Ability key '%s' should map to '%s' in [%s].", $abilityKey, $permission, $configPath),
                ];
            }
        }

        return $issues;
    }

    /**
     * @param  array<string, string>  $expectedRouteNames
     * @param  array<string, array<int, string>>  $controllerRoutes
     * @return array<string, string>
     */
    private function expectedRoutesForRegisteredActions(array $expectedRouteNames, array $controllerRoutes): array
    {
        return collect($expectedRouteNames)
            ->filter(fn (string $routeName, string $action): bool => array_key_exists($action, $controllerRoutes))
            ->all();
    }

    /**
     * @param  array<string, string>  $expectedAbilityMap
     * @param  array<string, array<int, string>>  $controllerRoutes
     * @return array<string, string>
     */
    private function expectedAbilitiesForRegisteredActions(array $expectedAbilityMap, array $controllerRoutes): array
    {
        $abilityPrefixes = [];

        if (array_key_exists('index', $controllerRoutes) || array_key_exists('show', $controllerRoutes)) {
            $abilityPrefixes[] = 'view';
        }

        if (array_key_exists('create', $controllerRoutes) || array_key_exists('store', $controllerRoutes)) {
            $abilityPrefixes[] = 'add';
        }

        if (array_key_exists('edit', $controllerRoutes) || array_key_exists('update', $controllerRoutes)) {
            $abilityPrefixes[] = 'edit';
        }

        if (array_key_exists('destroy', $controllerRoutes) || array_key_exists('bulk-action', $controllerRoutes) || array_key_exists('force-delete', $controllerRoutes)) {
            $abilityPrefixes[] = 'delete';
        }

        if (array_key_exists('restore', $controllerRoutes)) {
            $abilityPrefixes[] = 'restore';
        }

        return collect($expectedAbilityMap)
            ->filter(function (string $permission) use ($abilityPrefixes): bool {
                $prefix = strtolower((string) str($permission)->before('_')->toString());

                return in_array($prefix, $abilityPrefixes, true);
            })
            ->all();
    }
}
