<?php

namespace Tests\Feature;

use Modules\Todos\Http\Controllers\TodoController;
use Tests\TestCase;

class ScaffoldInspectCommandTest extends TestCase
{
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
}
