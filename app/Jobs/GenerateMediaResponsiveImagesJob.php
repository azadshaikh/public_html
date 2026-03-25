<?php

namespace App\Jobs;

use App\Traits\IsMonitored;
use Spatie\MediaLibrary\ResponsiveImages\Jobs\GenerateResponsiveImagesJob;

class GenerateMediaResponsiveImagesJob extends GenerateResponsiveImagesJob
{
    use IsMonitored;
}
