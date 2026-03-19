<?php

namespace Modules\Platform\Tests\Unit;

use Tests\TestCase;

class PlatformCrudDatagridPatternTest extends TestCase
{
    /**
     * @return array<int, string>
     */
    private function platformCrudIndexPages(): array
    {
        return [
            'modules/Platform/resources/js/pages/platform/agencies/index.tsx',
            'modules/Platform/resources/js/pages/platform/domains/index.tsx',
            'modules/Platform/resources/js/pages/platform/dns/index.tsx',
            'modules/Platform/resources/js/pages/platform/providers/index.tsx',
            'modules/Platform/resources/js/pages/platform/secrets/index.tsx',
            'modules/Platform/resources/js/pages/platform/servers/index.tsx',
            'modules/Platform/resources/js/pages/platform/ssl-certificates/index.tsx',
            'modules/Platform/resources/js/pages/platform/tlds/index.tsx',
            'modules/Platform/resources/js/pages/platform/websites/index.tsx',
        ];
    }

    public function test_platform_crud_index_pages_follow_shared_datagrid_pattern(): void
    {
        foreach ($this->platformCrudIndexPages() as $file) {
            $contents = file_get_contents(base_path($file));

            $this->assertNotFalse($contents, "Failed to read {$file}");
            $this->assertStringContainsString('buildScaffoldDatagridState', $contents, "{$file} should derive filter, tab, sort, and per-page state from the shared scaffold adapter.");
            $this->assertStringContainsString('perPageOptions: [10, 25, 50, 100]', $contents, "{$file} should expose the standard per-page selector options.");
            $this->assertStringContainsString('scaffoldColumns={config.columns}', $contents, "{$file} should pass backend scaffold column metadata into the datagrid.");
            $this->assertStringContainsString('filters={gridFilters}', $contents, "{$file} should use shared scaffold-derived filters.");
            $this->assertStringContainsString("tabs={{ name: 'status', items: statusTabs }}", $contents, "{$file} should use the shared status-tab contract.");
            $this->assertStringContainsString('perPage={perPage}', $contents, "{$file} should wire the shared per-page state into the datagrid.");
        }
    }

    public function test_platform_crud_index_pages_keep_shared_bulk_action_and_empty_state_helpers(): void
    {
        foreach ($this->platformCrudIndexPages() as $file) {
            $contents = file_get_contents(base_path($file));

            $this->assertNotFalse($contents, "Failed to read {$file}");
            $this->assertStringContainsString('buildScaffoldActionHandlers', $contents, "{$file} should use scaffold-backed row and bulk action helpers.");
            $this->assertStringContainsString('buildScaffoldEmptyState', $contents, "{$file} should use the shared scaffold empty-state helper.");
            $this->assertStringContainsString('<Datagrid', $contents, "{$file} should render through the shared datagrid component.");
        }
    }
}
