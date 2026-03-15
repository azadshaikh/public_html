<?php

declare(strict_types=1);

namespace Tests\Feature\Masters;

use App\DataTransferObjects\EmailSendResult;
use App\Enums\Status;
use App\Models\EmailProvider;
use App\Models\EmailTemplate;
use App\Models\Role;
use App\Models\User;
use App\Services\EmailService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class EmailManagementActionsTest extends TestCase
{
    use RefreshDatabase;

    private User $superUser;

    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->superUser = User::factory()->create([
            'status' => Status::ACTIVE,
            'first_name' => 'Mail',
            'last_name' => 'Super',
            'email_verified_at' => now(),
        ]);
        $this->superUser->assignRole(Role::findByName('super_user', 'web'));

        $this->administrator = User::factory()->create([
            'status' => Status::ACTIVE,
            'first_name' => 'Mail',
            'last_name' => 'Admin',
            'email_verified_at' => now(),
        ]);
        $this->administrator->assignRole(Role::findByName('administrator', 'web'));
    }

    public function test_super_users_can_create_an_email_provider(): void
    {
        $this->actingAs($this->superUser)
            ->post(route('app.masters.email.providers.store'), [
                'name' => 'Primary SMTP',
                'description' => 'Transactional mail provider',
                'sender_name' => 'Operations',
                'sender_email' => 'ops@example.com',
                'smtp_host' => 'smtp.example.com',
                'smtp_user' => 'smtp-user',
                'smtp_password' => 'secret',
                'smtp_port' => '587',
                'smtp_encryption' => 'tls',
                'reply_to' => 'reply@example.com',
                'bcc' => 'audit@example.com',
                'signature' => 'Regards',
                'status' => 'active',
                'order' => 1,
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('email_providers', [
            'name' => 'Primary SMTP',
            'sender_email' => 'ops@example.com',
            'smtp_host' => 'smtp.example.com',
        ]);
    }

    public function test_super_users_can_update_an_email_template(): void
    {
        $provider = EmailProvider::query()->create([
            'name' => 'Provider',
            'sender_name' => 'Sender',
            'sender_email' => 'sender@example.com',
            'smtp_host' => 'smtp.example.com',
            'smtp_user' => 'smtp-user',
            'smtp_password' => 'secret',
            'smtp_port' => '587',
            'smtp_encryption' => 'tls',
            'status' => Status::ACTIVE,
        ]);

        $template = EmailTemplate::query()->create([
            'name' => 'Welcome',
            'subject' => 'Welcome',
            'message' => 'Hello there',
            'send_to' => 'one@example.com',
            'provider_id' => $provider->id,
            'is_raw' => false,
            'status' => Status::ACTIVE,
        ]);

        $richMessage = <<<'HTML'
<h2>Updated</h2>
<p><strong>Formatted</strong> body with an <a href="https://example.com" target="_blank" rel="noopener nofollow">external link</a>.</p>
<table><thead><tr><th scope="col">Plan</th><th scope="col">Status</th></tr></thead><tbody><tr><td>Launch</td><td>Ready</td></tr></tbody></table>
<figure><img src="https://cdn.example.com/banner.jpg" alt="Launch banner" loading="lazy" /></figure>
HTML;

        $this->actingAs($this->superUser)
            ->put(route('app.masters.email.templates.update', $template), [
                'name' => 'Updated Welcome',
                'subject' => 'Updated Subject',
                'message' => $richMessage,
                'send_to' => 'one@example.com,two@example.com',
                'provider_id' => $provider->id,
                'is_raw' => true,
                'status' => 'inactive',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('email_templates', [
            'id' => $template->id,
            'name' => 'Updated Welcome',
            'subject' => 'Updated Subject',
            'message' => $richMessage,
            'send_to' => 'one@example.com,two@example.com',
            'is_raw' => true,
        ]);
    }

    public function test_super_users_can_send_a_test_email_for_a_template(): void
    {
        $provider = EmailProvider::query()->create([
            'name' => 'Provider',
            'sender_name' => 'Sender',
            'sender_email' => 'sender@example.com',
            'smtp_host' => 'smtp.example.com',
            'smtp_user' => 'smtp-user',
            'smtp_password' => 'secret',
            'smtp_port' => '587',
            'smtp_encryption' => 'tls',
            'status' => Status::ACTIVE,
        ]);

        $template = EmailTemplate::query()->create([
            'name' => 'Alert',
            'subject' => 'Alert',
            'message' => 'An alert',
            'send_to' => '',
            'provider_id' => $provider->id,
            'is_raw' => false,
            'status' => Status::ACTIVE,
        ]);

        $this->mock(EmailService::class, function (MockInterface $mock) use ($template, $provider): void {
            $mock->shouldReceive('sendTemplate')
                ->once()
                ->withArgs(function (
                    EmailTemplate $resolvedTemplate,
                    string $recipient,
                    array $data,
                    ?EmailProvider $resolvedProvider
                ) use ($template, $provider): bool {
                    return $resolvedTemplate->is($template)
                        && $recipient === 'test@example.com'
                        && $data === []
                        && $resolvedProvider?->is($provider) === true;
                })
                ->andReturn(EmailSendResult::success([
                    'transport' => 'smtp',
                ]));
        });

        $this->actingAs($this->superUser)
            ->postJson(route('app.masters.email.templates.send-test', $template), [
                'recipient' => 'test@example.com',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', 'Test email sent successfully.');
    }

    public function test_non_super_users_cannot_perform_email_management_actions(): void
    {
        $this->actingAs($this->administrator)
            ->post(route('app.masters.email.providers.store'), [
                'name' => 'Blocked',
                'sender_name' => 'Blocked',
                'sender_email' => 'blocked@example.com',
                'smtp_host' => 'smtp.example.com',
                'smtp_user' => 'smtp-user',
                'smtp_password' => 'secret',
                'smtp_port' => '587',
                'smtp_encryption' => 'tls',
            ])
            ->assertForbidden();
    }
}
