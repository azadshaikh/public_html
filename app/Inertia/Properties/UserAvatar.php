<?php

namespace App\Inertia\Properties;

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Inertia\PropertyContext;
use Inertia\ProvidesInertiaProperty;

class UserAvatar implements ProvidesInertiaProperty
{
    public function __construct(
        protected User $user,
        protected int $size = 64,
    ) {}

    public function toInertiaProperty(PropertyContext $context): string
    {
        $avatar = $this->user->getAttribute('avatar');

        if (is_string($avatar) && $avatar !== '') {
            if (filter_var($avatar, FILTER_VALIDATE_URL) !== false) {
                return $avatar;
            }

            return Storage::disk('public')->url($avatar);
        }

        return sprintf(
            'https://ui-avatars.com/api/?name=%s&size=%d',
            urlencode($this->user->name),
            $this->size,
        );
    }
}
