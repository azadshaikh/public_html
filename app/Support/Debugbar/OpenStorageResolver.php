<?php

declare(strict_types=1);

namespace App\Support\Debugbar;

use App\Support\Auth\SuperUserAccess;
use Illuminate\Http\Request;

class OpenStorageResolver
{
    /**
     * Resolve whether Debugbar stored requests may be opened.
     */
    public static function resolve(Request $request): bool
    {
        return (bool) config('debugbar.storage_open', false)
            && (bool) config('debugbar.enabled')
            && (bool) config('app.debug')
            && SuperUserAccess::allowsRequest($request);
    }
}
