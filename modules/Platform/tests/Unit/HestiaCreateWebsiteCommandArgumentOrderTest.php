<?php

namespace Modules\Platform\Tests\Unit;

use Tests\TestCase;

class HestiaCreateWebsiteCommandArgumentOrderTest extends TestCase
{
    public function test_create_website_command_passes_positional_args_in_expected_order(): void
    {
        $path = base_path('modules/Platform/app/Console/HestiaCreateWebsiteCommand.php');
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, 'Failed to read modules/Platform/app/Console/HestiaCreateWebsiteCommand.php');
        $this->assertStringContainsString('$website->website_username,', $contents);
        $this->assertStringContainsString('$website->domain,', $contents);
        $this->assertStringContainsString("'astero-active',", $contents);
        $this->assertStringContainsString('$backendTemplate,', $contents);
        $this->assertStringNotContainsString("'arg1' =>", $contents);
    }
}
