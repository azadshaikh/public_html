<?php

namespace Modules\Cms\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ModuleManager;
use Inertia\Inertia;
use Inertia\Response;

class CmsDashboardController extends Controller
{
    /**
     * Display the sample CMS dashboard.
     */
    public function __invoke(ModuleManager $moduleManager): Response
    {
        $module = $moduleManager->findOrFail('cms');

        return Inertia::render('cms/index', [
            'module' => [
                'name' => $module->name,
                'slug' => $module->slug,
                'version' => $module->version,
                'description' => $module->description,
                'features' => config('cms.features', []),
                'navigation' => config('cms.navigation', []),
            ],
        ]);
    }
}
