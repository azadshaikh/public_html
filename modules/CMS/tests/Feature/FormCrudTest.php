<?php

declare(strict_types=1);

namespace Modules\CMS\Tests\Feature;

use App\Enums\Status;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\CMS\Models\Form;
use Tests\TestCase;

class FormCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        foreach (['view_cms_forms', 'add_cms_forms', 'edit_cms_forms', 'delete_cms_forms', 'restore_cms_forms'] as $permission) {
            Permission::query()->firstOrCreate(
                ['name' => $permission, 'guard_name' => 'web'],
                [
                    'display_name' => ucwords(str_replace('_', ' ', $permission)),
                    'group' => 'forms',
                    'module_slug' => 'cms',
                ],
            );
        }

        $this->admin = User::factory()->create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'status' => Status::ACTIVE,
            'email_verified_at' => now(),
        ]);
        $this->admin->assignRole(Role::findByName('administrator', 'web'));
        $this->admin->givePermissionTo([
            'view_cms_forms',
            'add_cms_forms',
            'edit_cms_forms',
            'delete_cms_forms',
            'restore_cms_forms',
        ]);
    }

    public function test_guests_are_redirected_from_forms_create_page(): void
    {
        $this->get(route('cms.form.create'))
            ->assertRedirect(route('login'));
    }

    public function test_admin_can_access_forms_create_page(): void
    {
        $this->actingAs($this->admin)
            ->get(route('cms.form.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('cms/forms/create')
                ->where('initialValues.template', 'default')
                ->where('initialValues.form_type', 'standard')
                ->where('initialValues.status', 'draft')
                ->where('initialValues.store_in_database', true)
                ->where('initialValues.is_active', true)
                ->has('statusOptions')
                ->has('templateOptions')
                ->has('formTypeOptions')
            );
    }

    public function test_admin_can_access_forms_edit_page(): void
    {
        $form = $this->createForm([
            'confirmations' => [
                'type' => 'redirect',
                'redirect' => 'https://example.com/thank-you',
            ],
            'published_at' => Carbon::parse('2025-01-15 10:30:00', 'UTC'),
            'submissions_count' => 14,
            'views_count' => 120,
            'conversion_rate' => 11.7,
        ]);

        $this->actingAs($this->admin)
            ->get(route('cms.form.edit', $form))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('cms/forms/edit')
                ->where('form.id', $form->id)
                ->where('form.title', 'Contact Form')
                ->where('initialValues.title', 'Contact Form')
                ->where('initialValues.slug', 'contact-form')
                ->where('initialValues.shortcode', 'form_contact')
                ->where('initialValues.template', 'contact')
                ->where('initialValues.form_type', 'standard')
                ->where('initialValues.confirmation_type', 'redirect')
                ->where('initialValues.redirect_url', 'https://example.com/thank-you')
                ->where(
                    'initialValues.published_at',
                    $form->published_at?->setTimezone(app_localization_timezone())->format('Y-m-d\TH:i'),
                )
            );
    }

    public function test_form_show_route_redirects_to_edit_page(): void
    {
        $form = $this->createForm();

        $this->actingAs($this->admin)
            ->get(route('cms.form.show', $form))
            ->assertRedirect(route('cms.form.edit', $form));
    }

    public function test_admin_can_store_a_form(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('cms.form.store'), [
                'title' => 'Newsletter Signup',
                'slug' => 'newsletter-signup',
                'shortcode' => 'form_newsletter_signup',
                'template' => 'newsletter',
                'form_type' => 'popup',
                'html' => '<form><input type="email" name="email" /></form>',
                'css' => '.newsletter-form { display: grid; }',
                'store_in_database' => true,
                'confirmation_type' => 'message',
                'confirmation_message' => 'Thanks for subscribing!',
                'status' => 'published',
                'is_active' => true,
                'published_at' => '',
            ]);

        $form = Form::query()->where('slug', 'newsletter-signup')->firstOrFail();

        $response->assertRedirect(route('cms.form.edit', $form));

        $this->assertSame('Newsletter Signup', $form->title);
        $this->assertSame('newsletter', $form->template);
        $this->assertSame('popup', $form->form_type);
        $this->assertSame('message', $form->confirmations['type'] ?? null);
        $this->assertSame('Thanks for subscribing!', $form->confirmations['message'] ?? null);
        $this->assertTrue($form->store_in_database);
        $this->assertTrue($form->is_active);
        $this->assertSame('published', $form->status);
        $this->assertNotNull($form->published_at);
    }

    public function test_admin_can_update_a_form(): void
    {
        $form = $this->createForm();
        $publishAt = Carbon::parse('2025-03-01 14:00:00', app_localization_timezone());

        $response = $this->actingAs($this->admin)
            ->put(route('cms.form.update', $form), [
                'title' => 'Updated Contact Form',
                'slug' => 'updated-contact-form',
                'shortcode' => 'form_updated_contact',
                'template' => 'feedback',
                'form_type' => 'conversational',
                'html' => '<form><textarea name="message"></textarea></form>',
                'css' => '.feedback-form { gap: 1rem; }',
                'store_in_database' => false,
                'confirmation_type' => 'redirect',
                'redirect_url' => 'https://example.com/feedback-received',
                'status' => 'draft',
                'is_active' => false,
                'published_at' => $publishAt->format('Y-m-d\TH:i'),
            ]);

        $response->assertRedirect(route('cms.form.edit', $form));

        $form->refresh();

        $this->assertSame('Updated Contact Form', $form->title);
        $this->assertSame('updated-contact-form', $form->slug);
        $this->assertSame('feedback', $form->template);
        $this->assertSame('conversational', $form->form_type);
        $this->assertFalse($form->store_in_database);
        $this->assertFalse($form->is_active);
        $this->assertSame('redirect', $form->confirmations['type'] ?? null);
        $this->assertSame('https://example.com/feedback-received', $form->confirmations['redirect'] ?? null);
        $this->assertSame('draft', $form->status);
        $this->assertSame(
            $publishAt->clone()->utc()->toDateTimeString(),
            $form->published_at?->utc()->toDateTimeString(),
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createForm(array $overrides = []): Form
    {
        return Form::query()->create(array_merge([
            'title' => 'Contact Form',
            'slug' => 'contact-form',
            'shortcode' => 'form_contact',
            'template' => 'contact',
            'form_type' => 'standard',
            'html' => '<form><input name="email" type="email" /></form>',
            'css' => '.contact-form { display: grid; }',
            'confirmations' => [
                'type' => 'message',
                'message' => 'Thanks for reaching out.',
            ],
            'store_in_database' => true,
            'status' => 'published',
            'is_active' => true,
            'submissions_count' => 4,
            'views_count' => 20,
            'conversion_rate' => 20.0,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ], $overrides));
    }
}
