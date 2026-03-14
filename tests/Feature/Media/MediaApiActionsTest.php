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
use Tests\TestCase;

class MediaApiActionsTest extends TestCase
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
        $this->manager->givePermissionTo([
            'view_media',
            'add_media',
            'edit_media',
            'delete_media',
        ]);
    }

    public function test_authorized_users_can_fetch_upload_settings_and_media_details(): void
    {
        $media = $this->manager->addMedia(UploadedFile::fake()->image('asset.jpg'))
            ->usingName('Hero Asset')
            ->toMediaCollection('library', 'public');

        $this->actingAs($this->manager)
            ->getJson(route('app.media.upload-settings'))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.upload_route', route('app.media.upload-media'));

        $this->actingAs($this->manager)
            ->getJson(route('app.media.details', $media->id))
            ->assertOk()
            ->assertJsonPath('status', 1)
            ->assertJsonPath('data.id', $media->id)
            ->assertJsonPath('data.name', 'Hero Asset');
    }

    public function test_authorized_users_can_update_media_details_and_bulk_metadata(): void
    {
        $media = $this->manager->addMedia(UploadedFile::fake()->image('asset.jpg'))
            ->usingName('Original Name')
            ->toMediaCollection('library', 'public');

        $this->actingAs($this->manager)
            ->postJson(route('app.media.detail.update'), [
                'media_id' => $media->id,
                'media_name' => 'Updated Name',
                'media_alt' => 'Accessible alt text',
                'media_caption' => 'A useful caption',
                'media_tags' => 'home,hero',
                'media_description' => 'Image description',
            ])
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('media.name', 'Updated Name');

        $media->refresh();

        $this->assertSame('Updated Name', $media->name);
        $this->assertSame('Accessible alt text', $media->getCustomProperty('alt_text'));
        $this->assertSame('A useful caption', $media->getCustomProperty('caption'));

        $this->actingAs($this->manager)
            ->postJson(route('app.media.bulk.update-metadata'), [
                'ids' => [$media->id],
                'metadata' => [
                    'copyright' => 'Acme Inc.',
                    'license' => 'Internal Use',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('status', 1);

        $media->refresh();

        $this->assertSame('Acme Inc.', $media->getCustomProperty('copyright'));
        $this->assertSame('Internal Use', $media->getCustomProperty('license'));
    }

    public function test_authorized_users_can_delete_and_restore_media(): void
    {
        $media = $this->manager->addMedia(UploadedFile::fake()->image('asset.jpg'))
            ->usingName('Disposable Asset')
            ->toMediaCollection('library', 'public');

        $this->actingAs($this->manager)
            ->deleteJson(route('app.media.destroy', $media->id))
            ->assertOk()
            ->assertJsonPath('status', 1);

        $this->assertSoftDeleted('media', ['id' => $media->id]);

        $this->actingAs($this->manager)
            ->patchJson(route('app.media.restore', $media->id))
            ->assertOk()
            ->assertJsonPath('status', 1);

        $this->assertDatabaseHas('media', [
            'id' => $media->id,
            'deleted_at' => null,
        ]);
    }
}
