<?php

namespace Modules\Cms\Http\Controllers;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class CmsDashboardController extends Controller
{
    /**
     * Display the sample CMS dashboard.
     */
    public function __invoke(): Response
    {
        return Inertia::render('cms/index', [
            'module' => [
                'name' => config('cms.name'),
                'slug' => config('cms.slug'),
                'version' => config('cms.version'),
                'description' => config('cms.description'),
                'features' => config('cms.features', []),
                'navigation' => config('cms.navigation', []),
            ],
        ]);
    }
}
