<?php

namespace App\Console\Commands\Concerns;

use Carbon\Carbon;
use Carbon\CarbonInterval;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;

trait HandlesDateInputs
{
    public static function parseBeforeDate(Command $command): ?Carbon
    {
        if ($before = $command->option('before')) {
            return Date::parse($before);
        }

        if ($beforeDays = $command->option('beforeDays')) {
            return Date::now()->subDays((int) $beforeDays);
        }

        if ($interval = $command->option('beforeInterval')) {
            return Date::now()->sub(
                new CarbonInterval($interval)
            );
        }

        return null;
    }
}
