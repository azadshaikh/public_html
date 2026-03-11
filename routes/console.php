<?php

use App\Console\ProductionTestCommandGuard;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('test:guard', function (): int {
    ProductionTestCommandGuard::ensureSafe(app()->isProduction(), 'test');

    return self::SUCCESS;
})->purpose('Prevent tests from running in production');
