<?php

namespace Tests\Feature\Account;

use App\Enums\Status;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\InteractsWithModuleManifest;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use InteractsWithModuleManifest;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpModuleManifest('profile-modules.json');
    }

    protected function tearDown(): void
    {
        $this->tearDownModuleManifest();

        parent::tearDown();
    }

    public function test_profile_page_is_displayed_with_profile_props(): void
    {
        $this->setModuleStatuses([
            'CMS' => 'enabled',
        ]);

        $user = User::factory()->create([
            'first_name' => 'Super',
            'last_name' => 'User',
            'name' => 'Super User',
            'username' => 'superuser',
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('app.profile'));

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('account/profile')
                ->where('profile.first_name', 'Super')
                ->where('profile.last_name', 'User')
                ->where('profile.full_name', 'Super User')
                ->where('profile.username', 'superuser')
                ->where('profile.email', $user->email)
                ->where('profile.phone', '')
                ->has('profile.avatar_url')
                ->where('showUsername', true));
    }

    public function test_profile_page_hides_username_when_cms_module_is_disabled(): void
    {
        $this->setModuleStatuses([
            'CMS' => 'disabled',
        ]);

        $user = User::factory()->create([
            'first_name' => 'Super',
            'last_name' => 'User',
            'name' => 'Super User',
            'username' => 'superuser',
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('app.profile'));

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('account/profile')
                ->where('showUsername', false));
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Super',
            'last_name' => 'User',
            'name' => 'Super User',
            'username' => 'superuser',
        ]);

        $response = $this
            ->actingAs($user)
            ->patch(route('app.profile.update'), [
                'first_name' => 'Updated',
                'last_name' => 'Profile',
                'username' => 'updated-profile',
                'phone' => '9999999999',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('app.profile'));

        $user->refresh();

        $this->assertSame('Updated', $user->first_name);
        $this->assertSame('Profile', $user->last_name);
        $this->assertSame('Updated Profile', $user->name);
        $this->assertSame('updated-profile', $user->username);
    }

    public function test_email_cannot_be_changed_via_profile_update(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Super',
            'last_name' => 'User',
            'name' => 'Super User',
            'username' => 'superuser',
        ]);

        $originalEmail = $user->email;
        $originalVerificationTimestamp = $user->email_verified_at;

        $response = $this
            ->actingAs($user)
            ->patch(route('app.profile.update'), [
                'first_name' => 'Renamed',
                'last_name' => 'User',
                'username' => 'superuser',
                'email' => 'changed@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('app.profile'));

        $user->refresh();

        $this->assertSame($originalEmail, $user->email);
        $this->assertSame($originalVerificationTimestamp?->toISOString(), $user->email_verified_at?->toISOString());
    }

    public function test_profile_avatar_upload_uses_the_configured_storage_root_folder(): void
    {
        Storage::fake('public');

        config()->set('media-library.disk_name', 'public');
        config()->set('media.media_storage_root', 'media-root');

        $user = User::factory()->create([
            'first_name' => 'Super',
            'last_name' => 'User',
            'name' => 'Super User',
            'status' => Status::ACTIVE,
        ]);

        $response = $this
            ->actingAs($user)
            ->patch(route('app.profile.update'), [
                'first_name' => 'Updated',
                'last_name' => 'User',
                'phone' => '9999999999',
                'avatar' => UploadedFile::fake()->image('avatar.jpg'),
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('app.profile'));

        $user->refresh();

        $this->assertIsString($user->avatar);
        $this->assertStringStartsWith('media-root/avatars/', $user->avatar);
        Storage::disk('public')->assertExists($user->avatar);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Super',
            'last_name' => 'User',
            'name' => 'Super User',
        ]);

        $response = $this
            ->actingAs($user)
            ->delete(route('app.profile.destroy'), [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Super',
            'last_name' => 'User',
            'name' => 'Super User',
        ]);

        $response = $this
            ->actingAs($user)
            ->from(route('app.profile'))
            ->delete(route('app.profile.destroy'), [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('userDeletion', ['password'])
            ->assertRedirect(route('app.profile'));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'deleted_at' => null,
        ]);
    }
}
