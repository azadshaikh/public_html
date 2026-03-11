<?php

namespace Plugins\ChatBot\Providers;

use App\Plugins\Support\PluginServiceProvider;

class ChatBotServiceProvider extends PluginServiceProvider
{
    protected function pluginSlug(): string
    {
        return 'chatbot';
    }
}
