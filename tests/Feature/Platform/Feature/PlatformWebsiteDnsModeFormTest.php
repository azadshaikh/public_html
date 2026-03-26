<?php

namespace Tests\Feature\Platform\Feature;

use Modules\Platform\Services\ServerService;
use Modules\Platform\Services\WebsiteAccountService;
use Modules\Platform\Services\WebsiteService;
use ReflectionMethod;
use Tests\TestCase;

class PlatformWebsiteDnsModeFormTest extends TestCase
{
    public function test_platform_website_dns_modes_are_configured(): void
    {
        $dnsModes = config('platform.website.dns_modes');

        $this->assertIsArray($dnsModes);
        $this->assertArrayHasKey('subdomain', $dnsModes);
        $this->assertArrayHasKey('managed', $dnsModes);
        $this->assertArrayHasKey('external', $dnsModes);
    }

    public function test_backend_helpers_expose_and_preserve_dns_mode(): void
    {
        $websiteService = new WebsiteService(
            $this->createMock(ServerService::class),
            $this->createMock(WebsiteAccountService::class),
        );

        $this->assertSame([
            ['value' => 'subdomain', 'label' => 'Agency Subdomain'],
            ['value' => 'managed', 'label' => 'Managed DNS'],
            ['value' => 'external', 'label' => 'External DNS'],
        ], $websiteService->getDnsModeOptionsForForm());

        $prepareUpdateData = new ReflectionMethod($websiteService, 'prepareUpdateData');
        $prepareUpdateData->setAccessible(true);

        $prepared = $prepareUpdateData->invoke($websiteService, [
            'name' => 'Demo',
            'dns_mode' => 'managed',
        ]);

        $this->assertSame('managed', $prepared['dns_mode']);

        $controller = file_get_contents(base_path('modules/Platform/app/Http/Controllers/WebsiteController.php'));

        $this->assertIsString($controller);
        $this->assertStringContainsString("'dnsModeOptions' => \$this->websiteService->getDnsModeOptionsForForm()", $controller);
        $this->assertStringContainsString("'dns_mode' => (string) (\$website->dns_mode ?? 'subdomain')", $controller);
    }

    public function test_frontend_website_form_and_pages_wire_dns_mode_selector(): void
    {
        $websiteForm = file_get_contents(base_path('modules/Platform/resources/js/components/websites/website-form.tsx'));
        $createPage = file_get_contents(base_path('modules/Platform/resources/js/pages/platform/websites/create.tsx'));
        $editPage = file_get_contents(base_path('modules/Platform/resources/js/pages/platform/websites/edit.tsx'));
        $request = file_get_contents(base_path('modules/Platform/app/Http/Requests/WebsiteRequest.php'));

        $this->assertIsString($websiteForm);
        $this->assertIsString($createPage);
        $this->assertIsString($editPage);
        $this->assertIsString($request);

        $this->assertStringContainsString('label="DNS mode"', $websiteForm);
        $this->assertStringContainsString('value={form.data.dns_mode}', $websiteForm);
        $this->assertStringContainsString("form.setField('dns_mode', value)", $websiteForm);
        $this->assertStringContainsString('dnsModeOptions={props.dnsModeOptions}', $createPage);
        $this->assertStringContainsString('dnsModeOptions={props.dnsModeOptions}', $editPage);
        $this->assertStringContainsString("'dns_mode' => ['nullable', 'string', Rule::in(['subdomain', 'managed', 'external'])]", $request);
    }
}
