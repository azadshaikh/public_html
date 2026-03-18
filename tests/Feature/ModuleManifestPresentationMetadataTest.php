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
            $this->assertNotEmpty(trim((string) ($manifest['author'] ?? '')), sprintf('Missing author in %s', $manifestPath));
            $this->assertNotEmpty(trim((string) ($manifest['homepage'] ?? '')), sprintf('Missing homepage in %s', $manifestPath));
            $this->assertNotEmpty(trim((string) ($manifest['icon'] ?? '')), sprintf('Missing icon in %s', $manifestPath));
            $this->assertStringStartsWith('<svg', trim((string) $manifest['icon']), sprintf('Expected SVG icon markup in %s', $manifestPath));
        });
    }
}
