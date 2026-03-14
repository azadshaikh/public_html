<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaStorageRootTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_media_uploads_use_the_configured_root_folder_prefix(): void
    {
        Storage::fake('public');

        config([
            'media.media_storage_root' => '/azad/',
            'media-library.disk_name' => 'public',
            'filesystems.disks.public.driver' => 'local',
        ]);

        $user = User::factory()->create();

        $media = $user->addMedia(UploadedFile::fake()->image('avatar.jpg'))
            ->toMediaCollection('avatars', 'public');

        $this->assertSame('azad', $media->media_storage_root);
        Storage::disk('public')->assertExists(
            sprintf('azad/%s/%s', $media->uuid, $media->file_name)
        );
    }
}
