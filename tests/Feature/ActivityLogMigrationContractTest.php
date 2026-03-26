<?php

namespace Tests\Feature;

use Tests\TestCase;

class ActivityLogMigrationContractTest extends TestCase
{
    public function test_activity_log_consolidated_migration_includes_v5_columns_without_follow_up_patches(): void
    {
        $migrationPath = base_path('database/migrations/1988_04_13_000005_create_activity_log_table_consolidated.php');
        $contents = file_get_contents($migrationPath);

        $this->assertNotFalse($contents, 'Failed to read consolidated activity log migration.');
        $this->assertStringContainsString("\$table->json('attribute_changes')->nullable();", $contents);
        $this->assertStringContainsString("\$table->json('properties')->nullable();", $contents);
        $this->assertStringContainsString("\$table->uuid('batch_uuid')->nullable();", $contents);

        $this->assertFileDoesNotExist(
            base_path('database/migrations/2026_03_25_161050_add_activitylog_v5_columns_to_activity_log_table.php')
        );
        $this->assertFileDoesNotExist(
            base_path('database/migrations/2026_03_26_110114_restore_batch_uuid_to_activity_log_table.php')
        );
    }
}
