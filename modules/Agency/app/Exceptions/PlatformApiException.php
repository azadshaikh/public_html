<?php

declare(strict_types=1);

namespace Modules\Agency\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Thrown when the Platform API returns an error or is unreachable.
 */
class PlatformApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $statusCode = 0,
        public readonly array $body = [],
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    /**
     * Create from an HTTP response.
     *
     * @param  array<string, mixed>  $body
     */
    public static function fromResponse(int $statusCode, array $body): self
    {
        $message = $body['message'] ?? 'Platform API returned HTTP '.$statusCode;

        return new self($message, $statusCode, $body);
    }

    /**
     * Create for a connection failure.
     */
    public static function connectionFailed(Throwable $e): self
    {
        return new self(
            'Unable to reach Platform API: '.$e->getMessage(),
            0,
            [],
            $e
        );
    }
}
