<?php

namespace App\Services\LaravelTools;

use Illuminate\Support\Facades\Route;

class RouteService
{
    /**
     * Get all routes with filtering
     */
    public function getRoutes(string $search = '', string $method = 'all'): array
    {
        $routes = collect(Route::getRoutes())->map(fn ($route): array => [
            'methods' => $route->methods(),
            'uri' => $route->uri(),
            'name' => $route->getName(),
            'action' => $route->getActionName(),
            'middleware' => $route->middleware(),
        ]);

        // Filter by method
        if ($method !== 'all') {
            $routes = $routes->filter(fn (array $route): bool => in_array(strtoupper($method), $route['methods']));
        }

        // Filter by search
        if ($search !== '' && $search !== '0') {
            $routes = $routes->filter(fn (array $route): bool => str_contains(strtolower((string) $route['uri']), strtolower($search)) ||
                   str_contains(strtolower($route['name'] ?? ''), strtolower($search)) ||
                   str_contains(strtolower((string) $route['action']), strtolower($search)));
        }

        return [
            'routes' => $routes->values()->all(),
            'total' => $routes->count(),
        ];
    }
}
