<?php

namespace Modules\CMS\Tests\Unit;

use Tests\TestCase;

class LocalSeoPagePatternConsistencyTest extends TestCase
{
    public function test_local_seo_page_uses_extracted_section_components(): void
    {
        $pageContents = $this->readRequiredFile('modules/CMS/resources/js/pages/seo/settings/local-seo.tsx');

        $this->assertStringContainsString("from './components/local-seo-helpers';", $pageContents);
        $this->assertStringContainsString("from './components/local-seo-structured-data-card';", $pageContents);
        $this->assertStringContainsString("from './components/local-seo-profile-cards';", $pageContents);
        $this->assertStringContainsString("from './components/local-seo-business-hours-card';", $pageContents);
        $this->assertStringContainsString("from './components/local-seo-social-profiles-card';", $pageContents);
        $this->assertStringContainsString("from './components/local-seo-score-card';", $pageContents);
        $this->assertStringNotContainsString('function optionalUrlValidator(', $pageContents);
        $this->assertStringNotContainsString('function buildScore(', $pageContents);
    }

    public function test_local_seo_components_export_expected_sections(): void
    {
        $structuredCardContents = $this->readRequiredFile('modules/CMS/resources/js/pages/seo/settings/components/local-seo-structured-data-card.tsx');
        $profileCardContents = $this->readRequiredFile('modules/CMS/resources/js/pages/seo/settings/components/local-seo-profile-cards.tsx');
        $hoursCardContents = $this->readRequiredFile('modules/CMS/resources/js/pages/seo/settings/components/local-seo-business-hours-card.tsx');
        $socialCardContents = $this->readRequiredFile('modules/CMS/resources/js/pages/seo/settings/components/local-seo-social-profiles-card.tsx');
        $scoreCardContents = $this->readRequiredFile('modules/CMS/resources/js/pages/seo/settings/components/local-seo-score-card.tsx');
        $helperContents = $this->readRequiredFile('modules/CMS/resources/js/pages/seo/settings/components/local-seo-helpers.ts');

        $this->assertStringContainsString('export function LocalSeoStructuredDataCard(', $structuredCardContents);
        $this->assertStringContainsString('export function LocalSeoBasicIdentityCard(', $profileCardContents);
        $this->assertStringContainsString('export function LocalSeoContactCard(', $profileCardContents);
        $this->assertStringContainsString('export function LocalSeoBusinessHoursCard(', $hoursCardContents);
        $this->assertStringContainsString('export function LocalSeoSocialProfilesCard(', $socialCardContents);
        $this->assertStringContainsString('export function LocalSeoScoreCard(', $scoreCardContents);
        $this->assertStringContainsString('export function optionalUrlValidator(', $helperContents);
        $this->assertStringContainsString('export function buildScore(', $helperContents);
    }

    private function readRequiredFile(string $relativePath): string
    {
        $contents = file_get_contents(base_path($relativePath));

        $this->assertNotFalse($contents, 'Failed to read '.$relativePath);

        return $contents;
    }
}
