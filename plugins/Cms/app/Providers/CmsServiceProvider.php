<?php

namespace Plugins\Cms\Providers;

use App\Plugins\Support\PluginServiceProvider;

class CmsServiceProvider extends PluginServiceProvider
{
    protected function pluginSlug(): string
    {
        return 'cms';
    }
}
