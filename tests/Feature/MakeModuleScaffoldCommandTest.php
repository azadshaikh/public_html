<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\Support\InteractsWithModuleManifest;
use Tests\TestCase;

class MakeModuleScaffoldCommandTest extends TestCase
{
    use InteractsWithModuleManifest;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpModuleManifest('make-module-scaffold-modules.json');
    }

    protected function tearDown(): void
    {
        $this->tearDownModuleManifest();

        parent::tearDown();
    }

    public function test_make_module_scaffold_can_write_a_module_shell_and_nested_scaffold_into_a_sandbox(): void
    {
        $sandboxPath = $this->useModuleSandbox('make-module-scaffold-sandbox');

        $exitCode = Artisan::call('make:module-scaffold', [
            'module' => 'Feedback',
            'resource' => 'Ticket',
            '--description' => 'Feedback workflows and ticket triage.',
            '--write' => true,
            '--base-path' => $sandboxPath,
            '--json' => true,
        ]);

        $this->assertSame(0, $exitCode);

        $modulePath = $sandboxPath.'/modules/Feedback';

        $this->assertFileExists($modulePath.'/module.json');
        $this->assertFileExists($modulePath.'/app/Providers/FeedbackServiceProvider.php');
        $this->assertFileExists($modulePath.'/routes/web.php');
        $this->assertFileExists($modulePath.'/config/abilities.php');
        $this->assertFileExists($modulePath.'/config/navigation.php');
        $this->assertFileExists($modulePath.'/database/seeders/DatabaseSeeder.php');
        $this->assertFileExists($modulePath.'/app/Definitions/TicketDefinition.php');
        $this->assertFileExists($modulePath.'/app/Http/Controllers/TicketController.php');
        $this->assertFileExists($modulePath.'/resources/js/pages/feedback/tickets/index.tsx');

        $this->assertStringContainsString('"page_root": "resources/js/pages/feedback"', File::get($modulePath.'/module.json'));
        $this->assertStringContainsString('DatabaseSeeder', File::get($modulePath.'/module.json'));
        $this->assertStringContainsString('class FeedbackServiceProvider extends ModuleServiceProvider', File::get($modulePath.'/app/Providers/FeedbackServiceProvider.php'));
        $this->assertStringContainsString('// Register Feedback routes here.', File::get($modulePath.'/routes/web.php'));
        $this->assertStringContainsString('scaffold-generated:tickets:routes:start', File::get($modulePath.'/routes/web.php'));
        $this->assertStringContainsString("Column::make('status')->label('Status')->badge()->sortable()", File::get($modulePath.'/app/Definitions/TicketDefinition.php'));
        $this->assertStringContainsString("Filter::search('search')", File::get($modulePath.'/app/Definitions/TicketDefinition.php'));
    }
}
