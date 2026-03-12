<?php

namespace App\Http\Controllers\QueueMonitor\Payloads;

final class Metric
{
    public function __construct(public string $title, public float $value = 0, public ?float $previousValue = null, public string $format = '%d') {}

    public function hasChanged(): bool
    {
        return $this->value !== $this->previousValue;
    }

    public function hasIncreased(): bool
    {
        return $this->value > $this->previousValue;
    }

    public function format(float $value): string
    {
        return sprintf($this->format, $value);
    }
}
