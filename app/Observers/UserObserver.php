<?php

namespace App\Observers;

use App\Models\User;

class UserObserver
{
    /**
     * Handle the User "saving" event.
     * Auto-sync the name field from first_name and last_name.
     */
    public function saving(User $user): void
    {
        // Skip auto-sync if name was explicitly set AND first_name/last_name weren't changed
        // This allows explicit name setting via APIs while still auto-syncing when names change
        $nameWasExplicitlySet = $user->isDirty('name') && ! empty($user->name);
        $namesWereChanged = $user->isDirty(['first_name', 'last_name']);

        // Only auto-sync if:
        // 1. name is empty (needs a value), OR
        // 2. first_name/last_name changed AND name wasn't explicitly set to a different value
        if (empty($user->name) || ($namesWereChanged && ! $nameWasExplicitlySet)) {
            // Build name from first_name and last_name
            $name = trim(($user->first_name ?? '').' '.($user->last_name ?? ''));

            // Fallback to username or email if name is still empty
            if ($name === '' || $name === '0') {
                $name = $user->username ?: $user->email;
            }

            $user->name = $name;
        }
    }
}
