<?php

use App\Console\ProductionTestCommandGuard;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Command\Command;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('test:guard', function (): int {
    ProductionTestCommandGuard::ensureSafe(app()->isProduction(), 'test');

    return Command::SUCCESS;
})->purpose('Prevent tests from running in production');
