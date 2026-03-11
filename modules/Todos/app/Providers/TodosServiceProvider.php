<?php

namespace Modules\Todos\Providers;

use App\Modules\Support\ModuleServiceProvider;

class TodosServiceProvider extends ModuleServiceProvider
{
    protected function moduleSlug(): string
    {
        return 'todos';
    }
}
