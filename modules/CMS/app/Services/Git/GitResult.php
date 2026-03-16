<?php

namespace Modules\CMS\Services\Git;

class GitResult
{
    public function __construct(
        public bool $success,
        public string $output = '',
        public ?string $error = null,
        public ?array $data = null,
    ) {}

    public static function success(string $output = '', ?array $data = null): self
    {
        return new self(true, $output, null, $data);
    }

    public static function failure(string $error, string $output = '', ?array $data = null): self
    {
        return new self(false, $output, $error, $data);
    }
}
