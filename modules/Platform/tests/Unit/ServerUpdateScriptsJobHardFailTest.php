<?php

namespace Modules\Platform\Tests\Unit;

use Tests\TestCase;

class ServerUpdateScriptsJobHardFailTest extends TestCase
{
    public function test_server_update_scripts_job_fails_when_any_uploads_are_incomplete(): void
    {
        $path = base_path('modules/Platform/app/Jobs/ServerUpdateScripts.php');
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, 'Failed to read modules/Platform/app/Jobs/ServerUpdateScripts.php');
        $this->assertStringContainsString('Failed to upload bin scripts', $contents);
        $this->assertStringContainsString('Failed to upload templates', $contents);
        $this->assertStringContainsString('Failed to upload %d bin script file(s): %s', $contents);
        $this->assertStringContainsString('Failed to upload %d template file(s): %s', $contents);
    }
}
