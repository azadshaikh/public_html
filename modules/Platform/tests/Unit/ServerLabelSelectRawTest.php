<?php

namespace Modules\Platform\Tests\Unit;

use Modules\Platform\Models\Server;
use Tests\TestCase;

class ServerLabelSelectRawTest extends TestCase
{
    public function test_sqlite_driver_uses_concatenation_operator_expression(): void
    {
        $this->assertSame("name || ' (' || ip || ')'", Server::getServerLabelSelectRaw('sqlite'));
    }

    public function test_pgsql_driver_uses_single_quoted_concat_expression(): void
    {
        $this->assertSame("CONCAT(name, ' (', ip, ')')", Server::getServerLabelSelectRaw('pgsql'));
    }
}
