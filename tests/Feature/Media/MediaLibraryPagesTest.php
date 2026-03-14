<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use App\Enums\Status;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MediaLibraryPagesTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        config()->set('media-library.disk_name', 'public');

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->manager = User::factory()->create([
            'first_name' => 'Media',
            'last_name' => 'Manager',
            'name' => 'Media Manager',
            'status' => Status::ACTIVE,
            'email_verified_at' => now(),
        ]);
        $this->manager->assignRole(Role::findByName('administrator', 'web'));
        $this->manager->givePermissionTo('view_media');
        $this->manager->givePermissionTo('delete_media');
    }

    public function test_guests_are_redirected_away_from_the_media_library(): void
    {
        $this->get(route('app.media-library.index'))
            ->assertRedirect(route('login'));
    }

    public function test_users_without_media_permissions_cannot_access_the_media_library(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Regular',
            'last_name' => 'User',
            'name' => 'Regular User',
            'status' => Status::ACTIVE,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('app.media-library.index'))
            ->assertForbidden();
    }

    public function test_authorized_users_can_view_the_media_library_and_refresh_its_data(): void
    {
        $media = $this->manager->addMedia(UploadedFile::fake()->image('asset.jpg'))
            ->usingName('Homepage Hero')
            ->toMediaCollection('library', 'public');

        $this->actingAs($this->manager)
            ->get(route('app.media-library.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('media/index')
                ->where('filters.status', 'all')
                ->where('uploadSettings.upload_route', route('app.media.upload-media'))
                ->where('statistics.total', 1)
                ->where('statistics.trash', 0)
                ->has('media.data', 1)
                ->where('media.data.0.id', $media->id)
                ->where('media.data.0.file_name', $media->file_name)
                ->where('media.data.0.name', 'Homepage Hero')
            );

        $this->actingAs($this->manager)
            ->getJson(route('app.media-library.refresh'))
            ->assertOk()
            ->assertJsonPath('statistics.total', 1)
            ->assertJsonPath('statistics.trash', 0)
            ->assertJsonPath('media.data.0.id', $media->id)
            ->assertJsonPath('media.data.0.file_name', $media->file_name);
    }
}
