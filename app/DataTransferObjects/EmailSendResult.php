<?php

namespace App\DataTransferObjects;

class EmailSendResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $error = null,
        public readonly array $context = []
    ) {}

    public static function success(array $context = []): self
    {
        return new self(true, null, $context);
    }

    public static function failure(?string $error, array $context = []): self
    {
        return new self(false, $error, $context);
    }

    public function failed(): bool
    {
        return ! $this->success;
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'error' => $this->error,
            'context' => $this->context,
        ];
    }
}
