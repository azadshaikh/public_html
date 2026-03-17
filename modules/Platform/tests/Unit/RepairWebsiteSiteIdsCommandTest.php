<?php

namespace Modules\Platform\Tests\Unit;

use Modules\Platform\Console\RepairWebsiteSiteIdsCommand;
use Tests\TestCase;

class RepairWebsiteSiteIdsCommandTest extends TestCase
{
    public function test_build_repair_plan_for_mutated_generated_uid(): void
    {
        $plan = RepairWebsiteSiteIdsCommand::buildRepairPlan(
            recordId: 9,
            currentUid: 'ws0000929',
            uidSource: 'generated',
            existingServerUsername: null
        );

        $this->assertNotNull($plan);
        $this->assertSame('ws0000929', $plan['previous_uid']);
        $this->assertSame('WS00009', $plan['expected_uid']);
        $this->assertSame('ws0000929', $plan['server_username']);
    }

    public function test_build_repair_plan_skips_custom_uid_source(): void
    {
        $plan = RepairWebsiteSiteIdsCommand::buildRepairPlan(
            recordId: 9,
            currentUid: 'custom-user',
            uidSource: 'custom',
            existingServerUsername: null
        );

        $this->assertNull($plan);
    }

    public function test_build_repair_plan_skips_when_uid_is_already_expected(): void
    {
        $plan = RepairWebsiteSiteIdsCommand::buildRepairPlan(
            recordId: 9,
            currentUid: 'WS00009',
            uidSource: 'generated',
            existingServerUsername: 'ws0000929'
        );

        $this->assertNull($plan);
    }

    public function test_build_repair_plan_uses_saved_uid_format_metadata(): void
    {
        $plan = RepairWebsiteSiteIdsCommand::buildRepairPlan(
            recordId: 9,
            currentUid: 'as0099',
            uidSource: 'generated',
            existingServerUsername: null,
            uidPrefix: 'AS',
            uidZeroPadding: 4,
        );

        $this->assertNotNull($plan);
        $this->assertSame('AS0009', $plan['expected_uid']);
    }
}
