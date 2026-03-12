<?php

namespace App\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class ViewData
{
    public function __construct(
        public string $key,
        public mixed $value = null,
        public ?string $from = null,
        public array $views = []
    ) {}
}
