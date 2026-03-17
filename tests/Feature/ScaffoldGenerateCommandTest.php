<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\Support\InteractsWithModuleManifest;
use Tests\TestCase;

class ScaffoldGenerateCommandTest extends TestCase
{
    use InteractsWithModuleManifest;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpModuleManifest('scaffold-generate-modules.json', [
            'Platform' => 'enabled',
            'Todos' => 'enabled',
        ]);
    }

    protected function tearDown(): void
    {
        $this->tearDownModuleManifest();

        parent::tearDown();
    }

    public function test_scaffold_generate_can_render_an_app_blueprint_as_json(): void
    {
        Artisan::call('scaffold:generate', [
            'name' => 'Widget',
            '--json' => true,
        ]);

        $output = Artisan::output();

        $this->assertStringContainsString('"route_prefix": "app.widgets"', $output);
        $this->assertStringContainsString('"datagrid_contract"', $output);
        $this->assertStringContainsString('"form_wiring"', $output);
        $this->assertStringContainsString('"service_wiring"', $output);
    }

    public function test_scaffold_generate_can_render_a_module_blueprint_as_json(): void
    {
        $this->artisan('scaffold:generate', [
            'name' => 'Agency',
            '--module' => 'Platform',
            '--json' => true,
        ])
            ->expectsOutputToContain('"route_prefix": "platform.agencies"')
            ->assertSuccessful();
    }

    public function test_scaffold_generate_can_write_an_app_scaffold_into_a_sandbox(): void
    {
        $sandboxPath = storage_path('framework/testing/scaffold-generate-app');
        File::deleteDirectory($sandboxPath);

        $this->artisan('scaffold:generate', [
            'name' => 'Widget',
            '--write' => true,
            '--base-path' => $sandboxPath,
            '--json' => true,
        ])
            ->expectsOutputToContain('"write_result"')
            ->assertSuccessful();

        $this->assertFileExists($sandboxPath.'/app/Models/Widget.php');
        $this->assertFileExists($sandboxPath.'/app/Http/Controllers/WidgetController.php');
        $this->assertFileExists($sandboxPath.'/resources/js/components/widgets/widget-form.tsx');
        $this->assertFileExists($sandboxPath.'/routes/web.php');
        $this->assertFileExists($sandboxPath.'/config/navigation.php');
        $this->assertFileExists($sandboxPath.'/tests/Feature/WidgetCrudTest.php');

        $this->assertStringContainsString('class Widget extends Model', File::get($sandboxPath.'/app/Models/Widget.php'));
        $this->assertStringContainsString("route('app.widgets.store')", File::get($sandboxPath.'/resources/js/components/widgets/widget-form.tsx'));
        $this->assertStringContainsString("title={'Edit ' + widget.name}", File::get($sandboxPath.'/resources/js/pages/widgets/edit.tsx'));
        $this->assertStringContainsString('title={widget.name}', File::get($sandboxPath.'/resources/js/pages/widgets/show.tsx'));
        $this->assertStringContainsString("rememberKey: mode === 'create'", File::get($sandboxPath.'/resources/js/components/widgets/widget-form.tsx'));
        $this->assertStringContainsString('markTestSkipped', File::get($sandboxPath.'/tests/Feature/WidgetCrudTest.php'));
        $this->assertStringContainsString("'app.widgets.index'", File::get($sandboxPath.'/config/navigation.php'));
        $this->assertStringContainsString("'widgets.'", File::get($sandboxPath.'/routes/web.php'));

        $migrationFiles = File::glob($sandboxPath.'/database/migrations/*_create_widgets_table.php');
        $this->assertNotFalse($migrationFiles);
        $this->assertCount(1, $migrationFiles);

        File::deleteDirectory($sandboxPath);
    }

    public function test_scaffold_generate_can_write_a_module_scaffold_into_a_sandbox(): void
    {
        $sandboxPath = storage_path('framework/testing/scaffold-generate-module');
        File::deleteDirectory($sandboxPath);

        $this->artisan('scaffold:generate', [
            'name' => 'Announcement',
            '--module' => 'Todos',
            '--write' => true,
            '--base-path' => $sandboxPath,
            '--json' => true,
        ])
            ->expectsOutputToContain('"write_result"')
            ->assertSuccessful();

        $this->assertFileExists($sandboxPath.'/modules/Todos/app/Models/Announcement.php');
        $this->assertFileExists($sandboxPath.'/modules/Todos/app/Definitions/AnnouncementDefinition.php');
        $this->assertFileExists($sandboxPath.'/modules/Todos/resources/js/pages/todos/announcements/index.tsx');
        $this->assertFileExists($sandboxPath.'/modules/Todos/config/abilities.php');
        $this->assertFileExists($sandboxPath.'/modules/Todos/config/navigation.php');
        $this->assertFileExists($sandboxPath.'/modules/Todos/routes/web.php');
        $this->assertFileExists($sandboxPath.'/modules/Todos/tests/Feature/AnnouncementCrudTest.php');

        $this->assertStringContainsString('namespace Modules\\Todos\\Models;', File::get($sandboxPath.'/modules/Todos/app/Models/Announcement.php'));
        $this->assertStringContainsString('protected bool $expectsGeneratedRegistrationMerges = true;', File::get($sandboxPath.'/modules/Todos/app/Definitions/AnnouncementDefinition.php'));
        $this->assertStringContainsString("'viewAnnouncements' => 'view_announcements'", File::get($sandboxPath.'/modules/Todos/config/abilities.php'));
        $this->assertStringContainsString("'todos.announcements.index'", File::get($sandboxPath.'/modules/Todos/config/navigation.php'));
        $this->assertStringContainsString("name('announcements.')", File::get($sandboxPath.'/modules/Todos/routes/web.php'));
        $this->assertStringContainsString('buildScaffoldDatagridState', File::get($sandboxPath.'/modules/Todos/resources/js/pages/todos/announcements/index.tsx'));

        File::deleteDirectory($sandboxPath);
    }

    public function test_scaffold_generate_force_only_overwrites_normal_files_and_preserves_manual_registration_entries(): void
    {
        $sandboxPath = storage_path('framework/testing/scaffold-generate-force');
        File::deleteDirectory($sandboxPath);

        File::ensureDirectoryExists($sandboxPath.'/modules/Todos/config');
        File::ensureDirectoryExists($sandboxPath.'/modules/Todos/routes');

        File::put($sandboxPath.'/modules/Todos/config/abilities.php', <<<'PHP'
<?php

return [
    'manual' => 'manual',
];
PHP);

        File::put($sandboxPath.'/modules/Todos/config/navigation.php', <<<'PHP'
<?php

return [
    'sections' => [
        'todos_workspace' => [
            'label' => 'Todos',
            'weight' => 220,
            'area' => 'modules',
            'show_label' => true,
            'items' => [
                'todo_tasks' => [
                    'label' => 'Tasks',
                    'route' => 'app.todos.index',
                    'active_patterns' => ['app.todos.*'],
                ],
            ],
        ],
    ],
    'badge_functions' => [],
];
PHP);

        File::put($sandboxPath.'/modules/Todos/routes/web.php', <<<'PHP'
<?php

// manual routes
PHP);

        $this->artisan('scaffold:generate', [
            'name' => 'Announcement',
            '--module' => 'Todos',
            '--write' => true,
            '--base-path' => $sandboxPath,
            '--json' => true,
        ])->assertSuccessful();

        $controllerPath = $sandboxPath.'/modules/Todos/app/Http/Controllers/AnnouncementController.php';
        $abilitiesPath = $sandboxPath.'/modules/Todos/config/abilities.php';
        $navigationPath = $sandboxPath.'/modules/Todos/config/navigation.php';
        $routesPath = $sandboxPath.'/modules/Todos/routes/web.php';

        File::put($controllerPath, "<?php\n\n// manual controller\n");

        $this->artisan('scaffold:generate', [
            'name' => 'Announcement',
            '--module' => 'Todos',
            '--write' => true,
            '--base-path' => $sandboxPath,
            '--json' => true,
        ])->assertSuccessful();

        $this->assertStringContainsString('manual controller', File::get($controllerPath));
        $this->assertStringContainsString("'manual' => 'manual'", File::get($abilitiesPath));
        $this->assertStringContainsString("'viewAnnouncements' => 'view_announcements'", File::get($abilitiesPath));
        $this->assertStringContainsString("'todo_tasks' => [", File::get($navigationPath));
        $this->assertStringContainsString("'todos.announcements.index'", File::get($navigationPath));
        $this->assertStringContainsString('// manual routes', File::get($routesPath));
        $this->assertSame(1, substr_count(File::get($routesPath), 'scaffold-generated:announcements:routes:start'));
        $this->assertSame(1, substr_count(File::get($abilitiesPath), 'scaffold-generated:announcements:abilities:start'));
        $this->assertSame(1, substr_count(File::get($navigationPath), 'scaffold-generated:announcements:navigation:start'));

        $this->artisan('scaffold:generate', [
            'name' => 'Announcement',
            '--module' => 'Todos',
            '--write' => true,
            '--force' => true,
            '--base-path' => $sandboxPath,
            '--json' => true,
        ])->assertSuccessful();

        $this->assertStringContainsString('class AnnouncementController extends ScaffoldController', File::get($controllerPath));
        $this->assertStringContainsString("'manual' => 'manual'", File::get($abilitiesPath));
        $this->assertStringContainsString('// manual routes', File::get($routesPath));
        $this->assertSame(1, substr_count(File::get($routesPath), 'scaffold-generated:announcements:routes:start'));
        $this->assertSame(1, substr_count(File::get($abilitiesPath), 'scaffold-generated:announcements:abilities:start'));
        $this->assertSame(1, substr_count(File::get($navigationPath), 'scaffold-generated:announcements:navigation:start'));

        File::deleteDirectory($sandboxPath);
    }
}
