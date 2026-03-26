<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ModuleManifestPresentationMetadataTest extends TestCase
{
    public function test_all_local_module_manifests_include_presentation_metadata(): void
    {
        $manifests = collect(glob(base_path('modules/*/module.json')) ?: []);

        $this->assertNotEmpty($manifests, 'Expected at least one local module manifest.');

        $manifests->each(function (string $manifestPath): void {
            $manifest = json_decode(File::get($manifestPath), true, flags: JSON_THROW_ON_ERROR);

            $this->assertIsArray($manifest);
            $this->assertNotEmpty(trim((string) ($manifest['version'] ?? '')), sprintf('Missing version in %s', $manifestPath));
            $this->assertNotEmpty(trim((string) ($manifest['description'] ?? '')), sprintf('Missing description in %s', $manifestPath));

            if (array_key_exists('author', $manifest)) {
                $this->assertNotEmpty(trim((string) $manifest['author']), sprintf('Missing author in %s', $manifestPath));
            }

            if (array_key_exists('homepage', $manifest)) {
                $this->assertNotEmpty(trim((string) $manifest['homepage']), sprintf('Missing homepage in %s', $manifestPath));
            }

            if (array_key_exists('icon', $manifest)) {
                $icon = trim((string) $manifest['icon']);

                $this->assertNotEmpty($icon, sprintf('Missing icon in %s', $manifestPath));
                $this->assertStringStartsWith('<', $icon, sprintf('Expected HTML icon markup in %s', $manifestPath));
                $this->assertStringContainsString('>', $icon, sprintf('Expected HTML icon markup in %s', $manifestPath));
            }
        });
    }
}
