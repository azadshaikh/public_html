<?php

namespace Modules\Todos\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ModuleManager;
use Inertia\Inertia;
use Inertia\Response;

class TodosDashboardController extends Controller
{
    /**
     * Display the sample todos dashboard.
     */
    public function __invoke(ModuleManager $moduleManager): Response
    {
        $module = $moduleManager->findOrFail('todos');

        return Inertia::render('todos/index', [
            'module' => [
                'name' => $module->name,
                'slug' => $module->slug,
                'version' => $module->version,
                'description' => $module->description,
                'items' => config('todos.items', []),
            ],
        ]);
    }
}
