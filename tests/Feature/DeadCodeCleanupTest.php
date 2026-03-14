<?php

namespace Tests\Feature;

use App\Http\Controllers\MediaController;
use App\Http\Controllers\NotesController;
use App\Http\Controllers\SitemapController;
use Illuminate\Routing\Route;
use Tests\TestCase;

class DeadCodeCleanupTest extends TestCase
{
    public function test_legacy_sitemap_controller_is_not_registered(): void
    {
        $actions = collect(app('router')->getRoutes()->getRoutes())
            ->map(static fn (Route $route): string => $route->getActionName());

        $this->assertFalse($actions->contains(SitemapController::class.'@index'));
    }

    public function test_legacy_media_index_route_is_not_registered(): void
    {
        $actions = collect(app('router')->getRoutes()->getRoutes())
            ->map(static fn (Route $route): string => $route->getActionName());

        $this->assertFalse($actions->contains(MediaController::class.'@index'));
    }

    public function test_notes_edit_route_is_not_registered_but_json_routes_remain(): void
    {
        $routes = app('router')->getRoutes();

        $this->assertNull($routes->getByName('app.notes.edit'));
        $this->assertNotNull($routes->getByName('app.notes.store'));
        $this->assertNotNull($routes->getByName('app.notes.update'));
        $this->assertNotNull($routes->getByName('app.notes.destroy'));
        $this->assertNotNull($routes->getByName('app.notes.toggle-pin'));

        $actions = collect($routes->getRoutes())
            ->map(static fn (Route $route): string => $route->getActionName());

        $this->assertFalse($actions->contains(NotesController::class.'@edit'));
    }
}
