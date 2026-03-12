<?php

namespace App\Http\Controllers\QueueMonitor\Payloads;

final class Metrics
{
    /**
     * @var Metric[]
     */
    public array $metrics = [];

    /**
     * @return Metric[]
     */
    public function all(): array
    {
        return $this->metrics;
    }

    public function push(Metric $metric): self
    {
        $this->metrics[] = $metric;

        return $this;
    }
}
