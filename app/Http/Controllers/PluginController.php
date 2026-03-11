<?php

namespace App\Http\Controllers;

use App\Http\Requests\Plugins\UpdatePluginsRequest;
use App\Plugins\PluginManager;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PluginController extends Controller
{
    /**
     * Display the plugin management page.
     */
    public function index(): Response
    {
        $pluginManager = app(PluginManager::class);

        return Inertia::render('plugins/index', [
            'managedPlugins' => $pluginManager->managementData()->all(),
            'status' => session('status'),
        ]);
    }

    /**
     * Update plugin enabled statuses.
     */
    public function update(UpdatePluginsRequest $request): RedirectResponse
    {
        app(PluginManager::class)->writeStatuses($request->validated('plugins'));

        return to_route('plugins.index')->with('status', 'Plugin settings updated.');
    }
}
