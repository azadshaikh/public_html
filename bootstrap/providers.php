<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\PluginRuntimeServiceProvider;

return [
    AppServiceProvider::class,
    PluginRuntimeServiceProvider::class,
    FortifyServiceProvider::class,
];
