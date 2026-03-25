<?php

namespace App\Jobs;

use App\Traits\IsMonitored;
use Spatie\MediaLibrary\Conversions\Jobs\PerformConversionsJob;

class PerformMediaConversionsJob extends PerformConversionsJob
{
    use IsMonitored;
}
