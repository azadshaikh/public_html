<?php

namespace Modules\ChatBot\Providers;

use App\Modules\Support\ModuleServiceProvider;

class ChatBotServiceProvider extends ModuleServiceProvider
{
    protected function moduleSlug(): string
    {
        return 'chatbot';
    }
}
