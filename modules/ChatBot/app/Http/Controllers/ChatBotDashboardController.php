<?php

namespace Modules\ChatBot\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ModuleManager;
use Inertia\Inertia;
use Inertia\Response;

class ChatBotDashboardController extends Controller
{
    /**
     * Display the sample chatbot dashboard.
     */
    public function __invoke(ModuleManager $moduleManager): Response
    {
        $module = $moduleManager->findOrFail('chatbot');

        return Inertia::render('chatbot/index', [
            'module' => [
                'name' => $module->name,
                'slug' => $module->slug,
                'version' => $module->version,
                'description' => $module->description,
                'highlights' => config('chatbot.highlights', []),
            ],
        ]);
    }
}
