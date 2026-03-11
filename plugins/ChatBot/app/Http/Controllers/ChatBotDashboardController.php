<?php

namespace Plugins\ChatBot\Http\Controllers;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class ChatBotDashboardController extends Controller
{
    /**
     * Display the sample chatbot dashboard.
     */
    public function __invoke(): Response
    {
        return Inertia::render('chatbot/index', [
            'module' => [
                'name' => config('chatbot.name'),
                'slug' => config('chatbot.slug'),
                'version' => config('chatbot.version'),
                'description' => config('chatbot.description'),
                'highlights' => config('chatbot.highlights', []),
            ],
        ]);
    }
}
