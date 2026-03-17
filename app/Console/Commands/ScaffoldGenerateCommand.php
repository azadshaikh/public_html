<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Scaffold\ScaffoldGeneratorBlueprintFactory;
use App\Scaffold\ScaffoldGeneratorFileFactory;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use JsonException;
use Throwable;

class ScaffoldGenerateCommand extends Command
{
    protected $signature = 'scaffold:generate
        {name : Singular entity name, for example Widget or BlogPost}
        {--module= : Optional owning module name. Omit for App scaffolds}
        {--plural= : Optional plural display label override}
        {--route-prefix= : Optional route prefix override}
        {--permission-prefix= : Optional permission prefix override}
        {--controller= : Optional controller class basename override}
        {--definition= : Optional definition class basename override}
        {--request= : Optional request class basename override}
        {--service= : Optional service class basename override}
        {--write : Write generated files to disk}
        {--force : Overwrite existing generated files when possible}
        {--base-path= : Optional base path override for file output, intended for testing or dry-run sandboxes}
        {--json : Output the generated scaffold blueprint as JSON}';

    protected $description = 'Generate the canonical scaffold blueprint and expected file graph for a CRUD resource';

    public function __construct(
        private readonly ScaffoldGeneratorBlueprintFactory $blueprints,
        private readonly ScaffoldGeneratorFileFactory $files,
        private readonly Filesystem $filesystem,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $blueprint = $this->blueprints->make(
            name: (string) $this->argument('name'),
            module: $this->option('module'),
            overrides: [
                'plural' => $this->option('plural'),
                'route-prefix' => $this->option('route-prefix'),
                'permission-prefix' => $this->option('permission-prefix'),
                'controller' => $this->option('controller'),
                'definition' => $this->option('definition'),
                'request' => $this->option('request'),
                'service' => $this->option('service'),
            ],
        );

        $writeResult = null;

        if ($this->option('write')) {
            $writeResult = $this->writeFiles($blueprint);
        }

        if ($this->option('json')) {
            try {
                $payload = $writeResult === null
                    ? $blueprint
                    : [...$blueprint, 'write_result' => $writeResult];

                $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            } catch (JsonException $exception) {
                $this->error($exception->getMessage());

                return self::FAILURE;
            }

            return self::SUCCESS;
        }

        $this->table(['Key', 'Value'], [
            ['entity_name', $blueprint['entity_name']],
            ['entity_plural', $blueprint['entity_plural']],
            ['module', $blueprint['generator']['module'] ?? 'App'],
            ['route_prefix', $blueprint['route_prefix']],
            ['permission_prefix', $blueprint['permission_prefix']],
            ['inertia_page_prefix', $blueprint['inertia_page_prefix']],
        ]);

        $this->newLine();
        $this->info('Generated classes');
        $this->table(
            ['Type', 'Class'],
            collect($blueprint['classes'])
                ->map(fn (string $class, string $type): array => [$type, $class])
                ->values()
                ->all(),
        );

        $this->newLine();
        $this->info('Expected file graph');
        $this->table(
            ['Type', 'Path'],
            collect($blueprint['file_paths'])
                ->map(fn (string $path, string $type): array => [$type, $path])
                ->values()
                ->all(),
        );

        if (is_array($blueprint['registration_paths'] ?? null) && $blueprint['registration_paths'] !== []) {
            $this->newLine();
            $this->info('Registration merge targets');
            $this->table(
                ['Type', 'Path'],
                collect($blueprint['registration_paths'])
                    ->map(fn (string $path, string $type): array => [$type, $path])
                    ->values()
                    ->all(),
            );
        }

        $this->newLine();
        $this->info('Suggested tests');
        $this->table(
            ['Type', 'Path'],
            collect($blueprint['test_paths'])
                ->map(fn (string $path, string $type): array => [$type, $path])
                ->values()
                ->all(),
        );

        if (is_array($writeResult)) {
            $this->newLine();
            $this->info('Write summary');
            $this->table(['Outcome', 'Count'], [
                ['created', count($writeResult['created'])],
                ['merged', count($writeResult['merged'])],
                ['overwritten', count($writeResult['overwritten'])],
                ['skipped', count($writeResult['skipped'])],
            ]);
        } else {
            $this->newLine();
            $this->comment('Run with --write to materialize the generated scaffold files.');
        }

        if (is_array($blueprint['golden_path_example'])) {
            $example = $blueprint['golden_path_example'];

            $this->newLine();
            $this->info('Golden-path example');
            $this->table(['Key', 'Value'], [
                ['controller', $example['controller']],
                ['definition', $example['definition_class']],
                ['route_prefix', $example['route_prefix']],
                ['inertia_page', $example['expected_inertia_page']],
            ]);
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $blueprint
     * @return array{created: array<int, string>, merged: array<int, string>, overwritten: array<int, string>, skipped: array<int, string>}
     */
    private function writeFiles(array $blueprint): array
    {
        $targetBasePath = $this->normalizeBasePath($this->option('base-path'));
        $force = (bool) $this->option('force');
        $renderedFiles = $this->files->make($blueprint);
        $results = [
            'created' => [],
            'merged' => [],
            'overwritten' => [],
            'skipped' => [],
        ];

        foreach ($renderedFiles as $type => $file) {
            $targetPath = $this->resolveTargetPath($file['path'], $targetBasePath, $type);

            if ($this->filesystem->exists($targetPath)) {
                if (! $force) {
                    $results['skipped'][] = $targetPath;

                    continue;
                }

                $results['overwritten'][] = $targetPath;
            } else {
                $results['created'][] = $targetPath;
            }

            $this->filesystem->ensureDirectoryExists(dirname($targetPath));
            $this->filesystem->put($targetPath, $file['contents']);
        }

        $this->mergeRegistrationFiles($blueprint, $targetBasePath, $results);

        return $results;
    }

    /**
     * @param  array<string, mixed>  $blueprint
     * @param  array{created: array<int, string>, merged: array<int, string>, overwritten: array<int, string>, skipped: array<int, string>}  $results
     */
    private function mergeRegistrationFiles(array $blueprint, ?string $targetBasePath, array &$results): void
    {
        $registrationPaths = $blueprint['registration_paths'] ?? null;

        if (! is_array($registrationPaths)) {
            return;
        }

        foreach (['routes', 'navigation', 'abilities'] as $type) {
            $sourcePath = $registrationPaths[$type] ?? null;

            if (! is_string($sourcePath) || $sourcePath === '') {
                continue;
            }

            $targetPath = $this->resolveTargetPath($sourcePath, $targetBasePath, 'registration:'.$type);
            $outcome = match ($type) {
                'routes' => $this->mergeRouteRegistration($blueprint, $targetPath),
                'navigation' => $this->mergeNavigationRegistration($blueprint, $targetPath),
                'abilities' => $this->mergeAbilitiesRegistration($blueprint, $targetPath),
                default => 'skipped',
            };

            $this->recordWriteResult($results, $outcome, $targetPath);
        }
    }

    /**
     * @param  array<string, mixed>  $blueprint
     */
    private function mergeRouteRegistration(array $blueprint, string $targetPath): string
    {
        $existingContents = $this->filesystem->exists($targetPath)
            ? $this->filesystem->get($targetPath)
            : null;

        $mergedContents = $this->mergeMarkedPhpBlock(
            existingContents: $existingContents,
            marker: $this->registrationMarker($blueprint, 'routes'),
            block: $this->renderRouteRegistration($blueprint),
            createFallback: "<?php\n\n",
            insertCallback: static fn (string $contents, string $wrapped): string => rtrim($contents)."\n\n{$wrapped}\n",
        );

        return $this->persistMergedFile($targetPath, $existingContents, $mergedContents);
    }

    /**
     * @param  array<string, mixed>  $blueprint
     */
    private function mergeAbilitiesRegistration(array $blueprint, string $targetPath): string
    {
        $abilityMap = $blueprint['ability_map'] ?? null;

        if (! is_array($abilityMap) || $abilityMap === []) {
            return 'skipped';
        }

        $existingContents = $this->filesystem->exists($targetPath)
            ? $this->filesystem->get($targetPath)
            : null;

        $lines = collect($abilityMap)
            ->map(fn (mixed $permission, mixed $ability): string => sprintf("    '%s' => '%s',", (string) $ability, (string) $permission))
            ->implode("\n");

        $mergedContents = $this->mergeMarkedPhpBlock(
            existingContents: $existingContents,
            marker: $this->registrationMarker($blueprint, 'abilities'),
            block: $lines,
            createFallback: "<?php\n\nreturn [\n",
            insertCallback: function (string $contents, string $wrapped): string {
                if (preg_match('/\n\];\s*$/', $contents) === 1) {
                    return (string) preg_replace('/\n\];\s*$/', "\n\n{$wrapped}\n];\n", $contents, 1);
                }

                return rtrim($contents)."\n\n{$wrapped}\n";
            },
            createSuffix: "];\n",
        );

        return $this->persistMergedFile($targetPath, $existingContents, $mergedContents);
    }

    /**
     * @param  array<string, mixed>  $blueprint
     */
    private function mergeNavigationRegistration(array $blueprint, string $targetPath): string
    {
        $existingContents = $this->filesystem->exists($targetPath)
            ? $this->filesystem->get($targetPath)
            : null;

        $mergedContents = $this->mergeMarkedPhpBlock(
            existingContents: $existingContents,
            marker: $this->registrationMarker($blueprint, 'navigation'),
            block: $this->renderNavigationRegistration($blueprint, $targetPath),
            createFallback: "<?php\n\nreturn [\n    'sections' => [\n",
            insertCallback: function (string $contents, string $wrapped): string {
                if (preg_match("/\n(\s*)'badge_functions'\s*=>/", $contents, $matches) === 1) {
                    $indent = $matches[1] ?? '    ';

                    return (string) preg_replace(
                        "/\n(\s*)'badge_functions'\s*=>/",
                        "\n\n{$wrapped}\n\n{$indent}'badge_functions' =>",
                        $contents,
                        1,
                    );
                }

                if (preg_match('/\n\];\s*$/', $contents) === 1) {
                    return (string) preg_replace('/\n\];\s*$/', "\n\n{$wrapped}\n];\n", $contents, 1);
                }

                return rtrim($contents)."\n\n{$wrapped}\n";
            },
            createSuffix: "    ],\n    'badge_functions' => [],\n    'ui' => [\n        'show_section_headers' => true,\n        'show_badges' => true,\n    ],\n];\n",
        );

        return $this->persistMergedFile($targetPath, $existingContents, $mergedContents);
    }

    private function persistMergedFile(string $targetPath, ?string $existingContents, string $mergedContents): string
    {
        if ($existingContents !== null && $existingContents === $mergedContents) {
            return 'skipped';
        }

        $this->filesystem->ensureDirectoryExists(dirname($targetPath));
        $this->filesystem->put($targetPath, $mergedContents);

        return $existingContents === null ? 'created' : 'merged';
    }

    private function mergeMarkedPhpBlock(
        ?string $existingContents,
        string $marker,
        string $block,
        string $createFallback,
        callable $insertCallback,
        string $createSuffix = '',
    ): string {
        $wrapped = $this->wrapMarkedBlock($marker, $block);

        if ($existingContents === null) {
            return $createFallback.$wrapped.($createSuffix !== '' ? "\n{$createSuffix}" : "\n");
        }

        $pattern = $this->markedBlockPattern($marker);

        if (preg_match($pattern, $existingContents) === 1) {
            $updatedContents = preg_replace($pattern, $wrapped, $existingContents, 1);

            return is_string($updatedContents) ? $updatedContents : $existingContents;
        }

        return $insertCallback($existingContents, $wrapped);
    }

    /**
     * @param  array<string, mixed>  $blueprint
     */
    private function renderRouteRegistration(array $blueprint): string
    {
        $routePrefix = (string) ($blueprint['route_prefix'] ?? 'app.resources');
        $segments = array_values(array_filter(explode('.', $routePrefix)));
        $rootSegment = array_shift($segments) ?? 'app';
        $innerSegments = $segments !== [] ? $segments : [(string) ($blueprint['generator']['resource_slug'] ?? 'resources')];
        $innerPrefix = implode('/', $innerSegments);
        $innerName = implode('.', $innerSegments);
        $controllerClass = '\\'.ltrim((string) ($blueprint['classes']['controller'] ?? ''), '\\');
        $parameterName = (string) Str::snake((string) ($blueprint['generator']['entity'] ?? 'resource'));
        $adminPrefixExpression = $rootSegment === 'app'
            ? "trim((string) config('app.admin_slug'), '/')"
            : "trim((string) config('app.admin_slug'), '/').'/{$rootSegment}'";
        $outerName = $rootSegment === 'app' ? 'app.' : $rootSegment.'.';
        $showRouteParameter = '{'.$parameterName.'}';
        $restoreRoute = isset($blueprint['route_names']['restore'])
            ? "                \\Illuminate\\Support\\Facades\\Route::patch('/{$showRouteParameter}/restore', [{$controllerClass}::class, 'restore'])->whereNumber('{$parameterName}')->name('restore');\n"
            : '';
        $forceDeleteRoute = isset($blueprint['route_names']['force-delete'])
            ? "                \\Illuminate\\Support\\Facades\\Route::delete('/{$showRouteParameter}/force-delete', [{$controllerClass}::class, 'forceDelete'])->whereNumber('{$parameterName}')->name('force-delete');\n"
            : '';

        return <<<PHP
\\Illuminate\\Support\\Facades\\Route::middleware(['auth', 'user.status', 'verified', 'profile.completed'])
    ->prefix({$adminPrefixExpression})
    ->group(function (): void {
        \\Illuminate\\Support\\Facades\\Route::group(['as' => '{$outerName}'], function (): void {
            \\Illuminate\\Support\\Facades\\Route::prefix('{$innerPrefix}')->name('{$innerName}.')->middleware(['crud.exceptions'])->group(function (): void {
                \\Illuminate\\Support\\Facades\\Route::post('/bulk-action', [{$controllerClass}::class, 'bulkAction'])->name('bulk-action');
                \\Illuminate\\Support\\Facades\\Route::get('/create', [{$controllerClass}::class, 'create'])->name('create');
                \\Illuminate\\Support\\Facades\\Route::post('/', [{$controllerClass}::class, 'store'])->name('store');
                \\Illuminate\\Support\\Facades\\Route::get('/{$showRouteParameter}', [{$controllerClass}::class, 'show'])->whereNumber('{$parameterName}')->name('show');
                \\Illuminate\\Support\\Facades\\Route::get('/{$showRouteParameter}/edit', [{$controllerClass}::class, 'edit'])->whereNumber('{$parameterName}')->name('edit');
                \\Illuminate\\Support\\Facades\\Route::put('/{$showRouteParameter}', [{$controllerClass}::class, 'update'])->whereNumber('{$parameterName}')->name('update');
                \\Illuminate\\Support\\Facades\\Route::delete('/{$showRouteParameter}', [{$controllerClass}::class, 'destroy'])->whereNumber('{$parameterName}')->name('destroy');
{$forceDeleteRoute}{$restoreRoute}                \\Illuminate\\Support\\Facades\\Route::get('/{status?}', [{$controllerClass}::class, 'index'])
                    ->where('status', '^(all|active|inactive|trash)$')
                    ->name('index');
            });
        });
    });
PHP;
    }

    /**
     * @param  array<string, mixed>  $blueprint
     */
    private function renderNavigationRegistration(array $blueprint, string $targetPath): string
    {
        $module = $blueprint['generator']['module'] ?? null;
        $defaults = $this->resolveNavigationDefaults($targetPath, is_string($module) ? $module : null);
        $resourceSlug = str_replace('-', '_', (string) ($blueprint['generator']['resource_slug'] ?? 'resources'));
        $sectionKey = 'scaffold_'.$resourceSlug;
        $itemKey = 'scaffold_'.$resourceSlug;
        $entityPlural = (string) ($blueprint['entity_plural'] ?? 'Resources');
        $indexRoute = (string) ($blueprint['route_names']['index'] ?? '');
        $viewPermission = (string) (($blueprint['permission_names']['view'] ?? '') ?: ($blueprint['ability_map']['view'.Str::studly((string) ($blueprint['generator']['resource_slug'] ?? 'Resources'))] ?? ''));
        $activePattern = (string) ($blueprint['route_prefix'] ?? '').'.*';
        $icon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4" width="16" height="16" rx="2"></rect><path d="M8 9h8"></path><path d="M8 13h8"></path><path d="M8 17h5"></path></svg>';

        return <<<PHP
    '{$sectionKey}' => [
        'label' => '{$entityPlural}',
        'weight' => {$defaults['weight']},
        'area' => '{$defaults['area']}',
        'show_label' => false,
        'items' => [
            '{$itemKey}' => [
                'label' => '{$entityPlural}',
                'route' => ['name' => '{$indexRoute}', 'params' => ['status' => 'all']],
                'icon' => '{$icon}',
                'permission' => '{$viewPermission}',
                'active_patterns' => ['{$activePattern}'],
            ],
        ],
    ],
PHP;
    }

    /**
     * @return array{area: string, weight: int}
     */
    private function resolveNavigationDefaults(string $targetPath, ?string $module): array
    {
        $defaults = [
            'area' => $module === null ? 'cms' : 'modules',
            'weight' => $module === null ? 140 : 1000,
        ];

        if ($module === null || ! $this->filesystem->exists($targetPath)) {
            return $defaults;
        }

        try {
            $config = include $targetPath;

            if (! is_array($config)) {
                return $defaults;
            }

            $sections = $config['sections'] ?? null;

            if (! is_array($sections) || $sections === []) {
                return $defaults;
            }

            $firstSection = collect($sections)->first(fn (mixed $section): bool => is_array($section));

            if (! is_array($firstSection)) {
                return $defaults;
            }

            return [
                'area' => is_string($firstSection['area'] ?? null) && $firstSection['area'] !== ''
                    ? $firstSection['area']
                    : $defaults['area'],
                'weight' => is_numeric($firstSection['weight'] ?? null)
                    ? ((int) $firstSection['weight']) + 5
                    : $defaults['weight'],
            ];
        } catch (Throwable) {
            return $defaults;
        }
    }

    /**
     * @param  array<string, mixed>  $blueprint
     */
    private function registrationMarker(array $blueprint, string $type): string
    {
        $resourceSlug = str_replace('-', '_', (string) ($blueprint['generator']['resource_slug'] ?? 'resources'));

        return sprintf('scaffold-generated:%s:%s', $resourceSlug, $type);
    }

    private function wrapMarkedBlock(string $marker, string $block): string
    {
        return sprintf("// %s:start\n%s\n// %s:end", $marker, rtrim($block), $marker);
    }

    private function markedBlockPattern(string $marker): string
    {
        $start = preg_quote(sprintf('// %s:start', $marker), '/');
        $end = preg_quote(sprintf('// %s:end', $marker), '/');

        return "/{$start}.*?{$end}/s";
    }

    /**
     * @param  array{created: array<int, string>, merged: array<int, string>, overwritten: array<int, string>, skipped: array<int, string>}  $results
     */
    private function recordWriteResult(array &$results, string $outcome, string $targetPath): void
    {
        if (! array_key_exists($outcome, $results)) {
            return;
        }

        $results[$outcome][] = $targetPath;
    }

    private function normalizeBasePath(mixed $basePath): ?string
    {
        if (! is_string($basePath) || trim($basePath) === '') {
            return null;
        }

        return rtrim(trim($basePath), '/');
    }

    private function resolveTargetPath(string $path, ?string $targetBasePath, string $type): string
    {
        $resolvedPath = str_contains($path, '<timestamp>')
            ? str_replace('<timestamp>', now()->format('Y_m_d_His'), $path)
            : $path;

        if ($targetBasePath === null) {
            return $resolvedPath;
        }

        $applicationBasePath = rtrim(base_path(), '/');

        if (str_starts_with($resolvedPath, $applicationBasePath.'/')) {
            return $targetBasePath.substr($resolvedPath, strlen($applicationBasePath));
        }

        return $targetBasePath.'/generated/'.$type.'/'.basename($resolvedPath);
    }
}
