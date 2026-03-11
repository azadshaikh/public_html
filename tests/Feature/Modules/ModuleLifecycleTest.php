<?php

namespace Tests\Feature\Modules;

use App\Models\User;
use App\Modules\ModuleManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Modules\Todos\Models\TodoTask;
use Tests\TestCase;

class ModuleLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected string $manifestPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manifestPath = storage_path('framework/testing/modules-lifecycle.json');

        File::ensureDirectoryExists(dirname($this->manifestPath));
        File::put($this->manifestPath, json_encode([
            'CMS' => 'enabled',
            'ChatBot' => 'enabled',
            'Todos' => 'disabled',
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        config()->set('modules.manifest', $this->manifestPath);
        app()->forgetInstance(ModuleManager::class);
        app()->singleton(ModuleManager::class, fn ($app): ModuleManager => new ModuleManager(
            files: $app['files'],
            config: $app['config'],
        ));
    }

    protected function tearDown(): void
    {
        File::delete($this->manifestPath);

        parent::tearDown();
    }

    public function test_enabling_a_module_runs_its_migrations_and_seeders(): void
    {
        Schema::dropIfExists('todo_tasks');
        DB::table('migrations')
            ->where('migration', '2026_03_11_120200_create_todo_tasks_table')
            ->delete();

        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->patch(route('modules.update'), [
                'modules' => [
                    'CMS' => 'enabled',
                    'ChatBot' => 'enabled',
                    'Todos' => 'enabled',
                ],
            ])
            ->assertRedirect(route('modules.index'))
            ->assertSessionHas('status', 'Module settings updated.');

        $this->assertTrue(Schema::hasTable('todo_tasks'));
        $this->assertDatabaseCount('todo_tasks', 4);
        $this->assertDatabaseHas('todo_tasks', [
            'slug' => 'resolve-launch-blocker',
        ]);
    }

    public function test_disabling_a_module_keeps_existing_tables_and_data(): void
    {
        File::put($this->manifestPath, json_encode([
            'CMS' => 'enabled',
            'ChatBot' => 'enabled',
            'Todos' => 'enabled',
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        app()->forgetInstance(ModuleManager::class);
        app()->singleton(ModuleManager::class, fn ($app): ModuleManager => new ModuleManager(
            files: $app['files'],
            config: $app['config'],
        ));

        TodoTask::query()->create([
            'title' => 'Keep seeded data',
            'slug' => 'keep-seeded-data',
            'details' => 'This record should remain after the module is disabled.',
            'status' => 'backlog',
            'priority' => 'medium',
            'owner' => 'QA',
            'due_date' => now()->addDay()->toDateString(),
            'is_blocked' => false,
        ]);

        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->patch(route('modules.update'), [
                'modules' => [
                    'CMS' => 'enabled',
                    'ChatBot' => 'enabled',
                    'Todos' => 'disabled',
                ],
            ])
            ->assertRedirect(route('modules.index'))
            ->assertSessionHas('status', 'Module settings updated.');

        $this->assertTrue(Schema::hasTable('todo_tasks'));
        $this->assertDatabaseHas('todo_tasks', [
            'slug' => 'keep-seeded-data',
        ]);
    }
}
