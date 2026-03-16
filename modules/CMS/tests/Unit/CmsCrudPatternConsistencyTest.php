<?php

namespace Modules\CMS\Tests\Unit;

use Tests\TestCase;

class CmsCrudPatternConsistencyTest extends TestCase
{
    public function test_menu_routes_use_patch_restore_and_force_delete_endpoint(): void
    {
        $path = base_path('modules/CMS/routes/web.php');
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, 'Failed to read modules/CMS/routes/web.php');
        $this->assertStringContainsString("Route::delete('/{menu}/force-delete', [MenuController::class, 'forceDelete'])->name('force-delete')", $contents);
        $this->assertStringContainsString("Route::patch('/{menu}/restore', [MenuController::class, 'restore'])->name('restore')", $contents);
        $this->assertStringNotContainsString("Route::post('/{menu}/restore', [MenuController::class, 'restore'])->name('restore')", $contents);
    }

    public function test_menu_definition_is_fully_bound_to_scaffold_request_and_actions(): void
    {
        $path = base_path('modules/CMS/app/Definitions/MenuDefinition.php');
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, 'Failed to read modules/CMS/app/Definitions/MenuDefinition.php');
        $this->assertStringContainsString('use Modules\\CMS\\Http\\Requests\\MenuFormRequest;', $contents);
        $this->assertStringContainsString('public function getRequestClass(): ?string', $contents);
        $this->assertStringContainsString('return MenuFormRequest::class;', $contents);
        $this->assertStringContainsString('->route("{$this->routePrefix}.restore")', $contents);
        $this->assertStringContainsString("->method('PATCH')", $contents);
        $this->assertStringContainsString('->route("{$this->routePrefix}.force-delete")', $contents);
    }

    public function test_post_and_page_status_routes_match_configured_status_tabs(): void
    {
        $path = base_path('modules/CMS/routes/web.php');
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, 'Failed to read modules/CMS/routes/web.php');
        $this->assertStringContainsString('^(all|published|draft|scheduled|trash)$', $contents);
        $this->assertStringNotContainsString('^(all|published|draft|scheduled|pending_review|trash)$', $contents);
    }

    public function test_menu_model_uses_laravel_casts_method(): void
    {
        $path = base_path('modules/CMS/app/Models/Menu.php');
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, 'Failed to read modules/CMS/app/Models/Menu.php');
        $this->assertStringContainsString('protected function casts(): array', $contents);
        $this->assertStringNotContainsString('protected $casts = [', $contents);
    }

    public function test_form_and_design_block_models_render_full_status_badges(): void
    {
        $formPath = base_path('modules/CMS/app/Models/Form.php');
        $formContents = file_get_contents($formPath);
        $this->assertNotFalse($formContents, 'Failed to read modules/CMS/app/Models/Form.php');
        $this->assertStringContainsString('<span class="badge bg-success-subtle text-success">Published</span>', $formContents);
        $this->assertStringNotContainsString("'published' => 'success'", $formContents);

        $designBlockPath = base_path('modules/CMS/app/Models/DesignBlock.php');
        $designBlockContents = file_get_contents($designBlockPath);
        $this->assertNotFalse($designBlockContents, 'Failed to read modules/CMS/app/Models/DesignBlock.php');
        $this->assertStringContainsString('<span class="badge bg-success-subtle text-success">Published</span>', $designBlockContents);
        $this->assertStringNotContainsString("'published' => 'success'", $designBlockContents);
    }
}
