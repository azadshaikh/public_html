<?php

namespace Modules\CMS\Tests\Unit;

use Tests\TestCase;

class IntegrationsPagePatternConsistencyTest extends TestCase
{
    public function test_integrations_page_uses_extracted_section_support_files(): void
    {
        $pageContents = $this->readRequiredFile('modules/CMS/resources/js/pages/cms/integrations/index.tsx');

        $this->assertStringContainsString("from './components/integration-section-forms';", $pageContents);
        $this->assertStringContainsString("from './components/integrations-meta';", $pageContents);
        $this->assertStringContainsString('manageIntegrationsSeoSettings ?? false', $pageContents);
        $this->assertStringNotContainsString('function SectionCard(', $pageContents);
        $this->assertStringNotContainsString('function CodeField(', $pageContents);
        $this->assertStringNotContainsString('const sections: SectionMeta[] = [', $pageContents);
    }

    public function test_integrations_support_files_export_expected_building_blocks(): void
    {
        $formsContents = $this->readRequiredFile('modules/CMS/resources/js/pages/cms/integrations/components/integration-section-forms.tsx');
        $shellContents = $this->readRequiredFile('modules/CMS/resources/js/pages/cms/integrations/components/integration-section-shell.tsx');
        $metaContents = $this->readRequiredFile('modules/CMS/resources/js/pages/cms/integrations/components/integrations-meta.ts');

        $this->assertStringContainsString('export function buildIntegrationSectionMap(', $formsContents);
        $this->assertStringContainsString('export function IntegrationSectionCard(', $shellContents);
        $this->assertStringContainsString('export function IntegrationCodeField(', $shellContents);
        $this->assertStringContainsString('export const integrationSections: SectionMeta[] = [', $metaContents);
        $this->assertStringContainsString('export const integrationsBreadcrumbs: BreadcrumbItem[] = [', $metaContents);
    }

    private function readRequiredFile(string $relativePath): string
    {
        $contents = file_get_contents(base_path($relativePath));

        $this->assertNotFalse($contents, 'Failed to read '.$relativePath);

        return $contents;
    }
}
