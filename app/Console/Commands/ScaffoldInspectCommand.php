<?php

namespace App\Console\Commands;

use App\Scaffold\ScaffoldIntrospector;
use Illuminate\Console\Command;
use Throwable;

class ScaffoldInspectCommand extends Command
{
    protected $signature = 'scaffold:inspect
        {target? : Optional scaffold controller, definition, entity, or route prefix to inspect}
        {--json : Output inspection data as JSON}';

    protected $description = 'Inspect resolved scaffold metadata for scaffold controllers';

    public function __construct(private readonly ScaffoldIntrospector $introspector)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $target = $this->argument('target');

        if (is_string($target) && trim($target) !== '') {
            return $this->inspectSingleTarget($target);
        }

        return $this->inspectAllControllers();
    }

    private function inspectSingleTarget(string $target): int
    {
        $matches = $this->introspector->findControllers($target);

        if ($matches === []) {
            $this->error(sprintf('No scaffold controller matched [%s].', $target));

            return self::FAILURE;
        }

        if (count($matches) > 1) {
            $this->error(sprintf('Scaffold target [%s] is ambiguous.', $target));

            foreach ($matches as $match) {
                $this->line(' - '.$match);
            }

            return self::FAILURE;
        }

        try {
            $inspection = $this->introspector->inspectController($matches[0]);
        } catch (Throwable $throwable) {
            $this->error($throwable->getMessage());

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode($inspection, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        $this->renderDetailedInspection($inspection);

        return self::SUCCESS;
    }

    private function inspectAllControllers(): int
    {
        $controllers = $this->introspector->discoverControllers();

        if ($controllers === []) {
            $this->warn('No scaffold controllers were discovered.');

            return self::SUCCESS;
        }

        $inspections = collect($controllers)
            ->map(fn (string $controllerClass): array => $this->introspector->inspectController($controllerClass))
            ->all();

        if ($this->option('json')) {
            $this->line(json_encode($inspections, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        $this->table(
            ['Controller', 'Entity', 'Route Prefix', 'Inertia Page', 'Module', 'Golden Path'],
            collect($inspections)
                ->map(fn (array $inspection): array => [
                    $inspection['controller'],
                    $inspection['entity_name'],
                    $inspection['route_prefix'],
                    $inspection['expected_inertia_page'],
                    $inspection['module'] ?? 'App',
                    $inspection['golden_path_example'] ? 'yes' : 'no',
                ])
                ->all(),
        );

        $this->newLine();
        $this->info(sprintf('Inspected %d scaffold controller(s).', count($inspections)));

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $inspection
     */
    private function renderDetailedInspection(array $inspection): void
    {
        $this->table(['Key', 'Value'], [
            ['controller', $inspection['controller']],
            ['definition', $inspection['definition_class']],
            ['module', $inspection['module'] ?? 'App'],
            ['entity', $inspection['entity_name']],
            ['route_prefix', $inspection['route_prefix']],
            ['permission_prefix', $inspection['permission_prefix']],
            ['inertia_page', $inspection['inertia_page']],
            ['expected_inertia_page', $inspection['expected_inertia_page']],
            ['golden_path_example', $inspection['golden_path_example'] ? 'yes' : 'no'],
            ['uses_soft_deletes', $inspection['uses_soft_deletes'] ? 'yes' : 'no'],
        ]);

        $this->newLine();
        $this->info('Registered routes');
        $this->table(
            ['Action', 'Route Names'],
            collect($inspection['registered_routes'])
                ->map(fn (array $routeNames, string $action): array => [$action, implode(', ', $routeNames)])
                ->values()
                ->all(),
        );

        $this->newLine();
        $this->info('Expected file paths');
        $this->table(
            ['Type', 'Path'],
            collect($inspection['file_paths'])
                ->map(fn (string $path, string $type): array => [$type, $path])
                ->values()
                ->all(),
        );

        $this->newLine();
        $this->info('Suggested test paths');
        $this->table(
            ['Type', 'Path'],
            collect($inspection['test_paths'])
                ->map(fn (string $path, string $type): array => [$type, $path])
                ->values()
                ->all(),
        );
    }
}
