<?php

namespace Modules\Platform\Tests\Unit;

use App\Models\User;
use App\Services\EmailService;
use Mockery;
use Mockery\Expectation;
use Mockery\MockInterface;
use Modules\Platform\Console\SendProvisioningEmailsCommand;
use Modules\Platform\Models\Server;
use Modules\Platform\Models\Website;
use Modules\Platform\Services\WebsiteAccountService;
use Tests\TestCase;

class WebsiteProvisioningOptionsTest extends TestCase
{
    public function test_assign_site_identifier_uses_custom_uid(): void
    {
        /** @var Website&MockInterface $website */
        $website = Mockery::mock(Website::class)->makePartial();
        $website->id = 123;
        $website->metadata = [];
        /** @var Expectation $saveExpectation */
        $saveExpectation = $website->shouldReceive('save');
        $saveExpectation->andReturnTrue();

        $website->assignSiteIdentifier(1, 'custom_user-1');

        $this->assertSame('custom_user-1', $website->uid);
        $this->assertNotEmpty($website->secret_key);
    }

    public function test_generated_site_id_format_is_stable(): void
    {
        $this->assertSame('WS00009', Website::generateSiteIdFromRecordId(9));
        $this->assertSame('WS00929', Website::generateSiteIdFromRecordId(929));
        $this->assertSame('AS0009', Website::generateSiteIdFromRecordId(9, 'as', 4));
    }

    public function test_website_username_uses_server_username_override_without_changing_site_id(): void
    {
        $website = new Website;
        $website->uid = 'WS00009';
        $website->metadata = [
            'provisioning' => [
                'server_username' => 'ws0000929',
            ],
        ];

        $this->assertSame('WS00009', $website->site_id);
        $this->assertSame('ws0000929', $website->website_username);
    }

    public function test_skip_ssl_issue_flag_is_stored_in_provisioning_metadata(): void
    {
        $website = new Website;
        $website->metadata = [];

        $website->skip_ssl_issue = true;

        $this->assertTrue($website->skip_ssl_issue);
        $this->assertTrue((bool) data_get($website->metadata, 'provisioning.skip_ssl_issue'));
    }

    public function test_website_account_service_does_not_overwrite_existing_secrets(): void
    {
        $owner = new User;
        $owner->first_name = 'Owner';
        $owner->last_name = 'Test';
        $owner->email = 'owner@example.com';

        $server = new Server;
        $server->username = 'server_admin';

        /** @var Website&MockInterface $website */
        $website = Mockery::mock(Website::class)->makePartial();
        $website->uid = 'WS00001';
        $website->setRelation('owner', $owner);
        $website->setRelation('server', $server);

        /** @var Expectation $superUserSecretExpectation */
        $superUserSecretExpectation = $website->shouldReceive('getSecret');
        $superUserSecretExpectation->with('super_user_password')->andReturn([
            'username' => 'su@astero.in',
            'value' => 'existing-super',
        ]);
        /** @var Expectation $websiteAdminSecretExpectation */
        $websiteAdminSecretExpectation = $website->shouldReceive('getSecret');
        $websiteAdminSecretExpectation->with('website_admin_password')->andReturn([
            'username' => 'owner@example.com',
            'value' => 'existing-owner',
        ]);
        /** @var Expectation $serverSecretExpectation */
        $serverSecretExpectation = $website->shouldReceive('getSecret');
        $serverSecretExpectation->with('server_password')->andReturn([
            'username' => 'WS00001',
            'value' => 'existing-server',
        ]);

        $website->shouldNotReceive('setSecret');

        $service = new WebsiteAccountService;
        $accounts = $service->createAccountsForWebsite($website);

        $byKey = collect($accounts)->keyBy('secret_key');

        $this->assertSame('existing-super', $byKey['super_user_password']['password']);
        $this->assertSame('existing-owner', $byKey['website_admin_password']['password']);
        $this->assertSame('existing-server', $byKey['server_password']['password']);
    }

    public function test_send_provisioning_emails_skips_owner_email_when_flag_is_set(): void
    {
        /** @var EmailService&MockInterface $emailService */
        $emailService = Mockery::mock(EmailService::class);
        $this->app->instance(EmailService::class, $emailService);

        /** @var Expectation $sendEmailExpectation */
        $sendEmailExpectation = $emailService->shouldReceive('sendEmail');
        $sendEmailExpectation
            ->once()
            ->withArgs(function (string $to, string $template, array $variables): bool {
                $this->assertSame('su@astero.in', $to);
                $this->assertSame('Website Setup Completion Email (send to admin)', $template);
                $this->assertSame('demo.test', $variables['domain'] ?? null);

                return true;
            })
            ->andReturnTrue();

        $owner = new User;
        $owner->first_name = 'Owner';
        $owner->last_name = 'Test';
        $owner->email = 'owner@example.com';

        /** @var Website&MockInterface $website */
        $website = Mockery::mock(Website::class)->makePartial();
        $website->domain = 'demo.test';
        $website->metadata = ['skip_email' => true, 'admin_slug' => 'app'];
        $website->setRelation('owner', $owner);

        /** @var Expectation $websiteAdminPasswordExpectation */
        $websiteAdminPasswordExpectation = $website->shouldReceive('getSecret');
        $websiteAdminPasswordExpectation->with('website_admin_password')->andReturn([
            'username' => 'owner@example.com',
            'value' => 'owner-pass',
        ]);
        /** @var Expectation $superUserPasswordExpectation */
        $superUserPasswordExpectation = $website->shouldReceive('getSecret');
        $superUserPasswordExpectation->with('super_user_password')->andReturn([
            'username' => 'su@astero.in',
            'value' => 'su-pass',
        ]);

        /** @var Expectation $updateStepExpectation */
        $updateStepExpectation = $website->shouldReceive('updateProvisioningStep');
        $updateStepExpectation
            ->once()
            ->withArgs(function (string $key, string $message, string $status): bool {
                $this->assertSame('send_emails', $key);
                $this->assertSame('done', $status);
                $this->assertStringContainsString('1 provisioning email', $message);

                return true;
            });

        $command = new class extends SendProvisioningEmailsCommand
        {
            public function runHandleCommand(Website $website): void
            {
                $this->handleCommand($website);
            }

            public function info($string, $verbosity = null): void {}

            public function warn($string, $verbosity = null): void {}
        };

        $command->runHandleCommand($website);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
