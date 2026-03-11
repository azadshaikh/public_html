<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\ModuleRuntimeServiceProvider;

return [
    AppServiceProvider::class,
    ModuleRuntimeServiceProvider::class,
    FortifyServiceProvider::class,
];
