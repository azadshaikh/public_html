<?php

namespace App\Models\Contracts;

use App\Models\Monitor;
use Illuminate\Database\Eloquent\Builder;

/**
 * @mixin Monitor
 */
interface MonitorContract
{
    /**
     * @return Builder<Monitor>
     */
    public function newQuery();

    /**
     * @return string
     */
    public function getTable();
}
