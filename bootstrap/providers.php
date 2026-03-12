<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\HelperServiceProvider;
use App\Providers\ModuleRuntimeServiceProvider;

return [
    AppServiceProvider::class,
    HelperServiceProvider::class,
    ModuleRuntimeServiceProvider::class,
    FortifyServiceProvider::class,
];
