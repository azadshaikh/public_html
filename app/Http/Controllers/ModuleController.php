<?php

namespace App\Http\Controllers;

use App\Http\Requests\Modules\UpdateModulesRequest;
use App\Modules\ModuleManager;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ModuleController extends Controller
{
    /**
     * Display the module management page.
     */
    public function index(): Response
    {
        $moduleManager = app(ModuleManager::class);

        return Inertia::render('modules/index', [
            'managedModules' => $moduleManager->managementData()->all(),
            'status' => session('status'),
            'error' => session('error'),
        ]);
    }

    /**
     * Update module enabled statuses.
     */
    public function update(UpdateModulesRequest $request): RedirectResponse
    {
        app(ModuleManager::class)->writeStatuses($request->validated('modules'));

        return to_route('modules.index')->with('status', 'Module settings updated.');
    }
}
