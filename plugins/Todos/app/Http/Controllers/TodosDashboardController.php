<?php

namespace Plugins\Todos\Http\Controllers;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class TodosDashboardController extends Controller
{
    /**
     * Display the sample todos dashboard.
     */
    public function __invoke(): Response
    {
        return Inertia::render('todos/index', [
            'module' => [
                'name' => config('todos.name'),
                'slug' => config('todos.slug'),
                'version' => config('todos.version'),
                'description' => config('todos.description'),
                'items' => config('todos.items', []),
            ],
        ]);
    }
}
