<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\Support\InteractsWithModuleManifest;
use Tests\TestCase;

class ModuleInspectCommandTest extends TestCase
{
    use InteractsWithModuleManifest;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpModuleManifest('module-inspect-modules.json');
    }

    protected function tearDown(): void
    {
        $this->tearDownModuleManifest();

        parent::tearDown();
    }

    public function test_module_inspect_can_render_json_for_a_generated_module_scaffold(): void
    {
        $sandboxPath = $this->useModuleSandbox('module-inspect-sandbox');
        $modulePath = $sandboxPath.'/modules/Feedback';

        File::ensureDirectoryExists($modulePath.'/app/Providers');
        File::ensureDirectoryExists($modulePath.'/config');
        File::ensureDirectoryExists($modulePath.'/database/seeders');
        File::ensureDirectoryExists($modulePath.'/resources/js/pages/feedback');
        File::ensureDirectoryExists($modulePath.'/routes');

        File::put($modulePath.'/module.json', json_encode([
            'name' => 'Feedback',
            'slug' => 'feedback',
            'version' => '1.0.0',
            'description' => 'Feedback workflows and ticket triage.',
            'namespace' => 'Modules\\Feedback\\',
            'provider' => 'Modules\\Feedback\\Providers\\FeedbackServiceProvider',
            'page_root' => 'resources/js/pages/feedback',
            'route_files' => ['web' => 'routes/web.php'],
            'abilities_path' => 'config/abilities.php',
            'navigation_path' => 'config/navigation.php',
            'database_seeder' => 'Modules\\Feedback\\Database\\Seeders\\DatabaseSeeder',
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        File::put($modulePath.'/app/Providers/FeedbackServiceProvider.php', <<<'PHP'
<?php

declare(strict_types=1);

namespace Modules\Feedback\Providers;

use App\Modules\Support\ModuleServiceProvider;

class FeedbackServiceProvider extends ModuleServiceProvider
{
    protected function moduleSlug(): string
    {
        return 'feedback';
    }
}
PHP);

        File::put($modulePath.'/database/seeders/DatabaseSeeder.php', <<<'PHP'
<?php

declare(strict_types=1);

namespace Modules\Feedback\Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
    }
}
PHP);

        File::put($modulePath.'/routes/web.php', "<?php\n");
        File::put($modulePath.'/config/abilities.php', "<?php\n\nreturn [];\n");
        File::put($modulePath.'/config/navigation.php', "<?php\n\nreturn ['sections' => [], 'badge_functions' => []];\n");

        $this->withEnabledModules(['Feedback']);

        $exitCode = Artisan::call('module:inspect', [
            'module' => 'Feedback',
            '--json' => true,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('"issuesCount": 0', Artisan::output());
    }

    public function test_module_inspect_can_fail_when_requested_to_enforce_detected_issues(): void
    {
        $sandboxPath = $this->useModuleSandbox('module-inspect-broken-sandbox');
        $modulePath = $sandboxPath.'/modules/Broken';

        File::ensureDirectoryExists($modulePath);
        File::put($modulePath.'/module.json', json_encode([
            'name' => 'Broken',
            'slug' => 'broken',
            'version' => '1.0.0',
            'description' => 'Broken module fixture.',
            'namespace' => 'Modules\\Broken\\',
            'provider' => 'Modules\\Broken\\Providers\\BrokenServiceProvider',
            'page_root' => 'resources/js/pages/broken',
            'route_files' => ['web' => 'routes/web.php'],
            'abilities_path' => 'config/abilities.php',
            'navigation_path' => 'config/navigation.php',
            'database_seeder' => 'Modules\\Broken\\Database\\Seeders\\DatabaseSeeder',
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        $this->withEnabledModules(['Broken']);

        $this->artisan('module:inspect', [
            'module' => 'Broken',
            '--fail-on-issues' => true,
        ])
            ->expectsOutputToContain('Detected issues')
            ->expectsOutputToContain('Missing provider class file')
            ->assertFailed();

    }
}
