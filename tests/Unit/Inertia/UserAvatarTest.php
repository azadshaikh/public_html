<?php

namespace Tests\Unit\Inertia;

use App\Inertia\Properties\UserAvatar;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\PropertyContext;
use Tests\TestCase;

class UserAvatarTest extends TestCase
{
    public function test_it_returns_a_public_disk_url_for_a_stored_avatar(): void
    {
        Storage::fake('public');

        $user = User::factory()->make([
            'name' => 'Jane Doe',
        ]);

        $user->forceFill([
            'avatar' => 'avatars/jane-doe.png',
        ]);

        $avatar = new UserAvatar($user);

        $this->assertSame(
            (string) get_media_url('avatars/jane-doe.png', get_storage_disk(), false),
            $avatar->toInertiaProperty($this->propertyContext()),
        );
    }

    public function test_it_returns_an_existing_remote_avatar_url_unchanged(): void
    {
        $user = User::factory()->make([
            'name' => 'Jane Doe',
        ]);

        $user->forceFill([
            'avatar' => 'https://cdn.example.com/avatars/jane-doe.png',
        ]);

        $avatar = new UserAvatar($user);

        $this->assertSame(
            'https://cdn.example.com/avatars/jane-doe.png',
            $avatar->toInertiaProperty($this->propertyContext()),
        );
    }

    public function test_it_falls_back_to_a_ui_avatar_url_when_no_avatar_exists(): void
    {
        $user = User::factory()->make([
            'name' => 'Jane Doe',
        ]);

        $avatar = new UserAvatar($user, 128);

        $this->assertSame(
            'https://ui-avatars.com/api/?name=Jane+Doe&size=128',
            $avatar->toInertiaProperty($this->propertyContext()),
        );
    }

    protected function propertyContext(): PropertyContext
    {
        return new PropertyContext('avatar', [], Request::create('/'));
    }
}
