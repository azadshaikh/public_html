<?php

namespace Tests\Feature;

use App\Scaffold\ScaffoldIntrospector;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ScaffoldDoctorCommandTest extends TestCase
{
    public function test_scaffold_doctor_passes_for_current_application_state(): void
    {
        $this->artisan('scaffold:doctor')
            ->expectsOutputToContain('Scaffold doctor passed')
            ->assertSuccessful();
    }

    public function test_scaffold_doctor_fails_when_a_generated_routes_registration_block_is_missing(): void
    {
        $sandboxPath = storage_path('framework/testing/scaffold-doctor-routes-missing');
        File::deleteDirectory($sandboxPath);

        $inspection = $this->makeGeneratedInspectionFixture($sandboxPath, includeAbilities: false);

        File::put($inspection['registration_paths']['routes'], "<?php\n\n// no generated route block\n");
        File::put($inspection['registration_paths']['navigation'], $this->wrapMarkerBlock(
            $inspection['registration_markers']['navigation'],
            "'scaffold_widgets' => ['route' => ['name' => 'app.widgets.index'], 'permission' => 'view_widgets', 'active_patterns' => ['app.widgets.*']],",
        ));

        $this->swapScaffoldIntrospector($inspection);

        $this->artisan('scaffold:doctor')
            ->expectsOutputToContain("Missing generated routes registration block 'scaffold-generated:widgets:routes'")
            ->assertFailed();

        File::deleteDirectory($sandboxPath);
    }

    public function test_scaffold_doctor_fails_when_a_generated_navigation_registration_block_is_duplicated(): void
    {
        $sandboxPath = storage_path('framework/testing/scaffold-doctor-navigation-duplicate');
        File::deleteDirectory($sandboxPath);

        $inspection = $this->makeGeneratedInspectionFixture($sandboxPath, includeAbilities: false);

        File::put($inspection['registration_paths']['routes'], $this->wrapMarkerBlock(
            $inspection['registration_markers']['routes'],
            "Route::get('/{status?}', fn () => null)->name('app.widgets.index');\nRoute::get('/create', fn () => null)->name('app.widgets.create');\nRoute::post('/', fn () => null)->name('app.widgets.store');\nRoute::get('/{widget}', fn () => null)->name('app.widgets.show');\nRoute::get('/{widget}/edit', fn () => null)->name('app.widgets.edit');\nRoute::put('/{widget}', fn () => null)->name('app.widgets.update');\nRoute::delete('/{widget}', fn () => null)->name('app.widgets.destroy');\nRoute::post('/bulk-action', fn () => null)->name('app.widgets.bulk-action');",
        ));

        $duplicateBlock = $this->wrapMarkerBlock(
            $inspection['registration_markers']['navigation'],
            "'scaffold_widgets' => ['route' => ['name' => 'app.widgets.index'], 'permission' => 'view_widgets', 'active_patterns' => ['app.widgets.*']],",
        );

        File::put($inspection['registration_paths']['navigation'], $duplicateBlock."\n\n".$duplicateBlock);

        $this->swapScaffoldIntrospector($inspection);

        $this->artisan('scaffold:doctor')
            ->expectsOutputToContain("Duplicate generated navigation registration block 'scaffold-generated:widgets:navigation'")
            ->assertFailed();

        File::deleteDirectory($sandboxPath);
    }

    public function test_scaffold_doctor_fails_when_a_generated_abilities_block_drifts_from_expected_mappings(): void
    {
        $sandboxPath = storage_path('framework/testing/scaffold-doctor-abilities-drift');
        File::deleteDirectory($sandboxPath);

        $inspection = $this->makeGeneratedInspectionFixture($sandboxPath, includeAbilities: true);

        File::put($inspection['registration_paths']['routes'], $this->wrapMarkerBlock(
            $inspection['registration_markers']['routes'],
            "Route::get('/{status?}', fn () => null)->name('app.widgets.index');\nRoute::get('/create', fn () => null)->name('app.widgets.create');\nRoute::post('/', fn () => null)->name('app.widgets.store');\nRoute::get('/{widget}', fn () => null)->name('app.widgets.show');\nRoute::get('/{widget}/edit', fn () => null)->name('app.widgets.edit');\nRoute::put('/{widget}', fn () => null)->name('app.widgets.update');\nRoute::delete('/{widget}', fn () => null)->name('app.widgets.destroy');\nRoute::post('/bulk-action', fn () => null)->name('app.widgets.bulk-action');",
        ));

        File::put($inspection['registration_paths']['navigation'], $this->wrapMarkerBlock(
            $inspection['registration_markers']['navigation'],
            "'scaffold_widgets' => ['route' => ['name' => 'app.widgets.index'], 'permission' => 'view_widgets', 'active_patterns' => ['app.widgets.*']],",
        ));

        File::put($inspection['registration_paths']['abilities'], <<<'PHP'
<?php

return [
// scaffold-generated:widgets:abilities:start
    'viewWidgets' => 'view_widgets',
    'addWidgets' => 'add_widgets',
    'editWidgets' => 'edit_widgets',
    'deleteWidgets' => 'delete_widgetz',
// scaffold-generated:widgets:abilities:end
];
PHP);

        $this->swapScaffoldIntrospector($inspection);

        $this->artisan('scaffold:doctor')
            ->expectsOutputToContain("Generated abilities block in [{$inspection['registration_paths']['abilities']}] does not contain expected mapping ['deleteWidgets' => 'delete_widgets']")
            ->assertFailed();

        File::deleteDirectory($sandboxPath);
    }

    public function test_scaffold_doctor_fails_when_a_generated_navigation_block_omits_status_all_route_params(): void
    {
        $sandboxPath = storage_path('framework/testing/scaffold-doctor-navigation-semantic');
        File::deleteDirectory($sandboxPath);

        $inspection = $this->makeGeneratedInspectionFixture($sandboxPath, includeAbilities: false);

        File::put($inspection['registration_paths']['routes'], $this->wrapMarkerBlock(
            $inspection['registration_markers']['routes'],
            "\\App\\Http\\Controllers\\WidgetController::class\ncrud.exceptions\nRoute::post('/bulk-action', fn () => null)->name('app.widgets.bulk-action');\nRoute::get('/{status?}', fn () => null)->where('status', '^(all|active|inactive|trash)$')->name('app.widgets.index');\nRoute::get('/create', fn () => null)->name('app.widgets.create');\nRoute::post('/', fn () => null)->name('app.widgets.store');\nRoute::get('/{widget}', fn () => null)->name('app.widgets.show');\nRoute::get('/{widget}/edit', fn () => null)->name('app.widgets.edit');\nRoute::put('/{widget}', fn () => null)->name('app.widgets.update');\nRoute::delete('/{widget}', fn () => null)->name('app.widgets.destroy');",
        ));

        File::put($inspection['registration_paths']['navigation'], $this->wrapMarkerBlock(
            $inspection['registration_markers']['navigation'],
            "'scaffold_widgets' => ['route' => ['name' => 'app.widgets.index'], 'permission' => 'view_widgets', 'active_patterns' => ['app.widgets.*']],",
        ));

        $this->swapScaffoldIntrospector($inspection);

        $this->artisan('scaffold:doctor')
            ->expectsOutputToContain('does not include the expected status=all route params')
            ->assertFailed();

        File::deleteDirectory($sandboxPath);
    }

    public function test_scaffold_doctor_can_strictly_audit_legacy_registration_references(): void
    {
        $sandboxPath = storage_path('framework/testing/scaffold-doctor-legacy-strict');
        File::deleteDirectory($sandboxPath);

        $inspection = $this->makeGeneratedInspectionFixture($sandboxPath, includeAbilities: false);
        $inspection['expects_generated_registration_merges'] = false;

        File::put($inspection['registration_paths']['routes'], "<?php\n\n// unrelated routes\n");
        File::put($inspection['registration_paths']['navigation'], <<<'PHP'
<?php

return [
    'sections' => [
        'dashboard' => [
            'items' => [
                'dashboard' => [
                    'route' => 'dashboard',
                ],
            ],
        ],
    ],
];
PHP);

        $inspection['registration_audit'] = app(ScaffoldIntrospector::class)->auditRegistration($inspection);
        $this->swapScaffoldIntrospector($inspection);

        $this->artisan('scaffold:doctor', [
            '--strict-legacy-registrations' => true,
        ])
            ->expectsOutputToContain('does not reference the scaffold controller or expected index route')
            ->expectsOutputToContain('does not reference the expected route, view permission, and active pattern trio')
            ->assertFailed();

        File::deleteDirectory($sandboxPath);
    }

    /**
     * @return array<string, mixed>
     */
    private function makeGeneratedInspectionFixture(string $sandboxPath, bool $includeAbilities): array
    {
        File::ensureDirectoryExists($sandboxPath.'/app/Http/Controllers');
        File::ensureDirectoryExists($sandboxPath.'/app/Definitions');
        File::ensureDirectoryExists($sandboxPath.'/app/Models');
        File::ensureDirectoryExists($sandboxPath.'/app/Http/Requests');
        File::ensureDirectoryExists($sandboxPath.'/resources/js/pages/widgets');
        File::ensureDirectoryExists($sandboxPath.'/tests/Feature');
        File::ensureDirectoryExists($sandboxPath.'/config');
        File::ensureDirectoryExists($sandboxPath.'/routes');

        $controllerPath = $sandboxPath.'/app/Http/Controllers/WidgetController.php';
        $definitionPath = $sandboxPath.'/app/Definitions/WidgetDefinition.php';
        $modelPath = $sandboxPath.'/app/Models/Widget.php';
        $requestPath = $sandboxPath.'/app/Http/Requests/WidgetRequest.php';

        foreach ([$controllerPath, $definitionPath, $modelPath, $requestPath] as $path) {
            File::put($path, "<?php\n");
        }

        foreach (['index', 'create', 'edit', 'show'] as $pageName) {
            File::put($sandboxPath.'/resources/js/pages/widgets/'.$pageName.'.tsx', 'export default function Page() { return null; }');
        }

        File::put($sandboxPath.'/tests/Feature/WidgetCrudTest.php', "<?php\n");

        $registrationPaths = [
            'routes' => $sandboxPath.'/routes/web.php',
            'navigation' => $sandboxPath.'/config/navigation.php',
        ];

        if ($includeAbilities) {
            $registrationPaths['abilities'] = $sandboxPath.'/config/abilities.php';
        }

        return [
            'controller' => 'App\\Http\\Controllers\\WidgetController',
            'definition_class' => 'App\\Definitions\\WidgetDefinition',
            'module' => null,
            'entity_name' => 'Widget',
            'entity_plural' => 'Widgets',
            'route_prefix' => 'app.widgets',
            'permission_prefix' => 'widgets',
            'inertia_page' => 'widgets',
            'expected_inertia_page' => 'widgets',
            'golden_path_example' => false,
            'uses_soft_deletes' => false,
            'validate_conventional_route_names' => true,
            'page_components' => [
                'index' => 'widgets/index',
                'create' => 'widgets/create',
                'edit' => 'widgets/edit',
                'show' => 'widgets/show',
            ],
            'route_names' => [
                'index' => 'app.widgets.index',
                'create' => 'app.widgets.create',
                'store' => 'app.widgets.store',
                'show' => 'app.widgets.show',
                'edit' => 'app.widgets.edit',
                'update' => 'app.widgets.update',
                'destroy' => 'app.widgets.destroy',
                'bulk-action' => 'app.widgets.bulk-action',
            ],
            'permission_names' => [
                'view' => 'view_widgets',
                'add' => 'add_widgets',
                'edit' => 'edit_widgets',
                'delete' => 'delete_widgets',
            ],
            'ability_map' => [
                'viewWidgets' => 'view_widgets',
                'addWidgets' => 'add_widgets',
                'editWidgets' => 'edit_widgets',
                'deleteWidgets' => 'delete_widgets',
            ],
            'registration_paths' => $registrationPaths,
            'registration_markers' => array_filter([
                'routes' => 'scaffold-generated:widgets:routes',
                'navigation' => 'scaffold-generated:widgets:navigation',
                'abilities' => $includeAbilities ? 'scaffold-generated:widgets:abilities' : null,
            ]),
            'file_paths' => [
                'controller' => $controllerPath,
                'definition' => $definitionPath,
                'model' => $modelPath,
                'request' => $requestPath,
                'page:index' => $sandboxPath.'/resources/js/pages/widgets/index.tsx',
                'page:create' => $sandboxPath.'/resources/js/pages/widgets/create.tsx',
                'page:edit' => $sandboxPath.'/resources/js/pages/widgets/edit.tsx',
                'page:show' => $sandboxPath.'/resources/js/pages/widgets/show.tsx',
            ],
            'test_paths' => [
                'crud' => $sandboxPath.'/tests/Feature/WidgetCrudTest.php',
            ],
            'registered_routes' => [
                'index' => ['app.widgets.index'],
                'create' => ['app.widgets.create'],
                'store' => ['app.widgets.store'],
                'show' => ['app.widgets.show'],
                'edit' => ['app.widgets.edit'],
                'update' => ['app.widgets.update'],
                'destroy' => ['app.widgets.destroy'],
                'bulk-action' => ['app.widgets.bulk-action'],
            ],
            'expects_generated_registration_merges' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $inspection
     */
    private function swapScaffoldIntrospector(array $inspection): void
    {
        $this->app->instance(ScaffoldIntrospector::class, new class($inspection) extends ScaffoldIntrospector
        {
            /**
             * @param  array<string, mixed>  $inspection
             */
            public function __construct(private readonly array $inspection) {}

            public function discoverControllers(): array
            {
                return [$this->inspection['controller']];
            }

            public function inspectController(string $controllerClass): array
            {
                return $this->inspection;
            }
        });
    }

    private function wrapMarkerBlock(string $marker, string $contents): string
    {
        return sprintf("// %s:start\n%s\n// %s:end\n", $marker, $contents, $marker);
    }
}
