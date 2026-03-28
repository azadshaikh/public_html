<?php

namespace Modules\CMS\Tests\Unit;

use Tests\TestCase;

class SeoSettingControllerPatternConsistencyTest extends TestCase
{
    public function test_seo_setting_controller_uses_refactored_concerns(): void
    {
        $controllerContents = $this->readRequiredFile('modules/CMS/app/Http/Controllers/SeoSettingController.php');

        $this->assertStringContainsString('use InteractsWithSeoSettingsAudit;', $controllerContents);
        $this->assertStringContainsString('use InteractsWithSeoSettingsPages;', $controllerContents);
        $this->assertStringNotContainsString('private function getViewData(', $controllerContents);
        $this->assertStringNotContainsString('private function resolveSeoInertiaPage(', $controllerContents);
        $this->assertStringNotContainsString('private function captureCurrentSettings(', $controllerContents);
        $this->assertStringNotContainsString('private function buildChangeSummary(', $controllerContents);
    }

    public function test_seo_setting_concerns_hold_page_and_audit_behaviour(): void
    {
        $pagesConcernContents = $this->readRequiredFile('modules/CMS/app/Http/Controllers/Concerns/InteractsWithSeoSettingsPages.php');
        $auditConcernContents = $this->readRequiredFile('modules/CMS/app/Http/Controllers/Concerns/InteractsWithSeoSettingsAudit.php');

        $this->assertStringContainsString('private function getViewData(', $pagesConcernContents);
        $this->assertStringContainsString('private function resolveSeoInertiaPage(', $pagesConcernContents);
        $this->assertStringContainsString('private function getLocalSeoPageData(', $pagesConcernContents);
        $this->assertStringContainsString('private function getSocialMediaPageData(', $pagesConcernContents);
        $this->assertStringContainsString('private function getDecodedSettings(', $pagesConcernContents);

        $this->assertStringContainsString('private function captureCurrentSettings(', $auditConcernContents);
        $this->assertStringContainsString('private function resolveGroupName(', $auditConcernContents);
        $this->assertStringContainsString('private function logSettingsUpdateWithChanges(', $auditConcernContents);
        $this->assertStringContainsString('private function normalizeValue(', $auditConcernContents);
        $this->assertStringContainsString('private function buildChangeSummary(', $auditConcernContents);
    }

    private function readRequiredFile(string $relativePath): string
    {
        $contents = file_get_contents(base_path($relativePath));

        $this->assertNotFalse($contents, 'Failed to read '.$relativePath);

        return $contents;
    }
}
