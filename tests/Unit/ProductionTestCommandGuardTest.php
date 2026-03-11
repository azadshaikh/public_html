<?php

namespace Tests\Unit;

use App\Console\ProductionTestCommandGuard;
use Symfony\Component\Console\Exception\RuntimeException;
use Tests\TestCase;

class ProductionTestCommandGuardTest extends TestCase
{
    public function test_it_only_blocks_the_test_command_in_production(): void
    {
        $this->assertTrue(ProductionTestCommandGuard::shouldBlock(true, 'test'));
        $this->assertFalse(ProductionTestCommandGuard::shouldBlock(false, 'test'));
        $this->assertFalse(ProductionTestCommandGuard::shouldBlock(true, 'migrate'));
    }

    public function test_it_throws_a_clear_error_for_production_test_runs(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('It is not advisable to run tests in production');

        ProductionTestCommandGuard::ensureSafe(true, 'test');
    }
}
