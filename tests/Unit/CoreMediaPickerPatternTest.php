<?php

declare(strict_types=1);

namespace Tests\Unit;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class CoreMediaPickerPatternTest extends TestCase
{
    public function test_master_settings_branding_page_uses_media_picker_url_input_for_logo_and_icon(): void
    {
        $path = base_path('resources/js/pages/master-settings/branding.tsx');
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, 'Failed to read resources/js/pages/master-settings/branding.tsx');
        $this->assertStringContainsString('MediaPickerUrlInput', $contents);
        $this->assertStringContainsString('value={form.data.logo}', $contents);
        $this->assertStringContainsString('value={form.data.icon}', $contents);
        $this->assertStringContainsString("pickerAction = route('app.masters.settings.branding')", $contents);
    }

    public function test_master_settings_controller_exposes_media_picker_props_for_branding_page(): void
    {
        $path = base_path('app/Http/Controllers/Masters/SettingsController.php');
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, 'Failed to read app/Http/Controllers/Masters/SettingsController.php');
        $this->assertStringContainsString('use App\Traits\HasMediaPicker;', $contents);
        $this->assertStringContainsString('use HasMediaPicker;', $contents);
        $this->assertStringContainsString("return Inertia::render('master-settings/branding', [", $contents);
        $this->assertStringContainsString('...$this->getMediaPickerProps(),', $contents);
    }

    public function test_core_inertia_pages_do_not_currently_expose_other_named_raw_image_url_fields(): void
    {
        $pagesPath = base_path('resources/js/pages');
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($pagesPath));
        $matches = [];

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'tsx') {
                continue;
            }

            $realPath = $file->getRealPath();

            if ($realPath === false) {
                $this->fail('Failed to resolve a resources/js/pages file path.');
            }

            if ($realPath === base_path('resources/js/pages/master-settings/branding.tsx')) {
                continue;
            }

            $contents = file_get_contents($realPath);
            $this->assertNotFalse($contents, sprintf('Failed to read %s', $realPath));

            if (preg_match('/\b(?:logo_url|icon_url|favicon_url|branding_logo|branding_icon)\b/', $contents) === 1) {
                $matches[] = str_replace(base_path().'/', '', $realPath);
            }
        }

        $this->assertSame([], $matches, 'Unexpected named raw image URL fields found in core Inertia pages: '.implode(', ', $matches));
    }
}
