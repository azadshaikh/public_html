<?php

namespace Modules\Platform\Tests\Unit;

use Tests\TestCase;

class WebsiteCrudFlowPatternTest extends TestCase
{
    public function test_website_definition_has_route_for_remove_from_server_action(): void
    {
        $path = base_path('modules/Platform/app/Definitions/WebsiteDefinition.php');
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, 'Failed to read modules/Platform/app/Definitions/WebsiteDefinition.php');
        $this->assertStringContainsString("->route(\$routePrefix.'.remove-from-server')", $contents);
    }

    public function test_website_resource_uses_unsuspend_action_key_for_suspended_websites(): void
    {
        $path = base_path('modules/Platform/app/Http/Resources/WebsiteResource.php');
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, 'Failed to read modules/Platform/app/Http/Resources/WebsiteResource.php');
        $this->assertStringContainsString("\$actions['unsuspend'] = [", $contents);
        $this->assertStringNotContainsString("\$actions['activate'] = [", $contents);
    }

    public function test_provisioning_step_execute_and_revert_routes_use_post_methods(): void
    {
        $path = base_path('modules/Platform/routes/web.php');
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, 'Failed to read modules/Platform/routes/web.php');
        $this->assertStringContainsString("Route::post('/{website}/{step}/execute'", $contents);
        $this->assertStringContainsString("Route::post('/{website}/{step}/revert'", $contents);
        $this->assertStringNotContainsString("Route::get('/{website}/{step}/execute'", $contents);
        $this->assertStringNotContainsString("Route::get('/{website}/{step}/revert'", $contents);
    }

    public function test_website_show_view_executes_provisioning_actions_via_post_json_requests(): void
    {
        $path = base_path('modules/Platform/resources/js/pages/platform/websites/show.tsx');
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, 'Failed to read modules/Platform/resources/js/pages/platform/websites/show.tsx');
        $this->assertStringContainsString("method: 'POST'", $contents);
        $this->assertStringContainsString("'X-Requested-With': 'XMLHttpRequest'", $contents);
    }

    public function test_website_create_form_status_copy_matches_provisioning_flow(): void
    {
        $path = base_path('modules/Platform/resources/js/components/websites/website-form.tsx');
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, 'Failed to read modules/Platform/resources/js/components/websites/website-form.tsx');
        $this->assertStringContainsString('Website created successfully.', $contents);
        $this->assertStringContainsString('Enable for local or LAN sites.', $contents);
        $this->assertStringNotContainsString('Status will be set to Active on creation.', $contents);
    }
}
