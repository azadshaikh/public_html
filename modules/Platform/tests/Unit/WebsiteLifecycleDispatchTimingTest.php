<?php

namespace Modules\Platform\Tests\Unit;

use Tests\TestCase;

class WebsiteLifecycleDispatchTimingTest extends TestCase
{
    public function test_website_lifecycle_service_trash_and_restore_dispatch_after_response(): void
    {
        $path = base_path('modules/Platform/app/Services/WebsiteLifecycleService.php');
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, 'Failed to read modules/Platform/app/Services/WebsiteLifecycleService.php');
        $this->assertStringContainsString("dispatch(new WebsiteTrash(\$website->id))\n                ->onQueue('default')\n                ->afterResponse();", $contents);
        $this->assertStringContainsString("dispatch(new WebsiteUntrash(\$website->id))\n            ->onQueue('default')\n            ->afterResponse();", $contents);
        $this->assertStringContainsString("SendAgencyWebhook::dispatchForWebsiteAfterResponse(\$website, 'website.deleted', [", $contents);
        $this->assertStringContainsString("SendAgencyWebhook::dispatchForWebsiteAfterResponse(\$website, 'website.restored');", $contents);
    }

    public function test_bulk_website_trash_and_restore_dispatch_after_response(): void
    {
        $path = base_path('modules/Platform/app/Services/WebsiteService.php');
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, 'Failed to read modules/Platform/app/Services/WebsiteService.php');
        $this->assertStringContainsString("dispatch(new WebsiteTrash(\$website->id))\n                    ->onQueue('default')\n                    ->afterResponse();", $contents);
        $this->assertStringContainsString("dispatch(new WebsiteUntrash(\$website->id))\n                    ->onQueue('default')\n                    ->afterResponse();", $contents);
        $this->assertStringContainsString("SendAgencyWebhook::dispatchForWebsiteAfterResponse(\$website, 'website.deleted', [", $contents);
        $this->assertStringContainsString("SendAgencyWebhook::dispatchForWebsiteAfterResponse(\$website, 'website.restored');", $contents);
    }

    public function test_send_agency_webhook_supports_after_response_dispatch(): void
    {
        $path = base_path('modules/Platform/app/Jobs/SendAgencyWebhook.php');
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, 'Failed to read modules/Platform/app/Jobs/SendAgencyWebhook.php');
        $this->assertStringContainsString('public static function dispatchForWebsiteAfterResponse(Website $website, string $event, array $extraPayload = []): void', $contents);
        $this->assertStringContainsString("dispatch(new self(\$website->agency_id, \$event, \$payload))\n                ->onQueue('default')\n                ->afterResponse();", $contents);
    }
}
