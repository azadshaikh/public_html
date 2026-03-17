<?php

declare(strict_types=1);

namespace Modules\CMS\Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\CMS\Services\FrontendFaviconService;
use ReflectionMethod;
use Tests\TestCase;

class FrontendFaviconServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_manifest_and_asset_urls_use_host_agnostic_paths(): void
    {
        $service = app(FrontendFaviconService::class);

        $manifest = $this->invokePrivateMethod($service, 'buildManifestJson', ['abcdef1234567890']);
        $decodedManifest = json_decode($manifest, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('/web-app-manifest-192x192.png?source=theme&v=abcdef1234567890', $decodedManifest['icons'][0]['src']);
        $this->assertSame('/web-app-manifest-512x512.png?source=theme&v=abcdef1234567890', $decodedManifest['icons'][1]['src']);
        $this->assertStringNotContainsString('frontend.example.com', $manifest);

        $assetUrl = $this->invokePrivateMethod($service, 'buildFrontendAssetUrl', ['/favicon.svg', 'abcdef1234567890']);

        $this->assertSame('/favicon.svg?source=theme&v=abcdef1234567890', $assetUrl);
    }

    public function test_render_head_markup_returns_empty_when_customizer_favicon_is_not_configured(): void
    {
        $service = app(FrontendFaviconService::class);

        $this->assertSame('', $service->renderHeadMarkup());
    }

    public function test_write_file_skips_rewriting_identical_contents(): void
    {
        $service = app(FrontendFaviconService::class);
        $path = storage_path('framework/testing/frontend-favicon-service-write-test.txt');

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        file_put_contents($path, 'original-content');
        touch($path, time() - 10);
        clearstatcache(true, $path);

        $originalMtime = filemtime($path);

        $this->invokePrivateMethod($service, 'writeFile', [$path, 'original-content']);
        clearstatcache(true, $path);

        $this->assertSame($originalMtime, filemtime($path));

        $this->invokePrivateMethod($service, 'writeFile', [$path, 'updated-content']);
        clearstatcache(true, $path);

        $this->assertSame('updated-content', file_get_contents($path));
        $this->assertNotSame($originalMtime, filemtime($path));

        unlink($path);
    }

    /**
     * @param  array<int, mixed>  $arguments
     */
    private function invokePrivateMethod(object $instance, string $method, array $arguments = []): mixed
    {
        $reflection = new ReflectionMethod($instance, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($instance, $arguments);
    }
}
