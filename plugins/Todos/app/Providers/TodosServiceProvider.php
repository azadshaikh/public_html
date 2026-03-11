<?php

namespace Plugins\Todos\Providers;

use App\Plugins\Support\PluginServiceProvider;

class TodosServiceProvider extends PluginServiceProvider
{
    protected function pluginSlug(): string
    {
        return 'todos';
    }
}
