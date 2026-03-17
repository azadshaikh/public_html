<?php

namespace App\Modules\Tests\Feature;

use App\Enums\Status;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Todos\Models\Todo;
use Tests\Support\InteractsWithModuleManifest;
use Tests\TestCase;

class ModuleLifecycleTest extends TestCase
{
    use InteractsWithModuleManifest;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpModuleManifest('modules-lifecycle.json', [
            'CMS' => 'enabled',
            'ChatBot' => 'enabled',
            'Todos' => 'disabled',
        ]);

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function tearDown(): void
    {
        $this->tearDownModuleManifest();

        parent::tearDown();
    }

    public function test_enabling_a_module_runs_its_migrations_and_seeders(): void
    {
        Schema::dropIfExists('todos');
        DB::table('migrations')
            ->where('migration', '1988_04_13_000070_create_todos_table')
            ->delete();

        DB::table('permissions')
            ->where('module_slug', 'todos')
            ->delete();

        $user = User::factory()->create([
            'email_verified_at' => now(),
            'first_name' => 'Module',
            'status' => Status::ACTIVE,
        ]);
        $user->assignRole(Role::findByName('super_user', 'web'));

        $this->actingAs($user)
            ->patch(route('app.masters.modules.update'), [
                'modules' => [
                    'CMS' => 'enabled',
                    'ChatBot' => 'enabled',
                    'Todos' => 'enabled',
                ],
            ])
            ->assertRedirect(route('app.masters.modules.index'))
            ->assertSessionHas('success', 'Module settings updated.');

        $this->assertTrue(Schema::hasTable('todos'));
        $this->assertSame(6, DB::table('permissions')->where('module_slug', 'todos')->count());
        $this->assertDatabaseHas('permissions', [
            'name' => 'view_todos',
            'module_slug' => 'todos',
        ]);
    }

    public function test_disabling_a_module_keeps_existing_tables_and_data(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'first_name' => 'Module',
            'status' => Status::ACTIVE,
        ]);
        $user->assignRole(Role::findByName('super_user', 'web'));

        $this->actingAs($user)
            ->patch(route('app.masters.modules.update'), [
                'modules' => [
                    'CMS' => 'enabled',
                    'ChatBot' => 'enabled',
                    'Todos' => 'enabled',
                ],
            ])
            ->assertRedirect(route('app.masters.modules.index'))
            ->assertSessionHas('success', 'Module settings updated.');

        Todo::query()->create([
            'user_id' => $user->id,
            'title' => 'Keep seeded data',
            'description' => 'This record should remain after the module is disabled.',
            'status' => 'pending',
            'priority' => 'medium',
            'visibility' => 'private',
            'is_starred' => false,
        ]);

        $this->actingAs($user)
            ->patch(route('app.masters.modules.update'), [
                'modules' => [
                    'CMS' => 'enabled',
                    'ChatBot' => 'enabled',
                    'Todos' => 'disabled',
                ],
            ])
            ->assertRedirect(route('app.masters.modules.index'))
            ->assertSessionHas('success', 'Module settings updated.');

        $this->assertTrue(Schema::hasTable('todos'));
        $this->assertDatabaseHas('todos', [
            'title' => 'Keep seeded data',
        ]);
    }
}
