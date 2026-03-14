<?php

namespace App\Services\LaravelTools;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

class RouteService
{
    /**
     * Get all routes with filtering
     */
    public function getRoutes(
        string $search = '',
        string $method = 'all',
        string $sort = 'uri',
        string $direction = 'asc',
        int $perPage = 25,
    ): array {
        $routes = collect(Route::getRoutes())
            ->map(function ($route): array {
                $methods = collect($route->methods())
                    ->reject(fn (string $method): bool => $method === 'HEAD')
                    ->values()
                    ->all();

                return [
                    'methods' => $methods,
                    'method_label' => implode(', ', $methods),
                    'uri' => $route->uri(),
                    'name' => $route->getName(),
                    'action' => $route->getActionName(),
                    'middleware' => $route->middleware(),
                ];
            });

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

        $routes = $this->sortRoutes($routes, $sort, $direction);
        $paginator = $this->paginateRoutes($routes, $perPage);

        return [
            'routes' => $paginator,
            'total' => $routes->count(),
        ];
    }

    protected function sortRoutes(Collection $routes, string $sort, string $direction): Collection
    {
        $allowedSorts = ['method_label', 'uri', 'name', 'action'];
        $sortKey = in_array($sort, $allowedSorts, true) ? $sort : 'uri';
        $descending = $direction === 'desc';

        return $routes
            ->sortBy(
                fn (array $route): string => strtolower((string) ($route[$sortKey] ?? '')),
                SORT_NATURAL,
                $descending,
            )
            ->values();
    }

    protected function paginateRoutes(Collection $routes, int $perPage): LengthAwarePaginator
    {
        $page = max(1, (int) request()->integer('page', 1));
        $perPage = max(10, min($perPage, 100));

        return new LengthAwarePaginator(
            $routes->forPage($page, $perPage)->values(),
            $routes->count(),
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ],
        );
    }
}
