<?php

use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\EventServiceProvider;
use App\Providers\GlobalWarningServiceProvider;
use App\Providers\HelperServiceProvider;
use App\Providers\ModuleRuntimeServiceProvider;
use App\Providers\QueueMonitorProvider;

return [
    AppServiceProvider::class,
    QueueMonitorProvider::class,
    AuthServiceProvider::class,
    EventServiceProvider::class,
    GlobalWarningServiceProvider::class,
    HelperServiceProvider::class,
    ModuleRuntimeServiceProvider::class,
];
