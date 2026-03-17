<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Modules\Platform\Http\Controllers\AgencyController;
use Modules\Todos\Http\Controllers\TodoController;
use Tests\Support\InteractsWithModuleManifest;
use Tests\TestCase;

class ScaffoldInspectCommandTest extends TestCase
{
    use InteractsWithModuleManifest;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpModuleManifest('scaffold-inspect-modules.json', [
            'Platform' => 'enabled',
            'Todos' => 'enabled',
        ]);
    }

    protected function tearDown(): void
    {
        $this->tearDownModuleManifest();

        parent::tearDown();
    }

    public function test_scaffold_inspect_can_render_json_for_a_specific_controller(): void
    {
        $this->artisan('scaffold:inspect', [
            'target' => TodoController::class,
            '--json' => true,
        ])
            ->assertSuccessful();
    }

    public function test_scaffold_inspect_fails_for_unknown_targets(): void
    {
        $this->artisan('scaffold:inspect', [
            'target' => 'DefinitelyMissingScaffold',
        ])
            ->expectsOutputToContain('No scaffold controller matched [DefinitelyMissingScaffold].')
            ->assertFailed();
    }

    public function test_scaffold_inspect_marks_the_golden_path_example_in_json_output(): void
    {
        $this->artisan('scaffold:inspect', [
            'target' => AgencyController::class,
            '--json' => true,
        ])
            ->expectsOutputToContain('"golden_path_example": true')
            ->assertSuccessful();
    }

    public function test_scaffold_inspect_includes_registration_metadata_in_json_output(): void
    {
        Artisan::call('scaffold:inspect', [
            'target' => TodoController::class,
            '--json' => true,
        ]);

        $output = Artisan::output();

        $this->assertStringContainsString('"registration_paths"', $output);
        $this->assertStringContainsString('"registration_audit"', $output);
    }
}
