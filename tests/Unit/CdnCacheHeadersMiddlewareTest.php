<?php

namespace Tests\Unit;

use App\Http\Middleware\CdnCacheHeadersMiddleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class CdnCacheHeadersMiddlewareTest extends TestCase
{
    public function test_public_guest_pages_receive_cacheable_headers(): void
    {
        config([
            'app.cdn_cache_headers' => true,
            'app.cdn_cache_max_age' => 31536000,
            'app.admin_slug' => 'admin',
        ]);

        $middleware = new CdnCacheHeadersMiddleware;
        $request = Request::create('/landing', 'GET');

        $response = $middleware->handle($request, fn (): Response => new Response('ok', 200));

        $cacheControl = (string) $response->headers->get('Cache-Control');

        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=31536000', $cacheControl);
        $this->assertStringContainsString('s-maxage=31536000', $cacheControl);
    }

    public function test_admin_and_session_routes_receive_no_store_headers(): void
    {
        config([
            'app.cdn_cache_headers' => true,
            'app.cdn_cache_max_age' => 31536000,
            'app.admin_slug' => 'rv6e6a2k8',
        ]);

        $middleware = new CdnCacheHeadersMiddleware;
        $request = Request::create('/rv6e6a2k8/login', 'GET');

        $response = $middleware->handle($request, fn (): Response => new Response('login', 200));

        $cacheControl = (string) $response->headers->get('Cache-Control');

        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('no-cache', $cacheControl);
        $this->assertStringContainsString('must-revalidate', $cacheControl);
        $this->assertStringContainsString('max-age=0', $cacheControl);
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertSame('no-cache', $response->headers->get('Pragma'));
        $this->assertSame('0', $response->headers->get('Expires'));
    }
}
