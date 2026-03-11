<?php

namespace Modules\Cms\Providers;

use App\Modules\Support\ModuleServiceProvider;

class CmsServiceProvider extends ModuleServiceProvider
{
    protected function moduleSlug(): string
    {
        return 'cms';
    }
}
