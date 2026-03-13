<?php

namespace App\Http\Controllers;

use App\Http\Requests\Modules\UpdateModulesRequest;
use App\Modules\ModuleLifecycleManager;
use App\Modules\ModuleManager;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ModuleController extends Controller
{
    /**
     * Display the module management page.
     */
    public function index(): Response
    {
        $moduleManager = resolve(ModuleManager::class);

        return Inertia::render('modules/index', [
            'managedModules' => $moduleManager->managementData()->all(),
            'indexUrl' => route('app.masters.modules.index'),
            'updateUrl' => route('app.masters.modules.update'),
            'error' => session('error'),
        ]);
    }

    /**
     * Update module enabled statuses.
     */
    public function update(UpdateModulesRequest $request): RedirectResponse
    {
        try {
            resolve(ModuleLifecycleManager::class)->syncStatuses($request->validated('modules'));
        } catch (Throwable $throwable) {
            report($throwable);

            return to_route('app.masters.modules.index')->with('error', 'Unable to update the selected modules right now.');
        }

        return to_route('app.masters.modules.index')->with('success', 'Module settings updated.');
    }
}
