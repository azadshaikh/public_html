<?php

namespace App\Console\Commands;

use App\Scaffold\ScaffoldController;
use App\Scaffold\ScaffoldIntrospector;
use Illuminate\Console\Command;
use Throwable;

class ScaffoldDoctorCommand extends Command
{
    protected $signature = 'scaffold:doctor
        {--fail-fast : Stop after the first detected issue}
        {--strict-legacy-registrations : Fail when non-generated registration files do not reference the expected scaffold hooks}';

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

        if (($inspection['expects_generated_registration_merges'] ?? false) === true) {
            $issues = [...$issues, ...$this->inspectGeneratedRegistrationMerges($controllerClass, $inspection, $controllerRoutes)];
        } elseif ($this->option('strict-legacy-registrations')) {
            $issues = [...$issues, ...$this->inspectLegacyRegistrationReferences($controllerClass, $inspection)];
        }

        if (is_string($inspection['module']) && $inspection['module'] !== '') {
            $issues = [...$issues, ...$this->inspectModuleAbilityMap($controllerClass, $inspection['module'], $inspection['ability_map'], $controllerRoutes)];
        }

        return $issues;
    }

    /**
     * @param  array<string, mixed>  $inspection
     * @return array<int, array{controller: string, message: string}>
     */
    private function inspectLegacyRegistrationReferences(string $controllerClass, array $inspection): array
    {
        $audit = $inspection['registration_audit'] ?? $this->introspector->auditRegistration($inspection);
        $issues = [];

        $routeAudit = is_array($audit['routes'] ?? null) ? $audit['routes'] : [];

        if (($routeAudit['exists'] ?? false) === false) {
            $issues[] = [
                'controller' => $controllerClass,
                'message' => sprintf('Missing legacy routes registration target [%s].', (string) (($routeAudit['path'] ?? null) ?: ($inspection['registration_paths']['routes'] ?? 'unknown'))),
            ];
        } elseif (($routeAudit['contains_controller_reference'] ?? false) !== true && ($routeAudit['contains_expected_index_route'] ?? false) !== true) {
            $issues[] = [
                'controller' => $controllerClass,
                'message' => sprintf('Legacy routes registration [%s] does not reference the scaffold controller or expected index route.', (string) $routeAudit['path']),
            ];
        }

        $navigationAudit = is_array($audit['navigation'] ?? null) ? $audit['navigation'] : [];

        if (($navigationAudit['exists'] ?? false) === false) {
            $issues[] = [
                'controller' => $controllerClass,
                'message' => sprintf('Missing legacy navigation registration target [%s].', (string) (($navigationAudit['path'] ?? null) ?: ($inspection['registration_paths']['navigation'] ?? 'unknown'))),
            ];
        } elseif (($navigationAudit['contains_index_route'] ?? false) !== true || ($navigationAudit['contains_view_permission'] ?? false) !== true || ($navigationAudit['contains_active_pattern'] ?? false) !== true) {
            $issues[] = [
                'controller' => $controllerClass,
                'message' => sprintf('Legacy navigation registration [%s] does not reference the expected route, view permission, and active pattern trio.', (string) $navigationAudit['path']),
            ];
        }

        return $issues;
    }

    /**
     * @param  array<string, mixed>  $inspection
     * @param  array<string, array<int, string>>  $controllerRoutes
     * @return array<int, array{controller: string, message: string}>
     */
    private function inspectGeneratedRegistrationMerges(string $controllerClass, array $inspection, array $controllerRoutes): array
    {
        $registrationPaths = $inspection['registration_paths'] ?? null;
        $registrationMarkers = $inspection['registration_markers'] ?? null;

        if (! is_array($registrationPaths) || ! is_array($registrationMarkers)) {
            return [[
                'controller' => $controllerClass,
                'message' => 'Generated registration metadata is missing from scaffold inspection.',
            ]];
        }

        $issues = [];

        foreach ($registrationPaths as $type => $path) {
            $marker = $registrationMarkers[$type] ?? null;

            if (! is_string($path) || $path === '' || ! is_string($marker) || $marker === '') {
                continue;
            }

            if (! is_file($path)) {
                $issues[] = [
                    'controller' => $controllerClass,
                    'message' => sprintf('Missing registration target for %s at [%s].', $type, $path),
                ];

                continue;
            }

            $contents = file_get_contents($path);

            if (! is_string($contents)) {
                $issues[] = [
                    'controller' => $controllerClass,
                    'message' => sprintf('Could not read registration target for %s at [%s].', $type, $path),
                ];

                continue;
            }

            $block = $this->extractMarkedBlock($contents, $marker);

            if ($block === null) {
                $issues[] = [
                    'controller' => $controllerClass,
                    'message' => sprintf("Missing generated %s registration block '%s' in [%s].", $type, $marker, $path),
                ];

                continue;
            }

            if ($this->countMarkedBlocks($contents, $marker) > 1) {
                $issues[] = [
                    'controller' => $controllerClass,
                    'message' => sprintf("Duplicate generated %s registration block '%s' found in [%s].", $type, $marker, $path),
                ];

                continue;
            }

            $issues = [...$issues, ...$this->inspectRegistrationBlockContents($controllerClass, $type, $path, $block, $inspection, $controllerRoutes)];
        }

        return $issues;
    }

    /**
     * @param  array<string, mixed>  $inspection
     * @param  array<string, array<int, string>>  $controllerRoutes
     * @return array<int, array{controller: string, message: string}>
     */
    private function inspectRegistrationBlockContents(string $controllerClass, string $type, string $path, string $block, array $inspection, array $controllerRoutes): array
    {
        return match ($type) {
            'routes' => $this->inspectRouteRegistrationBlock($controllerClass, $path, $block, $inspection, $controllerRoutes),
            'navigation' => $this->inspectNavigationRegistrationBlock($controllerClass, $path, $block, $inspection),
            'abilities' => $this->inspectAbilityRegistrationBlock($controllerClass, $path, $block, $inspection, $controllerRoutes),
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $inspection
     * @param  array<string, array<int, string>>  $controllerRoutes
     * @return array<int, array{controller: string, message: string}>
     */
    private function inspectRouteRegistrationBlock(string $controllerClass, string $path, string $block, array $inspection, array $controllerRoutes): array
    {
        $issues = [];

        if (! str_contains($block, (string) $inspection['controller']) && ! str_contains($block, class_basename((string) $inspection['controller']))) {
            $issues[] = [
                'controller' => $controllerClass,
                'message' => sprintf('Generated routes block in [%s] does not reference controller [%s].', $path, $inspection['controller']),
            ];
        }

        if (! str_contains($block, 'crud.exceptions')) {
            $issues[] = [
                'controller' => $controllerClass,
                'message' => sprintf('Generated routes block in [%s] does not include the expected crud.exceptions middleware.', $path),
            ];
        }

        if (! str_contains($block, '/bulk-action')) {
            $issues[] = [
                'controller' => $controllerClass,
                'message' => sprintf('Generated routes block in [%s] does not include the expected bulk-action route.', $path),
            ];
        }

        if (! str_contains($block, "where('status'") && ! str_contains($block, 'where("status"')) {
            $issues[] = [
                'controller' => $controllerClass,
                'message' => sprintf('Generated routes block in [%s] does not include the expected status filter route constraint.', $path),
            ];
        }

        foreach ($this->expectedRoutesForRegisteredActions($inspection['route_names'], $controllerRoutes) as $routeName) {
            if (! str_contains($block, sprintf("'%s'", $routeName))) {
                $issues[] = [
                    'controller' => $controllerClass,
                    'message' => sprintf('Generated routes block in [%s] does not reference expected route [%s].', $path, $routeName),
                ];
            }
        }

        return $issues;
    }

    /**
     * @param  array<string, mixed>  $inspection
     * @return array<int, array{controller: string, message: string}>
     */
    private function inspectNavigationRegistrationBlock(string $controllerClass, string $path, string $block, array $inspection): array
    {
        $issues = [];
        $indexRoute = $inspection['route_names']['index'] ?? null;
        $viewPermission = $inspection['permission_names']['view'] ?? null;
        $activePattern = ($inspection['route_prefix'] ?? '').'.*';

        if (is_string($indexRoute) && ! str_contains($block, sprintf("'%s'", $indexRoute))) {
            $issues[] = [
                'controller' => $controllerClass,
                'message' => sprintf('Generated navigation block in [%s] does not reference index route [%s].', $path, $indexRoute),
            ];
        }

        if (is_string($viewPermission) && ! str_contains($block, sprintf("'%s'", $viewPermission))) {
            $issues[] = [
                'controller' => $controllerClass,
                'message' => sprintf('Generated navigation block in [%s] does not reference view permission [%s].', $path, $viewPermission),
            ];
        }

        if (is_string($inspection['route_prefix'] ?? null) && ! str_contains($block, sprintf("'%s'", $activePattern))) {
            $issues[] = [
                'controller' => $controllerClass,
                'message' => sprintf('Generated navigation block in [%s] does not reference active pattern [%s].', $path, $activePattern),
            ];
        }

        if (! str_contains($block, "'status' => 'all'") && ! str_contains($block, '"status" => "all"')) {
            $issues[] = [
                'controller' => $controllerClass,
                'message' => sprintf('Generated navigation block in [%s] does not include the expected status=all route params.', $path),
            ];
        }

        return $issues;
    }

    /**
     * @param  array<string, mixed>  $inspection
     * @param  array<string, array<int, string>>  $controllerRoutes
     * @return array<int, array{controller: string, message: string}>
     */
    private function inspectAbilityRegistrationBlock(string $controllerClass, string $path, string $block, array $inspection, array $controllerRoutes): array
    {
        $issues = [];

        foreach ($this->expectedAbilitiesForRegisteredActions($inspection['ability_map'], $controllerRoutes) as $abilityKey => $permission) {
            $expectedEntry = sprintf("'%s' => '%s'", $abilityKey, $permission);

            if (! str_contains($block, $expectedEntry)) {
                $issues[] = [
                    'controller' => $controllerClass,
                    'message' => sprintf('Generated abilities block in [%s] does not contain expected mapping [%s].', $path, $expectedEntry),
                ];
            }
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

    private function extractMarkedBlock(string $contents, string $marker): ?string
    {
        $pattern = $this->markedBlockPattern($marker);

        if (preg_match($pattern, $contents, $matches) !== 1) {
            return null;
        }

        return $matches[1] ?? null;
    }

    private function countMarkedBlocks(string $contents, string $marker): int
    {
        preg_match_all($this->markedBlockPattern($marker), $contents, $matches);

        return count($matches[0] ?? []);
    }

    private function markedBlockPattern(string $marker): string
    {
        $start = preg_quote(sprintf('// %s:start', $marker), '/');
        $end = preg_quote(sprintf('// %s:end', $marker), '/');

        return "/{$start}\n(.*?){$end}/s";
    }
}
