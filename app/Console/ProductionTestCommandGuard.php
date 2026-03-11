<?php

namespace App\Console;

use Symfony\Component\Console\Exception\RuntimeException;

final class ProductionTestCommandGuard
{
    public const string MESSAGE = 'Running tests in production is blocked. It is not advisable to run tests in production because tests may migrate, seed, truncate, or otherwise mutate live data.';

    public static function ensureSafe(bool $isProduction, ?string $command): void
    {
        if (! self::shouldBlock($isProduction, $command)) {
            return;
        }

        throw new RuntimeException(self::MESSAGE);
    }

    public static function shouldBlock(bool $isProduction, ?string $command): bool
    {
        return $isProduction && self::normalizeCommand($command) === 'test';
    }

    private static function normalizeCommand(?string $command): string
    {
        return strtolower(trim((string) $command));
    }
}
