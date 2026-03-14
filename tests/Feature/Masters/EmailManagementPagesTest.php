<?php

declare(strict_types=1);

namespace Tests\Feature\Masters;

use App\Enums\Status;
use App\Models\EmailLog;
use App\Models\EmailProvider;
use App\Models\EmailTemplate;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EmailManagementPagesTest extends TestCase
{
    use RefreshDatabase;

    private User $superUser;

    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->superUser = User::factory()->create([
            'status' => Status::ACTIVE,
            'first_name' => 'Super',
            'last_name' => 'User',
            'email_verified_at' => now(),
        ]);
        $this->superUser->assignRole(Role::findByName('super_user', 'web'));

        $this->administrator = User::factory()->create([
            'status' => Status::ACTIVE,
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email_verified_at' => now(),
        ]);
        $this->administrator->assignRole(Role::findByName('administrator', 'web'));
    }

    public function test_email_provider_pages_render_expected_props(): void
    {
        $provider = EmailProvider::query()->create([
            'name' => 'Primary SMTP',
            'description' => 'Main transactional provider',
            'sender_name' => 'Operations',
            'sender_email' => 'ops@example.com',
            'smtp_host' => 'smtp.example.com',
            'smtp_user' => 'smtp-user',
            'smtp_password' => 'secret',
            'smtp_port' => '587',
            'smtp_encryption' => 'tls',
            'status' => Status::ACTIVE,
            'order' => 1,
        ]);

        $this->actingAs($this->superUser)
            ->get(route('app.masters.email.providers.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('masters/email/providers/index')
                ->has('emailProviders.data', 1)
                ->has('statistics')
                ->has('filters'));

        $this->actingAs($this->superUser)
            ->get(route('app.masters.email.providers.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('masters/email/providers/create')
                ->where('initialValues.smtp_encryption', 'none')
                ->where('initialValues.smtp_password', '')
                ->has('statusOptions', 2)
                ->has('encryptionOptions', 3));

        $this->actingAs($this->superUser)
            ->get(route('app.masters.email.providers.edit', $provider))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('masters/email/providers/edit')
                ->where('emailProvider.id', $provider->id)
                ->where('initialValues.smtp_password', '')
                ->where('initialValues.smtp_encryption', 'tls'));

        $this->actingAs($this->superUser)
            ->get(route('app.masters.email.providers.show', $provider))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('masters/email/providers/show')
                ->where('emailProvider.id', $provider->id)
                ->where('emailProvider.has_smtp_password', true));
    }

    public function test_email_template_pages_render_expected_props(): void
    {
        $provider = EmailProvider::query()->create([
            'name' => 'Template Provider',
            'sender_name' => 'Support',
            'sender_email' => 'support@example.com',
            'smtp_host' => 'smtp.example.com',
            'smtp_user' => 'smtp-user',
            'smtp_password' => 'secret',
            'smtp_port' => '587',
            'smtp_encryption' => 'tls',
            'status' => Status::ACTIVE,
        ]);

        $template = EmailTemplate::query()->create([
            'name' => 'Welcome Email',
            'subject' => 'Welcome aboard',
            'message' => 'Hello there!',
            'send_to' => 'alpha@example.com,beta@example.com',
            'provider_id' => $provider->id,
            'is_raw' => true,
            'status' => Status::ACTIVE,
        ]);

        $this->actingAs($this->superUser)
            ->get(route('app.masters.email.templates.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('masters/email/templates/index')
                ->has('emailTemplates.data', 1)
                ->has('providerOptions', 1)
                ->has('filters'));

        $this->actingAs($this->superUser)
            ->get(route('app.masters.email.templates.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('masters/email/templates/create')
                ->where('initialValues.provider_id', '')
                ->where('initialValues.is_raw', false)
                ->has('statusOptions', 2)
                ->has('providerOptions', 1));

        $this->actingAs($this->superUser)
            ->get(route('app.masters.email.templates.edit', $template))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('masters/email/templates/edit')
                ->where('emailTemplate.id', $template->id)
                ->where('initialValues.provider_id', (string) $provider->id)
                ->where('initialValues.is_raw', true));

        $this->actingAs($this->superUser)
            ->get(route('app.masters.email.templates.show', $template))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('masters/email/templates/show')
                ->where('emailTemplate.id', $template->id)
                ->where('emailTemplate.send_to_list', ['alpha@example.com', 'beta@example.com']));
    }

    public function test_email_log_pages_render_expected_props(): void
    {
        $provider = EmailProvider::query()->create([
            'name' => 'Logs Provider',
            'sender_name' => 'Notifier',
            'sender_email' => 'notify@example.com',
            'smtp_host' => 'smtp.example.com',
            'smtp_user' => 'smtp-user',
            'smtp_password' => 'secret',
            'smtp_port' => '587',
            'smtp_encryption' => 'tls',
            'status' => Status::ACTIVE,
        ]);

        $template = EmailTemplate::query()->create([
            'name' => 'Alert Email',
            'subject' => 'System Alert',
            'message' => 'Something happened.',
            'provider_id' => $provider->id,
            'is_raw' => false,
            'status' => Status::ACTIVE,
        ]);

        $log = EmailLog::query()->create([
            'email_template_id' => $template->id,
            'template_name' => $template->name,
            'email_provider_id' => $provider->id,
            'provider_name' => $provider->name,
            'sent_by' => $this->superUser->id,
            'status' => EmailLog::STATUS_SENT,
            'subject' => 'System Alert',
            'body' => 'A test alert message.',
            'recipients' => ['alerts@example.com'],
            'context' => ['ticket' => 42],
            'sent_at' => now(),
        ]);

        $this->actingAs($this->superUser)
            ->get(route('app.masters.email.logs.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('masters/email/logs/index')
                ->has('emailLogs.data', 1)
                ->has('providerOptions', 1)
                ->has('templateOptions', 1)
                ->has('filters'));

        $this->actingAs($this->superUser)
            ->get(route('app.masters.email.logs.show', $log))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('masters/email/logs/show')
                ->where('emailLog.id', $log->id)
                ->where('emailLog.subject', 'System Alert')
                ->where('emailLog.body', 'A test alert message.'));
    }

    public function test_non_super_users_cannot_access_email_management_pages(): void
    {
        $this->actingAs($this->administrator)
            ->get(route('app.masters.email.providers.index'))
            ->assertForbidden();

        $this->actingAs($this->administrator)
            ->get(route('app.masters.email.logs.index'))
            ->assertForbidden();
    }
}
