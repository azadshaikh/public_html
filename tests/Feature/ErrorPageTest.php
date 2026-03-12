<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ErrorPageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->prefix('/_test/errors')->group(function (): void {
            Route::get('/401', fn () => abort(401))->name('test.errors.401');
            Route::get('/402', fn () => abort(402, 'Billing access is required.'))->name('test.errors.402');
            Route::get('/403', fn () => abort(403, 'You are not allowed to access this area.'))->name('test.errors.403');
            Route::get('/419', fn () => abort(419))->name('test.errors.419');
            Route::get('/429', fn () => abort(429))->name('test.errors.429');
            Route::get('/500', fn () => abort(500))->name('test.errors.500');
            Route::get('/503', fn () => abort(503))->name('test.errors.503');
            Route::get('/418', fn () => abort(418))->name('test.errors.418');
        });
    }

    /**
     * @return array<string, array{0: string, 1: int, 2: string, 3: string|null}>
     */
    public static function handledErrorStatusProvider(): array
    {
        return [
            '401 unauthorized' => ['/_test/errors/401', 401, 'errors/401', null],
            '402 payment required' => ['/_test/errors/402', 402, 'errors/402', 'Billing access is required.'],
            '403 forbidden' => ['/_test/errors/403', 403, 'errors/403', 'You are not allowed to access this area.'],
            '419 page expired' => ['/_test/errors/419', 419, 'errors/419', null],
            '429 throttled' => ['/_test/errors/429', 429, 'errors/429', null],
            '500 server error' => ['/_test/errors/500', 500, 'errors/500', null],
            '503 unavailable' => ['/_test/errors/503', 503, 'errors/503', null],
        ];
    }

    #[DataProvider('handledErrorStatusProvider')]
    public function test_supported_error_statuses_render_inertia_pages(
        string $uri,
        int $status,
        string $component,
        ?string $message,
    ): void {
        $this->get($uri)
            ->assertStatus($status)
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component($component)
                ->where('status', $status)
                ->where('message', $message)
            );
    }

    public function test_missing_routes_render_the_inertia_not_found_page(): void
    {
        $this->get('/definitely-missing-page')
            ->assertNotFound()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('errors/404')
                ->where('status', 404)
                ->where('message', null)
            );
    }

    public function test_unhandled_status_codes_fall_back_to_laravel_default_rendering(): void
    {
        $this->get('/_test/errors/418')
            ->assertStatus(418)
            ->assertHeaderMissing('X-Inertia');
    }

    public function test_json_requests_keep_the_default_exception_response(): void
    {
        $this->getJson('/_test/errors/403')
            ->assertForbidden()
            ->assertHeaderMissing('X-Inertia')
            ->assertJsonStructure(['message']);
    }
}
