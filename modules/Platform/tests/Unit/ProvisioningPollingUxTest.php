<?php

namespace Modules\Platform\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProvisioningPollingUxTest extends TestCase
{
    #[DataProvider('pollingFiles')]
    public function test_provisioning_pages_poll_every_ten_seconds_without_interval_loops(string $relativePath): void
    {
        $contents = file_get_contents(base_path($relativePath));

        $this->assertNotFalse($contents, sprintf('Failed to read %s', $relativePath));
        $this->assertStringContainsString('const PROVISIONING_POLL_INTERVAL_MS = 10_000;', $contents);
        $this->assertStringContainsString("const PROVISIONING_POLL_INTERVAL_LABEL = 'every 10 seconds';", $contents);
        $this->assertStringContainsString('window.setTimeout(() => {', $contents);
        $this->assertStringContainsString('window.clearTimeout(timeoutId);', $contents);
        $this->assertStringNotContainsString('window.setInterval(', $contents);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function pollingFiles(): array
    {
        return [
            'server provisioning steps table' => ['modules/Platform/resources/js/pages/platform/servers/components/server-provisioning-steps-table.tsx'],
            'website provisioning steps table component' => ['modules/Platform/resources/js/pages/platform/websites/components/website-provisioning-steps-table.tsx'],
            'website show page provisioning table' => ['modules/Platform/resources/js/pages/platform/websites/show.tsx'],
        ];
    }
}
