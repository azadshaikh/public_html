<?php

declare(strict_types=1);

namespace App\Support\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;

class SuperUserAccess
{
    /**
     * Determine whether the given user is a super user.
     */
    public static function allows(?Authenticatable $user): bool
    {
        return $user !== null
            && method_exists($user, 'isSuperUser')
            && $user->isSuperUser();
    }

    /**
     * Determine whether the current request is being made by a super user.
     */
    public static function allowsRequest(Request $request): bool
    {
        return self::allows($request->user());
    }
}
