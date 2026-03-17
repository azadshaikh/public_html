<?php

namespace Modules\Platform\Tests\Unit;

use Tests\TestCase;

class WebsiteDeleteCleanupFlowTest extends TestCase
{
    public function test_website_delete_job_performs_queue_cleanup_before_hestia_user_deletion(): void
    {
        $path = base_path('modules/Platform/app/Jobs/WebsiteDelete.php');
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, 'Failed to read modules/Platform/app/Jobs/WebsiteDelete.php');
        $this->assertStringContainsString('$this->removeQueueWorkerConfiguration($website);', $contents);
        $this->assertStringContainsString('$this->deleteFromHestiaServer($website);', $contents);
        $this->assertStringContainsString("'a-manage-queue-worker'", $contents);
        $this->assertStringContainsString("Artisan::call('platform:hestia:delete-website'", $contents);

        $queuePosition = strpos($contents, '$this->removeQueueWorkerConfiguration($website);');
        $deletePosition = strpos($contents, '$this->deleteFromHestiaServer($website);');

        $this->assertIsInt($queuePosition);
        $this->assertIsInt($deletePosition);
        $this->assertLessThan($deletePosition, $queuePosition);
    }

    public function test_website_remove_from_server_job_removes_queue_worker_before_hestia_delete(): void
    {
        $path = base_path('modules/Platform/app/Jobs/WebsiteRemoveFromServer.php');
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, 'Failed to read modules/Platform/app/Jobs/WebsiteRemoveFromServer.php');
        $this->assertStringContainsString('$this->removeQueueWorkerConfiguration($website);', $contents);
        $this->assertStringContainsString("'a-manage-queue-worker'", $contents);
        $this->assertStringContainsString("Artisan::call('platform:hestia:delete-website'", $contents);

        $queuePosition = strpos($contents, '$this->removeQueueWorkerConfiguration($website);');
        $deletePosition = strpos($contents, "Artisan::call('platform:hestia:delete-website'");

        $this->assertIsInt($queuePosition);
        $this->assertIsInt($deletePosition);
        $this->assertLessThan($deletePosition, $queuePosition);
    }

    public function test_expired_websites_command_dispatches_website_delete_with_website_id(): void
    {
        $path = base_path('modules/Platform/app/Console/HestiaDeleteExpiredWebsitesCommand.php');
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, 'Failed to read modules/Platform/app/Console/HestiaDeleteExpiredWebsitesCommand.php');
        $this->assertStringContainsString('dispatch(new WebsiteDelete($website->id));', $contents);
        $this->assertStringNotContainsString('WebsiteDelete::dispatch($website);', $contents);
    }

    public function test_website_delete_job_uses_db_transaction_only_for_local_deletion_operations(): void
    {
        $path = base_path('modules/Platform/app/Jobs/WebsiteDelete.php');
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, 'Failed to read modules/Platform/app/Jobs/WebsiteDelete.php');
        $this->assertStringNotContainsString('DB::beginTransaction();', $contents);
        $this->assertStringNotContainsString('DB::rollBack();', $contents);
        $this->assertStringContainsString('DB::transaction(function () use ($website): void {', $contents);
    }

    public function test_bulk_force_delete_does_not_set_deleted_status_before_cleanup_job(): void
    {
        $path = base_path('modules/Platform/app/Services/WebsiteService.php');
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, 'Failed to read modules/Platform/app/Services/WebsiteService.php');
        $this->assertStringContainsString("['delete', 'restore', 'force_delete', 'suspend', 'unsuspend', 'remove_from_server']", $contents);
        $this->assertStringContainsString('dispatch(new WebsiteDelete($website->id));', $contents);
        $this->assertStringContainsString("'force_delete' => \$affected.' website(s) scheduled for deletion'", $contents);
        $this->assertStringNotContainsString('$website->status = WebsiteStatus::Deleted;', $contents);
    }
}
