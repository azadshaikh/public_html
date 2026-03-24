<?php

declare(strict_types=1);

namespace Modules\ChatBot\Providers;

use App\Modules\Support\ModuleServiceProvider;
use Modules\ChatBot\Services\ChatBotService;
use Modules\ChatBot\Services\ToolPermissionService;

class ChatBotServiceProvider extends ModuleServiceProvider
{
    protected function moduleSlug(): string
    {
        return 'chatbot';
    }

    public function register(): void
    {
        parent::register();

        $this->app->register(EventServiceProvider::class);
        $this->app->singleton(ChatBotService::class);
        $this->app->singleton(ToolPermissionService::class);
    }
}
