<?php

namespace App\Services;

use App\Enums\Status;
use App\Exceptions\AccountBannedException;
use App\Exceptions\AccountSuspendedException;
use App\Models\Role;
use App\Models\User;
use App\Models\UserProvider;
use Exception;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SocialLoginService
{
    public function __construct(protected UserService $userService) {}

    /**
     * Find or create a user from social login data.
     */
    public function findOrCreateUser($socialUser, string $provider): User
    {
        $userProvider = UserProvider::query()->where('provider_id', $socialUser->getId())->first();

        if ($userProvider) {
            $user = User::query()->findOrFail($userProvider->user_id);
        } else {
            $user = User::query()->where('email', $socialUser->getEmail())->first();
            if ($user) {
                $this->createUserProvider($user, $socialUser, $provider);
            } else {
                $user = $this->createNewUser($socialUser, $provider);
            }
        }

        if ($this->hasStatus($user, Status::BANNED)) {
            throw new AccountBannedException(__('auth.account_banned'));
        }

        if ($this->hasStatus($user, Status::SUSPENDED)) {
            throw new AccountSuspendedException(__('auth.account_suspended'));
        }

        return $user;
    }

    /**
     * Store media from external URL
     *
     * @param  string  $url  The external URL of the media
     * @param  string  $directory  The directory to store the file in (e.g., 'users/avatars')
     * @param  string  $extension  The file extension (e.g., 'jpg', 'png')
     * @return string|null The path to the stored file or null if failed
     */
    public function storeMediaFromUrl($url, string $directory, string $extension = 'jpg'): ?string
    {
        try {
            $rootFolder = get_storage_root_folder();
            $disk = get_storage_disk();

            // Generate a unique filename inside root folder
            $filename = trim($rootFolder.'/'.$directory, '/').'/'.Str::uuid().'.'.$extension;

            // Get the file content from the URL
            $fileContent = file_get_contents($url);

            // Store the file
            $path = Storage::disk($disk)->put($filename, $fileContent);

            if ($path) {
                return $filename;
            }

            return null;
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Check if any social login provider is enabled.
     */
    public function hasEnabledSocialLogins(): bool
    {
        return config('services.google.enabled', false) || config('services.github.enabled', false);
    }

    /**
     * Create a new user provider record.
     */
    protected function createUserProvider(User $user, $socialUser, string $provider): void
    {
        UserProvider::query()->create([
            'user_id' => $user->id,
            'provider_id' => $socialUser->getId(),
            'avatar' => $socialUser->getAvatar(),
            'provider' => $provider,
        ]);
    }

    /**
     * Create a new user from social login data.
     */
    protected function createNewUser($socialUser, string $provider): User
    {
        $name = $socialUser->getName() ?: $socialUser->getEmail();
        [$firstName, $lastName] = $this->userService->splitName($name);
        $email = $socialUser->getEmail();

        if ($provider === 'github') {
            $firstName = null;
            $lastName = null;
            $name = $socialUser->getNickname() ?: $email;
        }

        $user = User::query()->create([
            'name' => $name,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'password' => Hash::make(Str::random(16)),
            'status' => Status::ACTIVE,
            'email_verified_at' => now(),
        ]);

        $this->setupUserProfile($user, $socialUser, $provider);

        return $user;
    }

    /**
     * Set up the user profile and related data.
     */
    protected function setupUserProfile(User $user, $socialUser, string $provider): void
    {
        $this->handleAvatarUpload($user, $socialUser->getAvatar());

        if (! $user->roles()->exists()) {
            $defaultRoleId = (int) setting('registration_default_role', 5);
            $defaultRole = Role::query()
                ->where('status', Status::ACTIVE)
                ->where('id', $defaultRoleId)
                ->first();

            if (! $defaultRole) {
                $defaultRole = Role::query()
                    ->where('status', Status::ACTIVE)
                    ->where('name', 'user')
                    ->first();
            }

            if ($defaultRole) {
                $user->syncRoles([$defaultRole->id]);
            }
        }

        $this->createUserProvider($user, $socialUser, $provider);
    }

    /**
     * Handle avatar upload using Laravel's storage functionality.
     */
    protected function handleAvatarUpload(User $user, string $avatarUrl): void
    {
        try {
            $avatar_path = $this->storeMediaFromUrl($avatarUrl, 'users/avatars');

            // Update user's avatar path
            $user->avatar = $avatar_path;
            $user->save();
        } catch (Exception) {
            // Set a default avatar path or leave it null
            $user->avatar = null;
            $user->save();
        }
    }

    private function hasStatus(User $user, Status $status): bool
    {
        $current = $user->getAttribute('status');

        if ($current instanceof Status) {
            return $current === $status;
        }

        return is_string($current) && $current === $status->value;
    }
}
