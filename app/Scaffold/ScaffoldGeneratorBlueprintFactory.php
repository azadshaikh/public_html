<?php

declare(strict_types=1);

namespace App\Scaffold;

use Illuminate\Support\Str;

class ScaffoldGeneratorBlueprintFactory
{
    public function __construct(private readonly ScaffoldIntrospector $introspector) {}

    /**
     * @param  array<string, string|null>  $overrides
     * @return array<string, mixed>
     */
    public function make(string $name, ?string $module = null, array $overrides = []): array
    {
        $studlySingular = Str::singular(Str::studly(trim($name)));
        $studlyPlural = Str::pluralStudly($studlySingular);
        $entityName = Str::headline($studlySingular);
        $entityPlural = $this->normalizeDisplayLabel($overrides['plural'] ?? null)
            ?? Str::headline($studlyPlural);

        $normalizedModule = $this->normalizeModuleName($module);
        $resourceSlug = Str::of($studlyPlural)->snake()->replace('_', '-')->toString();
        $permissionPrefix = $overrides['permission-prefix']
            ?? Str::of($studlyPlural)->snake()->toString();
        $routePrefix = $overrides['route-prefix']
            ?? ($normalizedModule === null
                ? 'app.'.$resourceSlug
                : Str::lower($normalizedModule).'.'.$resourceSlug);

        $classBasenames = [
            'model' => $studlySingular,
            'request' => $overrides['request'] ?? $studlySingular.'Request',
            'definition' => $overrides['definition'] ?? $studlySingular.'Definition',
            'controller' => $overrides['controller'] ?? $studlySingular.'Controller',
            'service' => $overrides['service'] ?? $studlySingular.'Service',
        ];

        $classMap = $this->buildClassMap($normalizedModule, $classBasenames);
        $definition = $this->makeGeneratedDefinition(
            entityName: $entityName,
            entityPlural: $entityPlural,
            routePrefix: $routePrefix,
            permissionPrefix: $permissionPrefix,
            modelClass: $classMap['model'],
            requestClass: $classMap['request'],
            definitionClass: $classMap['definition'],
            moduleName: $normalizedModule,
        );

        $filePaths = [
            'controller' => $this->resolveClassFilePath($classMap['controller']),
            'service' => $this->resolveClassFilePath($classMap['service']),
            ...$definition->expectedFilePaths(),
            'form-component' => $this->resolveFormComponentPath($normalizedModule, $resourceSlug, $studlySingular),
            'factory' => $this->resolveFactoryPath($normalizedModule, $studlySingular),
            'seeder' => $this->resolveSeederPath($normalizedModule, $studlySingular),
            'migration' => $this->resolveMigrationPattern($normalizedModule, $resourceSlug),
        ];

        return [
            'generator' => [
                'entity' => $studlySingular,
                'module' => $normalizedModule,
                'resource_slug' => $resourceSlug,
            ],
            'entity_name' => $entityName,
            'entity_plural' => $entityPlural,
            'route_prefix' => $routePrefix,
            'permission_prefix' => $permissionPrefix,
            'inertia_page_prefix' => $definition->getInertiaPagePrefix(),
            'classes' => $classMap,
            'file_paths' => $filePaths,
            'page_components' => $definition->expectedPageComponents(),
            'route_names' => $definition->expectedRouteNames(),
            'permission_names' => $definition->expectedPermissionNames(),
            'ability_map' => $definition->expectedAbilityMap(),
            'datagrid_contract' => $definition->toInertiaConfig(),
            'form_wiring' => $this->buildFormWiring($definition, $classMap, $filePaths, $studlySingular),
            'service_wiring' => $this->buildServiceWiring($classMap, $studlySingular),
            'test_paths' => $definition->expectedTestPaths(),
            'registration_paths' => $definition->expectedRegistrationPaths(),
            'registration_markers' => $definition->expectedRegistrationMarkers(),
            'golden_path_example' => $this->introspector->findGoldenPathExample(),
        ];
    }

    private function normalizeModuleName(?string $module): ?string
    {
        if (! is_string($module) || trim($module) === '') {
            return null;
        }

        return Str::studly(trim($module));
    }

    private function normalizeDisplayLabel(?string $label): ?string
    {
        if (! is_string($label) || trim($label) === '') {
            return null;
        }

        return trim($label);
    }

    /**
     * @param  array<string, string>  $classBasenames
     * @return array<string, string>
     */
    private function buildClassMap(?string $module, array $classBasenames): array
    {
        if ($module === null) {
            return [
                'model' => 'App\\Models\\'.$classBasenames['model'],
                'request' => 'App\\Http\\Requests\\'.$classBasenames['request'],
                'definition' => 'App\\Definitions\\'.$classBasenames['definition'],
                'controller' => 'App\\Http\\Controllers\\'.$classBasenames['controller'],
                'service' => 'App\\Services\\'.$classBasenames['service'],
            ];
        }

        return [
            'model' => sprintf('Modules\\%s\\Models\\%s', $module, $classBasenames['model']),
            'request' => sprintf('Modules\\%s\\Http\\Requests\\%s', $module, $classBasenames['request']),
            'definition' => sprintf('Modules\\%s\\Definitions\\%s', $module, $classBasenames['definition']),
            'controller' => sprintf('Modules\\%s\\Http\\Controllers\\%s', $module, $classBasenames['controller']),
            'service' => sprintf('Modules\\%s\\Services\\%s', $module, $classBasenames['service']),
        ];
    }

    private function makeGeneratedDefinition(
        string $entityName,
        string $entityPlural,
        string $routePrefix,
        string $permissionPrefix,
        string $modelClass,
        string $requestClass,
        string $definitionClass,
        ?string $moduleName,
    ): ScaffoldDefinition {
        return new class($entityName, $entityPlural, $routePrefix, $permissionPrefix, $modelClass, $requestClass, $definitionClass, $moduleName) extends ScaffoldDefinition
        {
            public function __construct(
                string $entityName,
                string $entityPlural,
                string $routePrefix,
                string $permissionPrefix,
                private readonly string $modelClass,
                private readonly string $requestClass,
                private readonly string $definitionClass,
                private readonly ?string $moduleName,
            ) {
                $this->entityName = $entityName;
                $this->entityPlural = $entityPlural;
                $this->routePrefix = $routePrefix;
                $this->permissionPrefix = $permissionPrefix;
                $this->expectsGeneratedRegistrationMerges = true;
                $this->statusField = 'status';
            }

            public function columns(): array
            {
                return [
                    Column::make('name')->label('Name')->sortable()->searchable(),
                    Column::make('slug')->label('Slug')->sortable()->searchable(),
                    Column::make('status')->label('Status')->badge()->sortable(),
                    Column::make('created_at')->label('Created')->sortable(),
                    Column::make('_actions')->label('Actions')->template('actions')->excludeFromExport()->width('90px'),
                ];
            }

            public function filters(): array
            {
                return [
                    Filter::select('status')
                        ->label('Status')
                        ->placeholder('All statuses')
                        ->options([
                            'active' => 'Active',
                            'inactive' => 'Inactive',
                        ]),
                ];
            }

            public function statusTabs(): array
            {
                return [
                    StatusTab::make('all')->label('All')->icon('ri-list-check')->color('primary')->default(),
                    StatusTab::make('active')->label('Active')->icon('ri-checkbox-circle-line')->color('success'),
                    StatusTab::make('inactive')->label('Inactive')->icon('ri-close-circle-line')->color('warning'),
                    StatusTab::make('trash')->label('Trash')->icon('ri-delete-bin-line')->color('danger'),
                ];
            }

            public function getModelClass(): string
            {
                return $this->modelClass;
            }

            public function getRequestClass(): ?string
            {
                return $this->requestClass;
            }

            public function getOwningModuleName(): ?string
            {
                return $this->moduleName;
            }

            protected function resolveClassFilePath(string $class): ?string
            {
                if ($class === self::class) {
                    return parent::resolveClassFilePath($this->definitionClass);
                }

                return parent::resolveClassFilePath($class);
            }
        };
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

    private function resolveFormComponentPath(?string $module, string $resourceSlug, string $studlySingular): string
    {
        $fileName = Str::of($studlySingular)->snake()->replace('_', '-')->toString().'-form.tsx';

        if ($module !== null) {
            return base_path(sprintf('modules/%s/resources/js/components/%s/%s', $module, $resourceSlug, $fileName));
        }

        return resource_path(sprintf('js/components/%s/%s', $resourceSlug, $fileName));
    }

    private function resolveFactoryPath(?string $module, string $studlySingular): string
    {
        if ($module !== null) {
            return base_path(sprintf('modules/%s/database/factories/%sFactory.php', $module, $studlySingular));
        }

        return base_path(sprintf('database/factories/%sFactory.php', $studlySingular));
    }

    private function resolveSeederPath(?string $module, string $studlySingular): string
    {
        if ($module !== null) {
            return base_path(sprintf('modules/%s/database/seeders/%sSeeder.php', $module, $studlySingular));
        }

        return base_path(sprintf('database/seeders/%sSeeder.php', $studlySingular));
    }

    private function resolveMigrationPattern(?string $module, string $resourceSlug): string
    {
        if ($module !== null) {
            return base_path(sprintf('modules/%s/database/migrations/<timestamp>_create_%s_table.php', $module, str_replace('-', '_', $resourceSlug)));
        }

        return base_path(sprintf('database/migrations/<timestamp>_create_%s_table.php', str_replace('-', '_', $resourceSlug)));
    }

    /**
     * @param  array<string, string>  $classMap
     * @param  array<string, string>  $filePaths
     * @return array<string, mixed>
     */
    private function buildFormWiring(ScaffoldDefinition $definition, array $classMap, array $filePaths, string $entity): array
    {
        $entityVariable = Str::camel($entity);

        return [
            'request_class' => $classMap['request'],
            'component_path' => $filePaths['form-component'] ?? null,
            'entity_prop' => $entityVariable,
            'initial_values_prop' => 'initialValues',
            'remember_key_prefix' => $definition->getRoutePrefix(),
            'submit_routes' => [
                'store' => $definition->expectedRouteNames()['store'] ?? null,
                'update' => $definition->expectedRouteNames()['update'] ?? null,
            ],
            'fields' => [
                'name',
                'slug',
                'description',
                'status',
            ],
        ];
    }

    /**
     * @param  array<string, string>  $classMap
     * @return array<string, string>
     */
    private function buildServiceWiring(array $classMap, string $entity): array
    {
        $entityVariable = Str::camel($entity);

        return [
            'class' => $classMap['service'],
            'property' => '$'.$entityVariable.'Service',
            'accessor' => '$this->'.$entityVariable.'Service',
            'controller_method' => 'service',
            'model_variable' => '$'.$entityVariable,
        ];
    }
}
