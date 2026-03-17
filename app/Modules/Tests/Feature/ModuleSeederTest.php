<?php

namespace App\Modules\Tests\Feature;

use App\Models\Permission;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Modules\ChatBot\Models\PromptTemplate;
use Modules\CMS\Models\CmsPost;
use Tests\Support\InteractsWithModuleManifest;
use Tests\TestCase;

class ModuleSeederTest extends TestCase
{
    use InteractsWithModuleManifest;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpModuleManifest('modules-seeder.json', [
            'CMS' => 'enabled',
            'ChatBot' => 'enabled',
            'Todos' => 'enabled',
        ]);

        $this->ensureModuleSeederEnvironment();
    }

    protected function tearDown(): void
    {
        $this->tearDownModuleManifest();

        parent::tearDown();
    }

    public function test_database_seeder_runs_the_enabled_module_seeders(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('cms_posts', 16);
        $this->assertDatabaseCount('prompt_templates', 3);
        $this->assertSame(6, Permission::query()->where('module_slug', 'todos')->count());

        $this->assertTrue(CmsPost::query()->where('slug', 'home')->where('type', 'page')->exists());
        $this->assertTrue(PromptTemplate::query()->where('slug', 'support-concierge')->exists());
        $this->assertTrue(Permission::query()->where('name', 'view_todos')->where('module_slug', 'todos')->exists());
    }

    private function ensureModuleSeederEnvironment(): void
    {
        $migrationPaths = [
            'cms_posts' => 'modules/CMS/database/migrations',
            'prompt_templates' => 'modules/ChatBot/database/migrations',
            'todos' => 'modules/Todos/database/migrations',
        ];

        foreach ($migrationPaths as $table => $path) {
            if (Schema::hasTable($table)) {
                continue;
            }

            Artisan::call('migrate', [
                '--path' => base_path($path),
                '--realpath' => true,
                '--force' => true,
            ]);
        }
    }
}
