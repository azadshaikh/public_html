<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\CustomMedia;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomMediaTest extends TestCase
{
    public function test_get_url_falls_back_to_configured_disk_when_media_disk_is_missing(): void
    {
        config([
            'media-library.disk_name' => 'public',
            'media.media_storage_root' => 'media',
            'filesystems.disks.public.url' => 'https://example.test/storage',
        ]);

        $media = new CustomMedia;
        $media->forceFill([
            'uuid' => 'test-media-uuid',
            'disk' => null,
            'file_name' => 'example.jpg',
            'generated_conversions' => [],
            'manipulations' => [],
            'custom_properties' => [],
            'responsive_images' => [],
        ]);

        $this->assertSame(
            Storage::disk('public')->url('media/test-media-uuid/example.jpg'),
            $media->getUrl(),
        );
        $this->assertSame('public', $media->disk);
    }
}
