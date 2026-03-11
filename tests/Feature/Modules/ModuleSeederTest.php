<?php

namespace Tests\Feature\Modules;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ChatBot\Models\PromptTemplate;
use Modules\Cms\Models\CmsPage;
use Modules\Todos\Models\TodoTask;
use Tests\TestCase;

class ModuleSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_runs_the_enabled_module_seeders(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('cms_pages', 3);
        $this->assertDatabaseCount('prompt_templates', 3);
        $this->assertDatabaseCount('todo_tasks', 4);

        $this->assertTrue(CmsPage::query()->where('slug', 'home')->exists());
        $this->assertTrue(PromptTemplate::query()->where('slug', 'support-concierge')->exists());
        $this->assertTrue(TodoTask::query()->where('slug', 'resolve-launch-blocker')->exists());
    }
}
